<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\Database;

final class SessionRepository
{
    public function __construct(private readonly Database$database){}
    public function create(string$hash,string$binding,int$userId,int$businessId,string$uaHash,string$ipHash,int$idle,int$absolute):void
    {
        $s=$this->database->pdo()->prepare("INSERT INTO sistema_sessoes(session_hash,credential_binding_hash,id_usuario,id_estabelecimento,user_agent_hash,ip_hash,emitida_em,ultima_atividade_em,expira_inatividade_em,expira_absoluta_em) VALUES(:hash,:binding,:user,:business,:ua,:ip,NOW(),NOW(),NOW()+(CAST(:idle AS integer)*INTERVAL '1 second'),NOW()+(CAST(:absolute AS integer)*INTERVAL '1 second')) ON CONFLICT(session_hash)DO UPDATE SET credential_binding_hash=EXCLUDED.credential_binding_hash,user_agent_hash=EXCLUDED.user_agent_hash,ip_hash=EXCLUDED.ip_hash,revogada_em=NULL,ultima_atividade_em=NOW(),expira_inatividade_em=EXCLUDED.expira_inatividade_em,expira_absoluta_em=EXCLUDED.expira_absoluta_em");
        $s->execute(['hash'=>$hash,'binding'=>$binding,'user'=>$userId,'business'=>$businessId,'ua'=>$uaHash,'ip'=>$ipHash,'idle'=>$idle,'absolute'=>$absolute]);
    }
    public function findValid(string$hash,int$userId,int$businessId):?array
    {
        $s=$this->database->pdo()->prepare("SELECT s.id,s.credential_binding_hash,s.user_agent_hash,s.ultima_atividade_em,u.senha AS password_hash FROM sistema_sessoes s JOIN acesso_usuarios u ON u.id=s.id_usuario AND u.id_estabelecimento=s.id_estabelecimento AND u.deleted_at IS NULL AND u.status='ativo' WHERE s.session_hash=:hash AND s.id_usuario=:user AND s.id_estabelecimento=:business AND s.revogada_em IS NULL AND s.expira_inatividade_em>NOW() AND s.expira_absoluta_em>NOW() LIMIT 1");
        $s->execute(['hash'=>$hash,'user'=>$userId,'business'=>$businessId]);$row=$s->fetch();return is_array($row)?$row:null;
    }
    public function touch(int$id,int$idle):void{$this->database->pdo()->prepare("UPDATE sistema_sessoes SET ultima_atividade_em=NOW(),expira_inatividade_em=LEAST(NOW()+(CAST(:idle AS integer)*INTERVAL '1 second'),expira_absoluta_em) WHERE id=:id AND revogada_em IS NULL")->execute(['idle'=>$idle,'id'=>$id]);}
    public function revoke(string$hash):void{$this->database->pdo()->prepare('UPDATE sistema_sessoes SET revogada_em=COALESCE(revogada_em,NOW()) WHERE session_hash=:hash')->execute(['hash'=>$hash]);}
}
