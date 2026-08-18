<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\HttpException;

final class JwtService
{
    public function issue(string$clientId,int$businessId,array$scopes,string$signingKey,int$credentialVersion,int$ttl=900):string
    {
        if(strlen($signingKey)!==32)throw new \InvalidArgumentException('Chave JWT derivada invalida.');$now=time();$ttl=max(60,min($ttl,max(60,min(3600,(int)env('API_JWT_MAX_TTL',900)))));
        $header=['alg'=>'HS256','typ'=>'JWT'];$claims=['iss'=>(string)env('API_JWT_ISSUER','pinepet-app'),'aud'=>(string)env('API_JWT_AUDIENCE','pinepet-api'),'sub'=>$clientId,'jti'=>$this->base64Url(random_bytes(24)),'iat'=>$now,'nbf'=>$now,'exp'=>$now+$ttl,'client_id'=>$clientId,'tenant_id'=>$businessId,'credential_version'=>$credentialVersion,'scopes'=>array_values(array_unique($scopes))];
        $encodedHeader=$this->base64Url(json_encode($header,JSON_THROW_ON_ERROR));$encodedClaims=$this->base64Url(json_encode($claims,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$signature=hash_hmac('sha256',$encodedHeader.'.'.$encodedClaims,$signingKey,true);return$encodedHeader.'.'.$encodedClaims.'.'.$this->base64Url($signature);
    }
    public function identity(string$token):array
    {
        $parts=explode('.',$token);if(count($parts)!==3)$this->reject();$claims=$this->decodeJson($parts[1]);
        if(!is_string($claims['client_id']??null)||preg_match('/^[A-Za-z0-9._-]{3,100}$/',$claims['client_id'])!==1||!is_int($claims['tenant_id']??null)||$claims['tenant_id']<1)$this->reject();return['client_id'=>$claims['client_id'],'tenant_id'=>$claims['tenant_id']];
    }
    public function validate(string$token,string$signingKey,int$credentialVersion):array
    {
        $parts=explode('.',$token);if(count($parts)!==3)$this->reject();[$encodedHeader,$encodedPayload,$encodedSignature]=$parts;$header=$this->decodeJson($encodedHeader);$claims=$this->decodeJson($encodedPayload);
        if(($header['alg']??null)!=='HS256'||($header['typ']??null)!=='JWT'||strlen($signingKey)!==32)$this->reject();$expected=hash_hmac('sha256',$encodedHeader.'.'.$encodedPayload,$signingKey,true);if(!hash_equals($expected,$this->decode($encodedSignature)))$this->reject();
        $now=time();$leeway=max(0,min(60,(int)env('API_JWT_LEEWAY',15)));$maxTtl=max(60,min(3600,(int)env('API_JWT_MAX_TTL',900)));
        foreach(['iss','aud','sub','jti','iat','nbf','exp','client_id','tenant_id','credential_version','scopes']as$key)if(!array_key_exists($key,$claims))$this->reject();
        if(!is_string($claims['iss'])||!hash_equals((string)env('API_JWT_ISSUER','pinepet-app'),$claims['iss'])||!is_string($claims['aud'])||!hash_equals((string)env('API_JWT_AUDIENCE','pinepet-api'),$claims['aud']))$this->reject();
        if(!is_string($claims['sub'])||!is_string($claims['client_id'])||$claims['sub']!==$claims['client_id']||preg_match('/^[A-Za-z0-9._-]{3,100}$/',$claims['client_id'])!==1||!is_string($claims['jti'])||preg_match('/^[A-Za-z0-9_-]{16,128}$/',$claims['jti'])!==1)$this->reject();
        if(!is_int($claims['iat'])||!is_int($claims['nbf'])||!is_int($claims['exp'])||!is_int($claims['tenant_id'])||$claims['tenant_id']<1||!is_int($claims['credential_version'])||$claims['credential_version']!==$credentialVersion)$this->reject();
        if($claims['iat']>$now+$leeway||$claims['nbf']>$now+$leeway||$claims['exp']<=$now-$leeway||$claims['exp']-$claims['iat']>$maxTtl||$claims['exp']<=$claims['iat'])throw new HttpException(401,'API-401-TOKEN-EXPIRED','O token de integracao expirou ou ainda nao e valido.');
        if(!is_array($claims['scopes'])||count($claims['scopes'])>100)$this->reject();foreach($claims['scopes']as$scope)if(!is_string($scope)||preg_match('/^[a-z][a-z0-9_]{0,59}\.[a-z][a-z0-9_]{0,29}$/',$scope)!==1)$this->reject();return$claims;
    }
    private function decodeJson(string$value):array{$decoded=json_decode($this->decode($value),true,32,JSON_BIGINT_AS_STRING);if(!is_array($decoded))$this->reject();return$decoded;}
    private function decode(string$value):string{if($value===''||preg_match('/^[A-Za-z0-9_-]+$/',$value)!==1)$this->reject();$decoded=base64_decode(strtr($value,'-_','+/').str_repeat('=',(4-strlen($value)%4)%4),true);if($decoded===false)$this->reject();return$decoded;}
    private function base64Url(string$value):string{return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
    private function reject():never{throw new HttpException(401,'API-401-TOKEN-INVALID','O token de integracao e invalido.');}
}
