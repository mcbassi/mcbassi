<?php
declare(strict_types=1);

namespace App\Diagnostico;

use PDO;
use RuntimeException;

final class FormFieldRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $this->ensureTable();

        $orderColumn = $this->resolveOrderColumn();
        $stmt = $this->pdo->query(sprintf('SELECT * FROM form_fields ORDER BY %s, id', $orderColumn));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => $this->normalizeField($row), $rows);
    }

    public function totalQuestions(): int
    {
        $this->ensureTable();

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM form_fields WHERE type NOT IN ('title', 'subtitle')");
        return (int) ($stmt->fetchColumn() ?: 0);
    }


    private function resolveOrderColumn(): string
    {
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'form_fields'
        ");
        $stmt->execute();
        $columns = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        foreach (['sort_order', 'ordem', 'order_no', 'display_order', 'position'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return 'id';
    }

    private function ensureTable(): void
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'form_fields'
        ");
        $stmt->execute();

        if ((int) $stmt->fetchColumn() <= 0) {
            throw new RuntimeException('A tabela form_fields não foi encontrada na base legado.');
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeField(array $row): array
    {
        $type = $this->normalizeType((string) ($row['type'] ?? 'text'));
        $options = [];

        if (!empty($row['options_json'])) {
            $decoded = json_decode((string) $row['options_json'], true);
            if (is_array($decoded)) {
                $options = array_values(array_map(static fn ($item): string => trim((string) $item), $decoded));
            }
        }

        $label = trim((string) ($row['label'] ?? ''));
        $isRequired = $this->inferRequired($row, $label, $type);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'sort_order' => (int) ($row['sort_order'] ?? $row['ordem'] ?? $row['order_no'] ?? $row['display_order'] ?? $row['position'] ?? 0),
            'name' => trim((string) ($row['name'] ?? '')),
            'label' => $label,
            'type' => $type !== '' ? $type : 'text',
            'required' => $isRequired,
            'placeholder' => trim((string) ($row['placeholder'] ?? '')),
            'prompt_code' => trim((string) ($row['prompt_code'] ?? '')),
            'options' => $options,
            'min' => $row['min_value'] ?? $row['min'] ?? null,
            'max' => $row['max_value'] ?? $row['max'] ?? null,
            'step' => $row['step'] ?? null,
        ];
    }



    private function inferRequired(array $row, string $label, string $type): bool
    {
        if (in_array($type, ['title', 'subtitle'], true)) {
            return false;
        }

        return $this->toBool($row['required'] ?? false);
    }

    private function normalizeType(string $type): string
    {
        $normalized = strtolower(trim($type));

        return match ($normalized) {
            'titulo', 'title', 'chapter', 'capitulo', 'capítulo', 'section_title' => 'title',
            'subtitulo', 'subtitle', 'sub_title', 'sub-title', 'section', 'subsection', 'seccion', 'seção' => 'subtitle',
            default => $normalized !== '' ? $normalized : 'text',
        };
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $string = trim((string) $value);
        if ($string === '') {
            return false;
        }

        if (ctype_digit($string)) {
            return (int) $string === 1;
        }

        return in_array(strtolower($string), ['1', 'true', 'sim', 'yes'], true);
    }
}
