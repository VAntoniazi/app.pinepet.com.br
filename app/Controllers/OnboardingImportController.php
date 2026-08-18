<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\OnboardingImportRepository;
use App\Repositories\OnboardingRepository;
use App\Services\OnboardingImportService;

final class OnboardingImportController
{
    public function __construct(private readonly OnboardingImportRepository$imports,private readonly OnboardingRepository$onboarding,private readonly OnboardingImportService$service){}
    public function prepare(Request$r):never{$this->run(function(array$u)use($r){$type=$this->service->type($r->input('tipo'));$mapping=$this->service->mapping($type,$r->input('mapeamento'));$total=filter_var($r->input('total_registros'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);$hash=strtolower(trim((string)$r->input('arquivo_hash')));$file=mb_substr(trim((string)$r->input('arquivo_nome')),0,255);if($total===false||preg_match('/^[a-f0-9]{64}$/',$hash)!==1||$file==='')throw new \RuntimeException('Metadados do arquivo invalidos.');$created=$this->imports->prepare(bin2hex(random_bytes(24)),(int)$u['user_id'],(int)$u['business_id'],$type,$file,$hash,$mapping,(int)$total);return['id'=>$created['id'],'status'=>$created['status'],'lote_tamanho'=>100];});}
    public function batch(Request$r):never{$this->run(function(array$u)use($r){$id=$this->id($r->input('id'));$batch=filter_var($r->input('numero_lote'),FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);$key=trim((string)$r->header('Idempotency-Key',''));if($batch===false||preg_match('/^[A-Za-z0-9._:-]{16,128}$/',$key)!==1)throw new \RuntimeException('Identificacao do lote invalida.');$job=$this->imports->job($id,(int)$u['user_id'],(int)$u['business_id']);if(!$job)throw new \RuntimeException('Importacao nao encontrada.');$mapping=json_decode((string)$job['mapeamento'],true,16,JSON_THROW_ON_ERROR);$rows=$this->service->rows((string)$job['tipo'],$mapping,$r->input('linhas'));$hash=hash('sha256',json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));return$this->imports->processBatch($id,(int)$u['user_id'],(int)$u['business_id'],(int)$batch,$key,$hash,$rows,fn($pdo,$type,$clean)=>$this->service->import($pdo,(int)$u['business_id'],$type,$clean));});}
    public function finish(Request$r):never{$this->run(fn(array$u)=>$this->imports->finish($this->id($r->input('id')),(int)$u['user_id'],(int)$u['business_id']));}
    private function run(callable$callback):never{try{$u=Auth::requireUser();if(!empty($u['profile_complete']))throw new \RuntimeException('Onboarding ja concluido.');$tenant=filter_var($_GET['id_estabelecimento']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($tenant===false||(int)$tenant!==(int)$u['business_id']){Response::json(['ok'=>false,'message'=>'Estabelecimento invalido.'],403);}if(!Csrf::validate($_SERVER['HTTP_X_CSRF_TOKEN']??null))Response::json(['ok'=>false,'message'=>'Sessao de formulario expirada.'],419);$state=$this->onboarding->state((int)$u['user_id'],(int)$u['business_id']);if((int)$state['etapa']!==6)throw new \RuntimeException('Etapa de onboarding invalida.');Response::json(['ok'=>true,'data'=>$callback($u)]);}catch(\Throwable$e){error_log('Onboarding import: '.$e->getMessage());Response::json(['ok'=>false,'message'=>$e instanceof \RuntimeException?$e->getMessage():'Nao foi possivel processar a importacao.'],422);}}
    private function id(mixed$v):string{$v=trim(is_scalar($v)?(string)$v:'');if(preg_match('/^[a-f0-9]{48}$/',$v)!==1)throw new \RuntimeException('Importacao invalida.');return$v;}
}
