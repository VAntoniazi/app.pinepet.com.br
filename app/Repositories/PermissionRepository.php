<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\Database;

final class PermissionRepository
{
    public function __construct(private readonly Database $database){}
    public function activeMembership(int$userId,int$businessId):bool
    {
        $s=$this->database->pdo()->prepare("SELECT 1 FROM acesso_usuarios u JOIN organizacao_estabelecimentos e ON e.id=u.id_estabelecimento AND e.deleted_at IS NULL WHERE u.id=:user AND u.id_estabelecimento=:business AND u.status='ativo' AND u.deleted_at IS NULL AND u.cadastro_completo_em IS NOT NULL LIMIT 1");
        $s->execute(['user'=>$userId,'business'=>$businessId]);return$s->fetchColumn()!==false;
    }
    public function allowed(int$userId,int$businessId,string$resource,string$action):bool
    {
        $s=$this->database->pdo()->prepare('SELECT permitido FROM sistema_usuarios_permissoes WHERE id_usuario=:user AND id_estabelecimento=:business AND recurso=:resource AND acao=:action LIMIT 1');
        $s->execute(['user'=>$userId,'business'=>$businessId,'resource'=>$resource,'action'=>$action]);
        $value=$s->fetchColumn();return$value===true||$value==='t'||$value===1||$value==='1';
    }
    public function listForUser(int$userId,int$businessId):array
    {
        $s=$this->database->pdo()->prepare('SELECT recurso,acao,permitido FROM sistema_usuarios_permissoes WHERE id_usuario=:user AND id_estabelecimento=:business ORDER BY recurso,acao');
        $s->execute(['user'=>$userId,'business'=>$businessId]);return$s->fetchAll();
    }
}
