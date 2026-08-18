<?php
declare(strict_types=1);
namespace App\Core;

final class Response
{
    public static function redirect(string $path,int $status=303): never
    {
        if(!str_starts_with($path,'/')||str_starts_with($path,'//'))throw new \InvalidArgumentException('Redirecionamento inválido.');
        header('Location: '.$path,true,$status);exit;
    }
    public static function json(array $payload,int $status=200): never
    {
        http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
        echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;
    }
    public static function abort(int $status,string $message=''): never
    {
        http_response_code($status);echo View::render('errors/generic',['status'=>$status,'customMessage'=>$message]);exit;
    }
}
