<?php
declare(strict_types=1);
namespace App\Core;

final class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $apiCode,
        string $message,
        public readonly array $details=[],
    ){parent::__construct($message);}
}
