<?php
declare(strict_types=1);
namespace App\Core;

final class Router
{
    private array $routes=[];
    public function get(string $path,callable $handler):void{$this->add('GET',$path,$handler);}
    public function post(string $path,callable $handler):void{$this->add('POST',$path,$handler);}
    private function add(string $method,string $path,callable $handler):void{$this->routes[]=[$method,'/'.trim($path,'/'),$handler];}
    public function dispatch(Request $request):void
    {
        try{
            $pathExists=false;
            foreach($this->routes as[$method,$path,$handler]){
                if($path!==$request->path())continue;$pathExists=true;if($method!==$request->method())continue;
                $result=$handler($request);if(is_string($result))echo$result;return;
            }
            throw new HttpException($pathExists?405:404,$pathExists?'API-405-METHOD-NOT-ALLOWED':'API-404-NOT-FOUND',$pathExists?'Método não permitido para este endpoint.':'O recurso solicitado não foi encontrado.');
        }catch(HttpException$e){
            if($request->isApi())ApiResponse::error($e->apiCode,$e->getMessage(),$e->status,$e->details);
            Response::abort($e->status,$e->getMessage());
        }catch(\Throwable$e){
            $requestId=bin2hex(random_bytes(8));error_log(sprintf('[%s] request=%s %s %s | %s',date('c'),$requestId,$request->method(),preg_replace('/[\x00-\x1F\x7F]/',' ',$request->path()),$e));
            if($request->isApi())ApiResponse::error('API-500-INTERNAL-ERROR','Não foi possível processar a requisição.',500,[], $requestId);
            Response::abort(500);
        }
    }
}
