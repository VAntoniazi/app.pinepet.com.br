<?php
declare(strict_types=1);
namespace App\Core;
final class Response
{
    public static function redirect(string $path, int $status=303): never { if (!str_starts_with($path,'/') || str_starts_with($path,'//')) throw new \InvalidArgumentException('Redirecionamento inválido.'); header('Location: '.$path,true,$status); exit; }
    public static function abort(int $status): never { http_response_code($status); echo View::render('errors/generic',['status'=>$status]); exit; }
}
