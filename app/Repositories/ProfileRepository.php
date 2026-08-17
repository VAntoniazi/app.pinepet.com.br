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
            [$firstName,$lastName]=$this->splitName($data['name']);
            $pdo->prepare(<<<'SQL'
                INSERT INTO cadastro_profissionais
                    (id_estabelecimento,nome,sobrenome,cpf,data_nascimento,email,telefone,whatsapp_com_ddd,cargo,especialidade,ativo)
                SELECT :business,:first_name,:last_name,:cpf,:birth,u.email,u.numero_telefone_ddd,u.numero_telefone_ddd,:role,:specialty,TRUE
                  FROM cadastro_usuarios u
                 WHERE u.id=:user
                   AND NOT EXISTS (
                       SELECT 1 FROM cadastro_profissionais p
                        WHERE p.id_estabelecimento=:business_check AND p.cpf=:cpf_check
                   )
            SQL)->execute([
                'business'=>$businessId,'first_name'=>$firstName,'last_name'=>$lastName,'cpf'=>$data['cpf'],
                'birth'=>$data['birth_date'],'role'=>$data['role'],'specialty'=>$data['specialty']!==''?$data['specialty']:null,
                'user'=>$userId,'business_check'=>$businessId,'cpf_check'=>$data['cpf'],
            ]);
            $flow=$pdo->prepare("SELECT id FROM atendimento_fluxos WHERE id_estabelecimento=:business AND nome='Atendimento padrão' ORDER BY id LIMIT 1");
            $flow->execute(['business'=>$businessId]); $flowId=$flow->fetchColumn();
            if($flowId===false){
                $create=$pdo->prepare(<<<'SQL'
                    INSERT INTO atendimento_fluxos(id_estabelecimento,nome,descricao,etapas,ativo)
                    VALUES(:business,'Atendimento padrão','Fluxo inicial criado no onboarding',
                           '[{"codigo":"CHECKIN","nome":"Check-in","ordem":1},{"codigo":"ATENDIMENTO","nome":"Em atendimento","ordem":2},{"codigo":"FINALIZACAO","nome":"Finalização","ordem":3}]'::jsonb,TRUE)
                    RETURNING id
                SQL);
                $create->execute(['business'=>$businessId]); $flowId=$create->fetchColumn();
            }
            $pdo->prepare(<<<'SQL'
                INSERT INTO atendimento_fluxos_preferencias
                    (id_estabelecimento,id_fluxo_padrao,avancar_automaticamente,permitir_pular_etapas,notificar_cliente)
                VALUES(:business,:flow,:auto_advance,:allow_skip,:notify_client)
                ON CONFLICT(id_estabelecimento) DO UPDATE SET
                    id_fluxo_padrao=EXCLUDED.id_fluxo_padrao,
                    avancar_automaticamente=EXCLUDED.avancar_automaticamente,
                    permitir_pular_etapas=EXCLUDED.permitir_pular_etapas,
                    notificar_cliente=EXCLUDED.notificar_cliente
            SQL)->execute(['business'=>$businessId,'flow'=>(int)$flowId,'auto_advance'=>$data['auto_advance']?'true':'false','allow_skip'=>$data['allow_skip']?'true':'false','notify_client'=>$data['notify_client']?'true':'false']);
            $pdo->prepare("INSERT INTO sistema_autenticacao_eventos(id_usuario,id_estabelecimento,evento,metadata,created_at) VALUES(:user_a,:business_a,'cadastro_complementado','{}'::jsonb,NOW()),(:user_b,:business_b,'primeiro_acesso','{}'::jsonb,NOW())")->execute(['user_a'=>$userId,'business_a'=>$businessId,'user_b'=>$userId,'business_b'=>$businessId]);
            $pdo->prepare("INSERT INTO cadastro_funil_eventos(cadastro_id,evento,etapa,metadata,created_at) SELECT id,'primeiro_acesso',3,'{}'::jsonb,NOW() FROM cadastro_temporarios WHERE id_usuario=:user ORDER BY id DESC LIMIT 1")->execute(['user'=>$userId]);
        });
    }

    /** @return array{0:string,1:?string} */
    private function splitName(string $fullName): array
    {
        $parts=preg_split('/\s+/u',trim($fullName),2);
        return [(string)($parts[0]??''),isset($parts[1])&&$parts[1]!==''?$parts[1]:null];
    }
}
