<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\HttpException;
use App\Repositories\RateLimitRepository;

final class ApiRateLimiter
{
    public function __construct(private readonly RateLimitRepository$limits,private readonly KeyDerivationService$keys){}
    public function enforce(string$actor,int$businessId):void
    {
        $limit=max(10,min(10000,(int)env('API_RATE_LIMIT_PER_MINUTE',120)));$phaseKey=$this->keys->derive($this->key(),'rate-limit-phase-120',(string)$businessId);$key=hash_hmac('sha256',$actor.':'.$businessId,$phaseKey);$result=$this->limits->hit($key,60,$limit);
        if(!$result['allowed'])throw new HttpException(429,'API-429-RATE-LIMITED','Limite temporário de requisições atingido.',['retry_after'=>$result['retry_after']]);
    }
    private function key():string{$key=(string)env('API_RATE_LIMIT_HASH_KEY','');if(strlen($key)<32)throw new \RuntimeException('API_RATE_LIMIT_HASH_KEY deve possuir ao menos 32 caracteres.');return$key;}
}
