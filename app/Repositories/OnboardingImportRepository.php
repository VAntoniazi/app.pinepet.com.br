<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\Database;
use PDO;

final class OnboardingImportRepository
{
    public function __construct(private readonly Database $database){}
    public function prepare(string$id,int$userId,int$businessId,string$type,string$file,string$hash,array$mapping,int$total):array
    {
        $json=json_encode($mapping,JSON_THROW_ON_ERROR);$importKey=hash('sha256',$type.'|'.$hash.'|'.$json);$s=$this->database->pdo()->prepare('INSERT INTO onboarding_importacoes(id,id_usuario,id_estabelecimento,tipo,arquivo_nome,arquivo_hash,chave_importacao,mapeamento,total_registros)VALUES(:id,:user,:business,:type,:file,:hash,:import_key,CAST(:mapping AS jsonb),:total)ON CONFLICT(id_usuario,id_estabelecimento,chave_importacao)DO UPDATE SET atualizado_em=NOW() RETURNING id,status');
        $s->execute(['id'=>$id,'user'=>$userId,'business'=>$businessId,'type'=>$type,'file'=>$file,'hash'=>$hash,'import_key'=>$importKey,'mapping'=>$json,'total'=>$total]);return$s->fetch();
    }
    public function job(string$id,int$userId,int$businessId,bool$lock=false):?array
    {
        $s=$this->database->pdo()->prepare('SELECT * FROM onboarding_importacoes WHERE id=:id AND id_usuario=:user AND id_estabelecimento=:business'.($lock?' FOR UPDATE':''));$s->execute(['id'=>$id,'user'=>$userId,'business'=>$businessId]);$row=$s->fetch();return is_array($row)?$row:null;
    }
    public function processBatch(string$id,int$userId,int$businessId,int$batch,string$key,string$payloadHash,array$rows,callable$importer):array
    {
        return $this->database->transaction(function(PDO$pdo)use($id,$userId,$businessId,$batch,$key,$payloadHash,$rows,$importer):array{
            $job=$this->job($id,$userId,$businessId,true);if(!$job||!in_array($job['status'],['PREPARADA','PROCESSANDO'],true))throw new \RuntimeException('Importacao indisponivel.');
            $existing=$pdo->prepare('SELECT payload_hash,resultado FROM onboarding_importacao_lotes WHERE id_importacao=:id AND numero_lote=:batch');$existing->execute(['id'=>$id,'batch'=>$batch]);$done=$existing->fetch();if(is_array($done)){if(!hash_equals((string)$done['payload_hash'],$payloadHash))throw new \RuntimeException('Lote repetido com conteudo diferente.');return json_decode((string)$done['resultado'],true,16,JSON_THROW_ON_ERROR);}
            $result=$importer($pdo,(string)$job['tipo'],$rows);$json=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $insert=$pdo->prepare('INSERT INTO onboarding_importacao_lotes(id_importacao,id_estabelecimento,numero_lote,idempotency_key,payload_hash,quantidade,importados,rejeitados,resultado)VALUES(:id,:business,:batch,:key,:hash,:quantity,:imported,:rejected,CAST(:result AS jsonb))');$insert->execute(['id'=>$id,'business'=>$businessId,'batch'=>$batch,'key'=>$key,'hash'=>$payloadHash,'quantity'=>count($rows),'imported'=>$result['importados'],'rejected'=>$result['rejeitados'],'result'=>$json]);
            $pdo->prepare("UPDATE onboarding_importacoes SET status='PROCESSANDO',total_processados=total_processados+:quantity,total_importados=total_importados+:imported,total_rejeitados=total_rejeitados+:rejected WHERE id=:id")->execute(['quantity'=>count($rows),'imported'=>$result['importados'],'rejected'=>$result['rejeitados'],'id'=>$id]);return$result;
        });
    }
    public function finish(string$id,int$userId,int$businessId):array
    {
        return$this->database->transaction(function(PDO$pdo)use($id,$userId,$businessId):array{$job=$this->job($id,$userId,$businessId,true);if(!$job)throw new \RuntimeException('Importacao nao encontrada.');if((int)$job['total_processados']!==(int)$job['total_registros'])throw new \RuntimeException('Ainda existem registros pendentes.');$pdo->prepare("UPDATE onboarding_importacoes SET status='CONCLUIDA',concluido_em=NOW() WHERE id=:id")->execute(['id'=>$id]);return['processados'=>(int)$job['total_processados'],'importados'=>(int)$job['total_importados'],'rejeitados'=>(int)$job['total_rejeitados']];});
    }
    public function assertCompleted(array$ids,int$userId,int$businessId):void
    {
        if($ids===[])throw new \RuntimeException('Conclua ao menos uma importacao ou escolha continuar sem migrar.');$ids=array_values(array_unique($ids));foreach($ids as$id){if(!is_string($id)||preg_match('/^[a-f0-9]{48}$/',$id)!==1)throw new \RuntimeException('Referencia de importacao invalida.');$job=$this->job($id,$userId,$businessId);if(!$job||$job['status']!=='CONCLUIDA')throw new \RuntimeException('Existe uma importacao ainda nao concluida.');}
    }
}
