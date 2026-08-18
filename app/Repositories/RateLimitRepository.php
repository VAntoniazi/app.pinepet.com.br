<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\Database;

final class RateLimitRepository
{
    public function __construct(private readonly Database$database){}
    public function hit(string$key,int$windowSeconds,int$limit):array
    {
        $s=$this->database->pdo()->prepare("WITH config AS(SELECT CAST(:window AS integer) AS seconds),upsert AS(INSERT INTO sistema_api_rate_limits(chave_hash,inicio_janela,contador,expira_em) SELECT :key,to_timestamp(floor(extract(epoch FROM NOW())/seconds)*seconds),1,NOW()+(seconds*INTERVAL '2 seconds') FROM config ON CONFLICT(chave_hash,inicio_janela) DO UPDATE SET contador=sistema_api_rate_limits.contador+1 RETURNING contador,inicio_janela) SELECT contador,extract(epoch FROM (inicio_janela+(config.seconds*INTERVAL '1 second')-NOW()))::integer AS retry_after FROM upsert CROSS JOIN config");
        $s->execute(['key'=>$key,'window'=>$windowSeconds]);$row=$s->fetch();return['allowed'=>(int)$row['contador']<=$limit,'remaining'=>max(0,$limit-(int)$row['contador']),'retry_after'=>max(1,(int)$row['retry_after'])];
    }
}
