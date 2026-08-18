<?php
declare(strict_types=1);

use App\Core\Csrf;

function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    if ($value === false || $value === '') $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    if (!is_string($value)) return $value;
    if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) $value = substr($value, 1, -1);
    return match (strtolower($value)) {'true','(true)' => true, 'false','(false)' => false, 'null','(null)' => null, default => $value};
}
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function asset(string $path): string
{
    $target = '/' . ltrim($path, '/');
    $file = BASE_PATH . '/public' . $target;
    if ($target === '/assets/images/logo.svg' && is_file($file)) {
        $contents = file_get_contents($file);
        if (is_string($contents)) return 'data:image/svg+xml;base64,' . base64_encode($contents);
    }
    return is_file($file) ? $target . '?v=' . substr(hash_file('sha256', $file), 0, 12) : $target;
}
function inline_asset(string $relativePath): string
{
    $allowed = ['assets/css/app.css', 'assets/css/onboarding.css', 'assets/css/onboarding-responsive.css', 'assets/css/onboarding-compact.css', 'assets/css/import.css', 'assets/js/app.js', 'assets/js/import.js'];
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    if (!in_array($relativePath, $allowed, true)) throw new InvalidArgumentException('Asset inline não permitido.');
    $contents = file_get_contents(BASE_PATH . '/public/' . $relativePath);
    if (!is_string($contents)) throw new RuntimeException('Não foi possível carregar o asset da interface.');
    return $contents;
}
function csrf_field(): string { return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">'; }
function flash(string $key, mixed $value): void { $_SESSION['_flash'][$key] = $value; }
function pull_flash(string $key, mixed $default = null): mixed { $value = $_SESSION['_flash'][$key] ?? $default; unset($_SESSION['_flash'][$key]); return $value; }
