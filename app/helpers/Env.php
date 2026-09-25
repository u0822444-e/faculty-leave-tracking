<?php

class Env
{
    private static bool $loaded = false;
    private static array $vars = [];

    public static function load(string $path): void
    {
        if (self::$loaded) return;
        if (!file_exists($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) continue;

            $pos = strpos($line, '=');
            if ($pos === false) continue;

            $key   = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            self::$vars[$key] = $value;
        }

        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        return self::$vars[$key] ?? $default;
    }
    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) return $default;
        return in_array(strtolower((string) $v), ['true', '1', 'yes', 'on'], true);
    }
}