<?php
declare(strict_types=1);

namespace App\Agents;

use RuntimeException;

final class AgentConfigService
{
    public function configPath(): string
    {
        $candidates = [
            \app_path('config.cfg'),
            \app_path('Config.cfg'),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    /** @return array<string, array<string, string>> */
    public function readConfig(): array
    {
        $path = $this->configPath();
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Arquivo de configuração não encontrado: ' . $path);
        }

        $config = parse_ini_file($path, true, INI_SCANNER_RAW);
        if (!is_array($config)) {
            throw new RuntimeException('Não foi possível ler o arquivo de configuração: ' . $path);
        }

        $normalized = [];
        foreach ($config as $section => $values) {
            if (!is_array($values)) {
                continue;
            }
            $normalized[(string) $section] = [];
            foreach ($values as $key => $value) {
                $normalized[(string) $section][(string) $key] = is_scalar($value) || $value === null
                    ? trim((string) $value)
                    : '';
            }
        }

        return $normalized;
    }

    /** @return array<int, string> */
    public function sections(): array
    {
        $sections = array_keys($this->readConfig());
        return array_values(array_filter(array_map('strval', $sections), static fn(string $section): bool => strtoupper($section) !== 'DEFAULT'));
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function saveSection(string $section, array $fields): void
    {
        $section = trim($section);
        if ($section === '') {
            throw new RuntimeException('Seção inválida.');
        }

        $config = $this->readConfig();
        if (!isset($config[$section])) {
            throw new RuntimeException("Seção '{$section}' não encontrada.");
        }

        foreach ($fields as $key => $value) {
            $field = trim((string) $key);
            if ($field === '') {
                continue;
            }
            $config[$section][$field] = is_scalar($value) || $value === null ? (string) $value : '';
        }

        $content = $this->buildIni($config);
        $path = $this->configPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            throw new RuntimeException('Diretório de configuração não encontrado: ' . $dir);
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('Diretório de configuração sem permissão de escrita: ' . $dir);
        }

        if (@file_put_contents($path, $content) === false) {
            throw new RuntimeException('Não foi possível salvar o arquivo de configuração.');
        }
    }

    /** @param array<string, array<string, string>> $config */
    private function buildIni(array $config): string
    {
        $lines = [];
        foreach ($config as $section => $pairs) {
            $lines[] = '[' . $section . ']';
            foreach ($pairs as $key => $value) {
                $escaped = addcslashes((string) $value, "\\\"");
                $lines[] = $key . ' = "' . $escaped . '"';
            }
            $lines[] = '';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
