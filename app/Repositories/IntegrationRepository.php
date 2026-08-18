<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\Database;

final class IntegrationRepository
{
    public function __construct(private readonly Database$database){}
    public function active(string$code):?array{$s=$this->database->pdo()->prepare('SELECT codigo,provedor,ambiente,cliente_id,cliente_segredo_cifrado,versao,configuracao FROM sistema_integracoes WHERE codigo=:code AND ativo=TRUE LIMIT 1');$s->execute(['code'=>$code]);$row=$s->fetch();return is_array($row)?$row:null;}
    public function configure(string$code,string$provider,string$environment,string$clientId,string$encryptedSecret):void{$s=$this->database->pdo()->prepare("INSERT INTO sistema_integracoes(codigo,provedor,ambiente,cliente_id,cliente_segredo_cifrado)VALUES(:code,:provider,:environment,:client,:secret)ON CONFLICT(codigo)DO UPDATE SET provedor=EXCLUDED.provedor,ambiente=EXCLUDED.ambiente,cliente_id=EXCLUDED.cliente_id,cliente_segredo_cifrado=EXCLUDED.cliente_segredo_cifrado,ativo=TRUE,versao=sistema_integracoes.versao+1");$s->execute(['code'=>$code,'provider'=>$provider,'environment'=>$environment,'client'=>$clientId,'secret'=>$encryptedSecret]);}
}
