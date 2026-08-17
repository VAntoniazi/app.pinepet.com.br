<?php
declare(strict_types=1);
namespace App\Core;
final class Csrf
{
    public static function token(): string { return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32)); }
    public static function validate(mixed $token): bool { return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'],$token); }
    public static function rotate(): void { $_SESSION['_csrf']=bin2hex(random_bytes(32)); }
}
