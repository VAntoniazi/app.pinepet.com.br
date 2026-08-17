<?php
declare(strict_types=1);
namespace App\Core;

final class Env
{
    public static function load(string $file): void
    {
        if (!is_readable($file)) return;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $key) || getenv($key) !== false) continue;
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) $value = substr($value, 1, -1);
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}
