<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AuthRepository
{
    public function __construct(private readonly Database $database) {}
    public function findByEmail(string $email): ?array
    {
        $s=$this->database->pdo()->prepare('SELECT id,public_id,id_estabelecimento,nome_completo,email,senha,status,cadastro_completo_em FROM acesso_usuarios WHERE email=:email AND deleted_at IS NULL LIMIT 1');
        $s->execute(['email'=>$email]); $row=$s->fetch(); return is_array($row)?$row:null;
    }
    public function isBlocked(string $emailHash,string $ipHash,int $limit,int $minutes): bool
    {
        $s=$this->database->pdo()->prepare("SELECT count(*) FROM sistema_login_tentativas WHERE sucesso=false AND created_at > NOW() - (CAST(:minutes AS integer) * INTERVAL '1 minute') AND (email_hash=:email OR ip_hash=:ip)");
        $s->execute(['minutes'=>$minutes,'email'=>$emailHash,'ip'=>$ipHash]); return (int)$s->fetchColumn()>=$limit;
    }
    public function recordAttempt(string $emailHash,string $ipHash,bool $success,string $userAgent): void
    {
        $s=$this->database->pdo()->prepare('INSERT INTO sistema_login_tentativas(email_hash,ip_hash,sucesso,user_agent,created_at) VALUES(:email,:ip,:success,:agent,NOW())');
        $s->execute(['email'=>$emailHash,'ip'=>$ipHash,'success'=>$success?'true':'false','agent'=>$userAgent]);
    }
    public function updatePasswordHash(int $userId,string $passwordHash): void
    {
        $this->database->pdo()->prepare('UPDATE acesso_usuarios SET senha=:password,updated_at=NOW(),version_lock=version_lock+1 WHERE id=:id AND deleted_at IS NULL')->execute(['password'=>$passwordHash,'id'=>$userId]);
    }
    public function activate(string $tokenHash,string $passwordHash): ?array
    {
        return $this->database->transaction(function(PDO $pdo) use($tokenHash,$passwordHash): ?array {
            $s=$pdo->prepare("SELECT t.id AS draft_id,u.id,u.public_id,u.id_estabelecimento,u.nome_completo,u.email,u.cadastro_completo_em FROM onboarding_inscricoes t JOIN acesso_usuarios u ON u.id=t.id_usuario WHERE t.ativacao_token_hash=:hash AND t.ativacao_expira_em>NOW() AND u.deleted_at IS NULL LIMIT 1 FOR UPDATE OF t,u");
            $s->execute(['hash'=>$tokenHash]); $user=$s->fetch(); if(!is_array($user)) return null;
            $pdo->prepare("UPDATE acesso_usuarios SET senha=:password,status='ativo',updated_at=NOW(),version_lock=version_lock+1 WHERE id=:id")->execute(['password'=>$passwordHash,'id'=>$user['id']]);
            $pdo->prepare("UPDATE onboarding_inscricoes SET ativacao_token_hash=NULL,ativacao_expira_em=NULL,status='senha_definida',updated_at=NOW() WHERE id=:id")->execute(['id'=>$user['draft_id']]);
            $pdo->prepare("INSERT INTO onboarding_eventos(cadastro_id,evento,etapa,metadata,created_at) VALUES(:draft_id,'senha_definida',3,'{}'::jsonb,NOW())")->execute(['draft_id'=>$user['draft_id']]);
            unset($user['draft_id']);$user['senha']=$passwordHash;return $user;
        });
    }
}
