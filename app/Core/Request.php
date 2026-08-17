<?php
declare(strict_types=1);
namespace App\Core;

final class Request
{
    private function __construct(private string $method, private string $path, private array $body, private array $server) {}
    public static function capture(): self
    {
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) Response::abort(413);
        $path = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
        $path = '/' . trim($path, '/');
        return new self(strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')), $path === '//' ? '/' : $path, $_POST, $_SERVER);
    }
    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function input(string $key, mixed $default=null): mixed { return $this->body[$key] ?? $default; }
    public function ip(): string
    {
        $remote = (string)($this->server['REMOTE_ADDR'] ?? '');
        if (env('TRUST_PROXY_HEADERS', false) === true && $this->trusted($remote)) {
            foreach ([$this->server['HTTP_CF_CONNECTING_IP'] ?? '', $this->server['HTTP_X_FORWARDED_FOR'] ?? ''] as $candidate)
                foreach (explode(',', $candidate) as $ip) if (filter_var(trim($ip), FILTER_VALIDATE_IP)) return trim($ip);
        }
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }
    public function userAgent(): string { return mb_substr((string)($this->server['HTTP_USER_AGENT'] ?? ''), 0, 500); }
    private function trusted(string $ip): bool
    {
        foreach (array_filter(array_map('trim', explode(',', (string)env('TRUSTED_PROXY_CIDRS','')))) as $cidr) {
            [$net,$bits] = array_pad(explode('/',$cidr,2),2,null); $a=inet_pton($ip); $n=inet_pton($net);
            if ($a===false || $n===false || strlen($a)!==strlen($n)) continue; $bits=$bits===null?strlen($a)*8:(int)$bits;
            $bytes=intdiv($bits,8); $rem=$bits%8; if (substr($a,0,$bytes)!==substr($n,0,$bytes)) continue;
            if ($rem===0 || ((ord($a[$bytes]) & (0xff << (8-$rem))) === (ord($n[$bytes]) & (0xff << (8-$rem))))) return true;
        }
        return false;
    }
}
