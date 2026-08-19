<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;
use PDO;

final class SettingsRepository
{
    public function __construct(private readonly Database $database){}
    public function data(int$userId,int$businessId):array
    {
        $pdo=$this->database->pdo();$s=$pdo->prepare('SELECT u.nome_completo,u.email,u.numero_telefone_ddd,e.nome_fantasia,e.cnpj,COALESCE(oc.aplica_vacinas,FALSE)aplica_vacinas,COALESCE(fc.emitir_nota,FALSE)emitir_nota FROM acesso_usuarios u JOIN organizacao_estabelecimentos e ON e.id=u.id_estabelecimento LEFT JOIN organizacao_configuracoes oc ON oc.id_estabelecimento=e.id LEFT JOIN fiscal_configuracoes fc ON fc.id_estabelecimento=e.id WHERE u.id=:user AND e.id=:business AND u.deleted_at IS NULL AND e.deleted_at IS NULL');$s->execute(['user'=>$userId,'business'=>$businessId]);$base=$s->fetch();if(!is_array($base))throw new \RuntimeException('Configurações não encontradas.');
        $s=$pdo->prepare('SELECT nome,cpf,numero_conselho,uf_conselho,validade_conselho,email,telefone FROM saude_responsaveis_tecnicos WHERE id_estabelecimento=:business AND ativo=TRUE');$s->execute(['business'=>$businessId]);$base['responsavel']=$s->fetch()?:[];
        $s=$pdo->prepare('SELECT m.codigo FROM financeiro_estabelecimentos_metodos em JOIN financeiro_metodos_pagamento m ON m.id=em.id_metodo WHERE em.id_estabelecimento=:business AND em.ativo=TRUE');$s->execute(['business'=>$businessId]);$base['pagamentos']=array_column($s->fetchAll(),'codigo');
        $s=$pdo->prepare('SELECT dia_semana,fechado,abre_as,fecha_as FROM organizacao_horarios WHERE id_estabelecimento=:business ORDER BY dia_semana');$s->execute(['business'=>$businessId]);$base['horarios']=$s->fetchAll();return$base;
    }
    public function paymentMethods():array{return$this->database->pdo()->query('SELECT codigo,nome FROM financeiro_metodos_pagamento WHERE ativo=TRUE ORDER BY ordem,nome')->fetchAll();}
    public function save(int$userId,int$businessId,array$d):void
    {
        $this->database->transaction(function(PDO$pdo)use($userId,$businessId,$d):void{
            $pdo->prepare('UPDATE acesso_usuarios SET nome_completo=:name,email=:email,numero_telefone_ddd=:phone,updated_at=NOW(),version_lock=version_lock+1 WHERE id=:user AND id_estabelecimento=:business AND deleted_at IS NULL')->execute(['name'=>$d['name'],'email'=>$d['email'],'phone'=>$d['phone'],'user'=>$userId,'business'=>$businessId]);
            $pdo->prepare('UPDATE organizacao_estabelecimentos SET nome_fantasia=:trade,cnpj=:cnpj,updated_at=NOW() WHERE id=:business AND deleted_at IS NULL')->execute(['trade'=>$d['trade'],'cnpj'=>$d['cnpj']?:null,'business'=>$businessId]);
            $pdo->prepare('INSERT INTO organizacao_configuracoes(id_estabelecimento,aplica_vacinas)VALUES(:business,:value)ON CONFLICT(id_estabelecimento)DO UPDATE SET aplica_vacinas=EXCLUDED.aplica_vacinas')->execute(['business'=>$businessId,'value'=>$d['vaccines']?'true':'false']);
            if($d['vaccines'])$pdo->prepare('INSERT INTO saude_responsaveis_tecnicos(id_estabelecimento,nome,cpf,conselho,numero_conselho,uf_conselho,validade_conselho,email,telefone)VALUES(:business,:name,:cpf,\'CRMV\',:number,:uf,:validity,:email,:phone)ON CONFLICT(id_estabelecimento)DO UPDATE SET nome=EXCLUDED.nome,cpf=EXCLUDED.cpf,numero_conselho=EXCLUDED.numero_conselho,uf_conselho=EXCLUDED.uf_conselho,validade_conselho=EXCLUDED.validade_conselho,email=EXCLUDED.email,telefone=EXCLUDED.telefone,ativo=TRUE')->execute(['business'=>$businessId]+$d['rt']);else$pdo->prepare('UPDATE saude_responsaveis_tecnicos SET ativo=FALSE WHERE id_estabelecimento=:business')->execute(['business'=>$businessId]);
            $status=$d['invoice']?'QUER_CONTRATAR':'NAO_APLICAVEL';$pdo->prepare('INSERT INTO fiscal_configuracoes(id_estabelecimento,emitir_nota,status_certificado,oferta_certificado_solicitada)VALUES(:business,:issue,:status,:offer)ON CONFLICT(id_estabelecimento)DO UPDATE SET emitir_nota=EXCLUDED.emitir_nota,status_certificado=CASE WHEN fiscal_configuracoes.status_certificado=\'ENVIADO\' THEN fiscal_configuracoes.status_certificado ELSE EXCLUDED.status_certificado END,oferta_certificado_solicitada=EXCLUDED.oferta_certificado_solicitada')->execute(['business'=>$businessId,'issue'=>$d['invoice']?'true':'false','status'=>$status,'offer'=>$d['invoice']?'true':'false']);
            $pdo->prepare('DELETE FROM financeiro_estabelecimentos_metodos WHERE id_estabelecimento=:business')->execute(['business'=>$businessId]);$pay=$pdo->prepare('INSERT INTO financeiro_estabelecimentos_metodos(id_estabelecimento,id_metodo)SELECT :business,id FROM financeiro_metodos_pagamento WHERE codigo=:code AND ativo=TRUE');foreach($d['payments']as$code)$pay->execute(['business'=>$businessId,'code'=>$code]);
            $hour=$pdo->prepare('INSERT INTO organizacao_horarios(id_estabelecimento,dia_semana,fechado,abre_as,fecha_as)VALUES(:business,:day,:closed,:open,:close)ON CONFLICT(id_estabelecimento,dia_semana)DO UPDATE SET fechado=EXCLUDED.fechado,abre_as=EXCLUDED.abre_as,fecha_as=EXCLUDED.fecha_as');foreach($d['hours']as$day=>$h)$hour->execute(['business'=>$businessId,'day'=>$day,'closed'=>$h['closed']?'true':'false','open'=>$h['closed']?null:$h['open'],'close'=>$h['closed']?null:$h['close']]);
        });
    }
}
