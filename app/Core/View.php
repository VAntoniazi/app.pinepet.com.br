<?php
declare(strict_types=1);
namespace App\Core;
final class View
{
    public static function render(string $view,array $data=[]): string { extract($data,EXTR_SKIP); ob_start(); require BASE_PATH.'/resources/views/'.$view.'.php'; $content=(string)ob_get_clean(); ob_start(); require BASE_PATH.'/resources/views/layouts/app.php'; return (string)ob_get_clean(); }
}
