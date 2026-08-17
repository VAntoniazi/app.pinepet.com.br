<?php
declare(strict_types=1);
namespace App\Core;

use PDO;
use RuntimeException;

final class Database
{
    private ?PDO $pdo = null;
    public function __construct(private readonly array $config) {}
    public function pdo(): PDO
    {
        if ($this->pdo) return $this->pdo;
        foreach (['host','database','username','password'] as $key) if (($this->config[$key] ?? '') === '') throw new RuntimeException('Configuração do banco incompleta.');
        $schema = (string) $this->config['schema'];
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $schema)) throw new RuntimeException('DB_SCHEMA inválido.');
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;connect_timeout=%d;application_name=pinepet-app', $this->config['host'], $this->config['port'], $this->config['database'], $this->config['sslmode'], $this->config['connect_timeout']);
        $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $this->pdo->exec('SET search_path TO "' . $schema . '", public');
        $this->pdo->exec("SET TIME ZONE 'UTC'");
        return $this->pdo;
    }
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo(); $pdo->beginTransaction();
        try { $result = $callback($pdo); $pdo->commit(); return $result; }
        catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
}
