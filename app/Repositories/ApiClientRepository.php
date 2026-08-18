<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\Database;

final class ApiClientRepository
{
    public function __construct(private readonly Database$database){}
    public function active(string$clientId,int$businessId):?array
    {
        $s=$this->database->pdo()->prepare('SELECT client_id,id_estabelecimento,scopes,segredo_verificador,chave_assinatura_cifrada,segredo_versao FROM sistema_api_clientes WHERE client_id=:client AND id_estabelecimento=:business AND ativo=TRUE AND (expira_em IS NULL OR expira_em>NOW()) LIMIT 1');
        $s->execute(['client'=>$clientId,'business'=>$businessId]);$row=$s->fetch();return is_array($row)?$row:null;
    }
    public function storeCredentials(string$clientId,int$businessId,string$verifier,string$encryptedKey):bool
    {
        $s=$this->database->pdo()->prepare('UPDATE sistema_api_clientes SET segredo_verificador=:verifier,chave_assinatura_cifrada=:encrypted,segredo_versao=segredo_versao+1,atualizado_em=NOW() WHERE client_id=:client AND id_estabelecimento=:business AND ativo=TRUE');$s->execute(['verifier'=>$verifier,'encrypted'=>$encryptedKey,'client'=>$clientId,'business'=>$businessId]);return$s->rowCount()===1;
    }
}
