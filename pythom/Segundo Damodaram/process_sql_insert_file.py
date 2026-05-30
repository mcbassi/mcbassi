#!/usr/bin/env python3
import argparse
import os
import re
import shutil
import sys
from datetime import datetime

try:
    import pymysql
    from pymysql.err import IntegrityError, MySQLError
except Exception as exc:  # pragma: no cover
    print("Erro: biblioteca pymysql não está instalada. Rode: py -m pip install pymysql")
    raise

HEADER_RE = re.compile(
    r"^\s*INSERT\s+INTO\s+`?(?P<table>[\w]+)`?\s*\((?P<cols>.+?)\)\s*VALUES\s*$",
    re.IGNORECASE,
)
FIRST_ID_RE = re.compile(r"^\s*,?\s*\((\d+)")
DUPLICATE_ERROR_CODES = {1062}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Processa um arquivo SQL com INSERT + linhas de VALUES, inserindo uma tupla por vez e removendo do arquivo as linhas já processadas."
    )
    parser.add_argument("--input", required=True, help="Arquivo SQL de entrada")
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=3306)
    parser.add_argument("--user", default="root")
    parser.add_argument("--password", default="")
    parser.add_argument("--database", default="statistics")
    parser.add_argument("--charset", default="utf8mb4")
    parser.add_argument("--log", default=None, help="Arquivo de log. Padrão: <input>.log.txt")
    parser.add_argument("--pending", default=None, help="Arquivo pendente. Padrão: <input>.pending.sql")
    parser.add_argument("--backup", action="store_true", help="Cria backup do arquivo original antes de substituir")
    parser.add_argument("--stop-on-error", action="store_true", help="Interrompe no primeiro erro não duplicado")
    return parser.parse_args()


def normalize_tuple_line(line: str) -> str:
    line = line.strip()
    if not line:
        return ""
    if line.endswith(","):
        line = line[:-1].rstrip()
    if line.endswith(";"):
        line = line[:-1].rstrip()
    return line


def extract_first_id(tuple_sql: str):
    m = FIRST_ID_RE.match(tuple_sql)
    return m.group(1) if m else None


def build_insert_sql(header: str, tuple_sql: str) -> str:
    return f"{header}\n{tuple_sql};"


def ensure_header(first_line: str) -> str:
    line = first_line.strip()
    if not HEADER_RE.match(line):
        raise ValueError(
            "A primeira linha não parece ser um INSERT ... VALUES válido."
        )
    return line


def write_log(log_path: str, msg: str) -> None:
    with open(log_path, "a", encoding="utf-8") as f:
        f.write(msg + "\n")


def main() -> int:
    args = parse_args()
    input_path = os.path.abspath(args.input)
    log_path = args.log or (input_path + ".log.txt")
    pending_path = args.pending or (input_path + ".pending.sql")
    temp_pending_path = pending_path + ".tmp"

    if not os.path.exists(input_path):
        print(f"Arquivo não encontrado: {input_path}")
        return 1

    with open(input_path, "r", encoding="utf-8", errors="replace") as f:
        try:
            header_line = next(f)
        except StopIteration:
            print("Arquivo vazio.")
            return 1
        header = ensure_header(header_line)
        remaining_lines = list(f)

    conn = pymysql.connect(
        host=args.host,
        port=args.port,
        user=args.user,
        password=args.password,
        database=args.database,
        charset=args.charset,
        autocommit=False,
    )

    processed_ok = 0
    processed_duplicate = 0
    processed_error = 0
    kept_pending = 0

    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    write_log(log_path, f"[{timestamp}] Início do processamento: {input_path}")

    try:
        with conn, conn.cursor() as cur, open(temp_pending_path, "w", encoding="utf-8") as pending:
            pending.write(header + "\n")

            for physical_line_no, raw_line in enumerate(remaining_lines, start=2):
                tuple_sql = normalize_tuple_line(raw_line)
                if not tuple_sql:
                    continue
                if not tuple_sql.startswith("("):
                    msg = (
                        f"LINHA={physical_line_no} ID=? ERRO=FORMATO_INVALIDO CODIGO=0 "
                        f"DETALHE=Linha não começa com '('"
                    )
                    write_log(log_path, msg)
                    pending.write(raw_line if raw_line.endswith("\n") else raw_line + "\n")
                    processed_error += 1
                    kept_pending += 1
                    if args.stop_on_error:
                        break
                    continue

                tuple_id = extract_first_id(tuple_sql) or "?"
                sql = build_insert_sql(header, tuple_sql)

                try:
                    cur.execute(sql)
                    conn.commit()
                    processed_ok += 1
                    write_log(
                        log_path,
                        f"LINHA={physical_line_no} ID={tuple_id} STATUS=INSERIDO CODIGO=0 DETALHE=OK",
                    )
                except IntegrityError as exc:
                    error_code = exc.args[0] if exc.args else 0
                    detail = str(exc).replace("\n", " ")
                    if error_code in DUPLICATE_ERROR_CODES:
                        conn.rollback()
                        processed_duplicate += 1
                        write_log(
                            log_path,
                            f"LINHA={physical_line_no} ID={tuple_id} STATUS=DUPLICADO CODIGO={error_code} DETALHE={detail}",
                        )
                    else:
                        conn.rollback()
                        processed_error += 1
                        kept_pending += 1
                        pending.write(tuple_sql + "\n")
                        write_log(
                            log_path,
                            f"LINHA={physical_line_no} ID={tuple_id} STATUS=ERRO CODIGO={error_code} DETALHE={detail}",
                        )
                        if args.stop_on_error:
                            break
                except MySQLError as exc:
                    conn.rollback()
                    error_code = exc.args[0] if exc.args else 0
                    detail = str(exc).replace("\n", " ")
                    processed_error += 1
                    kept_pending += 1
                    pending.write(tuple_sql + "\n")
                    write_log(
                        log_path,
                        f"LINHA={physical_line_no} ID={tuple_id} STATUS=ERRO CODIGO={error_code} DETALHE={detail}",
                    )
                    if args.stop_on_error:
                        break
                except Exception as exc:
                    conn.rollback()
                    processed_error += 1
                    kept_pending += 1
                    pending.write(tuple_sql + "\n")
                    detail = str(exc).replace("\n", " ")
                    write_log(
                        log_path,
                        f"LINHA={physical_line_no} ID={tuple_id} STATUS=ERRO CODIGO=-1 DETALHE={detail}",
                    )
                    if args.stop_on_error:
                        break

    finally:
        conn.close()

    # substitui o arquivo original pelo pending, removendo as linhas já processadas/duplicadas
    if args.backup:
        backup_path = input_path + ".bak"
        shutil.copy2(input_path, backup_path)

    with open(temp_pending_path, "r", encoding="utf-8") as f:
        pending_content = f.read().strip()

    if pending_content == header:
        # nada restou pendente
        os.remove(temp_pending_path)
        with open(input_path, "w", encoding="utf-8") as f:
            f.write(header + "\n")
        if os.path.exists(pending_path):
            os.remove(pending_path)
    else:
        shutil.move(temp_pending_path, pending_path)
        shutil.copy2(pending_path, input_path)

    summary = (
        f"Concluído. Inseridos={processed_ok}, Duplicados removidos={processed_duplicate}, "
        f"Erros mantidos para revisão={processed_error}, Pendentes atuais={kept_pending}."
    )
    print(summary)
    write_log(log_path, summary)
    return 0


if __name__ == "__main__":
    sys.exit(main())
