<?php
declare(strict_types=1);

use App\Core\Database;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap/app.php';
$database = new Database(require BASE_PATH . '/config/database.php');
$files = glob(BASE_PATH . '/database/*.sql') ?: [];
sort($files, SORT_STRING);
foreach ($files as $file) {
    fwrite(STDOUT, 'Aplicando ' . basename($file) . "...\n");
    $sql = file_get_contents($file);
    if (!is_string($sql) || trim($sql) === '') throw new RuntimeException('Migration vazia: ' . basename($file));
    $database->pdo()->exec($sql);
}
fwrite(STDOUT, "Migrations aplicadas com sucesso.\n");
