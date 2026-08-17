<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ProfileRepository
{
    public function __construct(private readonly Database $database) {}
    public function data(int $userId): ?array
    {
        $s=$this->database->pdo()->prepare('SELECT u.nome_completo,u.data_nascimento,u.sexo_biologico,u.cpf,u.numero_telefone_ddd,u.email,u.cadastro_completo_em,e.nome_fantasia,e.cnpj FROM cadastro_usuarios u JOIN cadastro_estabelecimentos e ON e.id=u.id_estabelecimento AND e.deleted_at IS NULL WHERE u.id=:id AND u.deleted_at IS NULL LIMIT 1');
        $s->execute(['id'=>$userId]); $row=$s->fetch(); return is_array($row)?$row:null;
    }
    public function complete(int $userId,int $businessId,array $data): void
    {
        $this->database->transaction(function(PDO $pdo) use($userId,$businessId,$data): void {
            $s=$pdo->prepare('UPDATE cadastro_usuarios SET nome_completo=:name,data_nascimento=:birth,sexo_biologico=:sex,cpf=:cpf,cadastro_completo_em=NOW(),updated_at=NOW(),version_lock=version_lock+1 WHERE id=:id AND id_estabelecimento=:business AND deleted_at IS NULL AND cadastro_completo_em IS NULL');
            $s->execute(['name'=>$data['name'],'birth'=>$data['birth_date'],'sex'=>$data['sex'],'cpf'=>$data['cpf'],'id'=>$userId,'business'=>$businessId]);
            if($s->rowCount()!==1) throw new \RuntimeException('Cadastro já concluído ou vínculo inválido.');
            $pdo->prepare('UPDATE cadastro_estabelecimentos SET nome_fantasia=:trade_name,cnpj=:cnpj,updated_at=NOW() WHERE id=:id AND deleted_at IS NULL')->execute(['trade_name'=>$data['trade_name'],'cnpj'=>$data['cnpj']!==''?$data['cnpj']:null,'id'=>$businessId]);
            $pdo->prepare("INSERT INTO sistema_autenticacao_eventos(id_usuario,id_estabelecimento,evento,metadata,created_at) VALUES(:user,:business,'cadastro_complementado','{}'::jsonb,NOW())")->execute(['user'=>$userId,'business'=>$businessId]);
        });
    }
}
