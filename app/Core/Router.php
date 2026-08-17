<?php
declare(strict_types=1);
namespace App\Core;
final class Router
{
    private array $routes=[];
    public function get(string $path,callable $handler): void { $this->routes[]=['GET',$path,$handler]; }
    public function post(string $path,callable $handler): void { $this->routes[]=['POST',$path,$handler]; }
    public function dispatch(Request $request): void
    {
        try { foreach($this->routes as [$method,$path,$handler]) if($method===$request->method() && $path===$request->path()){ $result=$handler($request); if(is_string($result)) echo $result; return; } Response::abort(404); }
        catch(\Throwable $e){ error_log(sprintf('[%s] %s %s | %s',date('c'),$request->method(),preg_replace('/[\x00-\x1F\x7F]/',' ',$request->path()),$e)); Response::abort(500); }
    }
}
