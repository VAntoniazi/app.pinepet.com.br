<?php
declare(strict_types=1);
namespace App\Core;

final class Request
{
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $server,
    ) {}

    public static function capture(): self
    {
        $requestPath=(string)parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH);$maxBody=$requestPath==='/primeiro-acesso'?6291456:(str_starts_with($requestPath,'/api/v1/onboarding/importacoes/')?524288:65536);
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBody) throw new HttpException(413,'API-413-PAYLOAD-TOO-LARGE','A requisição excede o limite permitido.');
        $path=rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH));
        $path='/'.trim($path,'/');
        $body=$_POST;
        if($body===[]&&str_contains(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json')){
            $raw=(string)file_get_contents('php://input',false,null,0,65537);
            $decoded=json_decode($raw,true);
            if($raw!==''&&!is_array($decoded)) throw new HttpException(400,'API-400-INVALID-JSON','O JSON enviado é inválido.');
            if(is_array($decoded)) $body=$decoded;
        }
        return new self(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET')),$path==='//'?'/':$path,$_GET,$body,$_SERVER);
    }
    public function method(): string{return $this->method;}
    public function path(): string{return $this->path;}
    public function isApi(): bool{return $this->path==='/api'||str_starts_with($this->path,'/api/');}
    public function input(string $key,mixed $default=null): mixed{return $this->body[$key]??$default;}
    public function query(string $key,mixed $default=null): mixed{return $this->query[$key]??$default;}
    public function header(string $name,mixed $default=null): mixed{$key='HTTP_'.strtoupper(str_replace('-','_',$name));return $this->server[$key]??$default;}
    public function ip(): string
    {
        $remote=(string)($this->server['REMOTE_ADDR']??'');
        if(env('TRUST_PROXY_HEADERS',false)===true&&$this->trusted($remote)){
            foreach([$this->server['HTTP_CF_CONNECTING_IP']??'',$this->server['HTTP_X_FORWARDED_FOR']??'']as$candidate)
                foreach(explode(',',$candidate)as$ip)if(filter_var(trim($ip),FILTER_VALIDATE_IP))return trim($ip);
        }
        return filter_var($remote,FILTER_VALIDATE_IP)?$remote:'0.0.0.0';
    }
    public function userAgent(): string{return mb_substr((string)($this->server['HTTP_USER_AGENT']??''),0,500);}
    private function trusted(string $ip): bool
    {
        foreach(array_filter(array_map('trim',explode(',',(string)env('TRUSTED_PROXY_CIDRS',''))))as$cidr){
            [$net,$bits]=array_pad(explode('/',$cidr,2),2,null);$a=inet_pton($ip);$n=inet_pton($net);
            if($a===false||$n===false||strlen($a)!==strlen($n))continue;$bits=$bits===null?strlen($a)*8:(int)$bits;
            if($bits<0||$bits>strlen($a)*8)continue;$bytes=intdiv($bits,8);$rem=$bits%8;
            if(substr($a,0,$bytes)!==substr($n,0,$bytes))continue;
            if($rem===0||((ord($a[$bytes])&(0xff<<(8-$rem)))===(ord($n[$bytes])&(0xff<<(8-$rem)))))return true;
        }
        return false;
    }
}
