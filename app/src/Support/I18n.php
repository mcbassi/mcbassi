<?php
declare(strict_types=1);

namespace App\Support;

final class I18n
{
    /** @var array<string,string> */
    private static array $available = [
        'pt' => 'Português',
        'es' => 'Español',
        'en' => 'English',
    ];

    private static string $lang = 'pt';
    private static string $fallback = 'pt';

    /** @var array<string,string> */
    private static array $messages = [];

    /** @var array<string,string> */
    private static array $fallbackMessages = [];

    public static function boot(string $langDir, string $default = 'pt'): string
    {
        $queryLang = isset($_GET['lang']) ? trim((string) $_GET['lang']) : '';

        if ($queryLang !== '') {
            if (strtolower($queryLang) === 'auto') {
                unset($_SESSION['_lang'], $_SESSION['_lang_manual']);
                $lang = self::detectBrowserLang() ?: self::normalize($default);
            } else {
                $lang = self::normalize($queryLang);
                $_SESSION['_lang_manual'] = true;
            }
        } elseif (!empty($_SESSION['_lang_manual']) && !empty($_SESSION['_lang'])) {
            $lang = self::normalize((string) $_SESSION['_lang']);
        } else {
            $lang = self::detectBrowserLang() ?: self::normalize($default);
        }

        if (!isset(self::$available[$lang])) {
            $lang = self::normalize($default);
        }

        if (!isset(self::$available[$lang])) {
            $lang = self::$fallback;
        }

        self::$lang = $lang;
        $_SESSION['_lang'] = $lang;

        self::$fallbackMessages = self::loadFile($langDir, self::$fallback);
        self::$messages = $lang === self::$fallback
            ? self::$fallbackMessages
            : array_replace(self::$fallbackMessages, self::loadFile($langDir, $lang));

        return self::$lang;
    }

    public static function get(string $key, array $replace = [], ?string $default = null): string
    {
        $text = self::$messages[$key] ?? self::$fallbackMessages[$key] ?? $default ?? $key;

        foreach ($replace as $name => $value) {
            $text = str_replace(':' . (string) $name, (string) $value, $text);
        }

        return $text;
    }

    /**
     * Traduz texto HTML já renderizado, sem obrigar a conversão imediata de todas as views.
     * Usa somente chaves auto.* dos arquivos de idioma.
     */
    public static function translateHtml(string $html): string
    {
        if (self::$lang === self::$fallback || $html === '') {
            return $html;
        }

        $map = self::autoMap();
        if ($map === []) {
            return $html;
        }

        // Traduz atributos visuais comuns.
        $html = preg_replace_callback(
            '/\s(placeholder|title|aria-label|alt|data-title|data-label|data-confirm|data-placeholder)=("|\')(.*?)(\2)/isu',
            static function (array $m) use ($map): string {
                $value = html_entity_decode((string) $m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $translated = self::translatePlainText($value, $map);
                return ' ' . $m[1] . '=' . $m[2] . htmlspecialchars($translated, ENT_QUOTES, 'UTF-8') . $m[4];
            },
            $html
        ) ?? $html;

        // Separa tags e textos para não mexer dentro das tags.
        // Elementos com data-no-i18n ou translate="no" também ficam protegidos.
        $parts = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return $html;
        }

        $skipTags = ['script', 'style', 'textarea', 'pre', 'code'];
        $skipStack = [];

        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }

            if ($part[0] === '<') {
                if (preg_match('/^<\s*\/\s*([a-z0-9]+)/i', $part, $close)) {
                    $tag = strtolower($close[1]);
                    $last = end($skipStack);
                    if ($last === $tag) {
                        array_pop($skipStack);
                    }
                    continue;
                }

                if (preg_match('/^<\s*([a-z0-9]+)/i', $part, $open)) {
                    $tag = strtolower($open[1]);
                    $hasNoI18n = preg_match('/\sdata-no-i18n(\s|=|>)/i', $part) === 1
                        || preg_match('/\stranslate\s*=\s*"no"/i', $part) === 1
                        || preg_match("/\\stranslate\\s*=\\s*'no'/i", $part) === 1;

                    if (in_array($tag, $skipTags, true) || $hasNoI18n) {
                        $skipStack[] = $tag;
                    }
                }
                continue;
            }

            if ($skipStack === []) {
                $decoded = html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $translated = self::translatePlainText($decoded, $map);
                $parts[$i] = htmlspecialchars($translated, ENT_QUOTES, 'UTF-8');
            }
        }

        return implode('', $parts);
    }

    /** @return array<string,string> */
    public static function jsMessages(): array
    {
        return [
            'lang' => self::lang(),
            'loading' => self::get('common.loading'),
            'processing' => self::get('common.processing'),
            'success' => self::get('common.success'),
            'error' => self::get('common.error'),
            'save' => self::get('common.save'),
            'cancel' => self::get('common.cancel'),
            'search' => self::get('common.search'),
            'select' => self::get('common.select'),
            'not_found' => self::get('common.not_found'),
            'confirm_delete' => self::get('common.confirm_delete'),
            'auto_map' => self::autoMap(),
        ];
    }

    public static function lang(): string
    {
        return self::$lang;
    }

    public static function htmlLang(): string
    {
        return match (self::$lang) {
            'pt' => 'pt-BR',
            'es' => 'es-CO',
            'en' => 'en-US',
            default => self::$lang,
        };
    }

    /** @return array<string,string> */
    public static function available(): array
    {
        return self::$available;
    }

    private static function detectBrowserLang(): ?string
    {
        $header = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if ($header === '') {
            return null;
        }

        $candidates = [];
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $pieces = explode(';', $part);
            $locale = trim($pieces[0] ?? '');
            $quality = 1.0;

            if (isset($pieces[1]) && preg_match('/q=([0-9.]+)/', $pieces[1], $m)) {
                $quality = (float) $m[1];
            }

            $normalized = self::normalize($locale);
            if (isset(self::$available[$normalized])) {
                $candidates[$normalized] = max($candidates[$normalized] ?? 0, $quality);
            }
        }

        if ($candidates === []) {
            return null;
        }

        arsort($candidates, SORT_NUMERIC);
        return array_key_first($candidates);
    }

    private static function normalize(string $lang): string
    {
        $lang = strtolower(trim($lang));
        $lang = str_replace('_', '-', $lang);

        return match (true) {
            str_starts_with($lang, 'pt') => 'pt',
            str_starts_with($lang, 'es') => 'es',
            str_starts_with($lang, 'en') => 'en',
            default => $lang,
        };
    }

    /** @return array<string,string> */
    private static function loadFile(string $langDir, string $lang): array
    {
        $langDir = rtrim($langDir, DIRECTORY_SEPARATOR);
        $files = [
            $langDir . DIRECTORY_SEPARATOR . $lang . '.php',
            // Camada estável: evita regressão quando um patch sobrescreve o arquivo principal de idioma.
            $langDir . DIRECTORY_SEPARATOR . $lang . '_stable.php',
            // Camada local opcional para ajustes do cliente sem mexer nos arquivos-base.
            $langDir . DIRECTORY_SEPARATOR . $lang . '.local.php',
        ];

        $messages = [];
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $data = require $file;
            if (is_array($data)) {
                $messages = array_replace($messages, $data);
            }
        }

        return $messages;
    }

    /** @return array<string,string> */
    private static function autoMap(): array
    {
        $map = [];

        foreach (self::$fallbackMessages as $key => $source) {
            if (!str_starts_with((string) $key, 'auto.')) {
                continue;
            }

            $target = self::$messages[$key] ?? $source;
            $source = (string) $source;
            $target = (string) $target;

            if ($source !== '' && $target !== '' && $source !== $target) {
                $map[$source] = $target;
            }
        }

        uksort($map, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        return $map;
    }

    /** @param array<string,string> $map */
    private static function translatePlainText(string $text, array $map): string
    {
        if (trim($text) === '') {
            return $text;
        }

        foreach ($map as $source => $target) {
            $text = str_replace($source, $target, $text);
        }

        return $text;
    }
}
