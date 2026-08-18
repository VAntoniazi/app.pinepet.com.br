<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\OnboardingRepository;
use App\Repositories\OnboardingImportRepository;
use App\Repositories\ProfileRepository;
use App\Services\CertificateStorageService;
use App\Services\CnpjLookupService;

final class OnboardingController
{
    public function __construct(private readonly ProfileRepository$profiles,private readonly OnboardingRepository$onboarding,private readonly OnboardingImportRepository$imports,private readonly CnpjLookupService$cnpj,private readonly CertificateStorageService$certificates){}
    public function show():string
    {
        $user=Auth::requireUser();if(!empty($user['profile_complete']))Response::redirect('/painel',302);$profile=$this->profiles->data((int)$user['user_id']);if(!$profile)Response::abort(403);$state=$this->onboarding->state((int)$user['user_id'],(int)$user['business_id']);
        return View::render('onboarding/index',['title'=>'Configure seu PinePet','profile'=>$profile,'businessId'=>(int)$user['business_id'],'step'=>(int)$state['etapa'],'data'=>$state['dados'],'methods'=>$this->onboarding->paymentMethods(),'error'=>pull_flash('error'),'old'=>pull_flash('old',[]),'certificateOfferUrl'=>'/certificado-digital/oferta']);
    }
    public function certificateOffer():string{Auth::requireUser();return View::render('onboarding/certificate-offer',['title'=>'Certificado digital | PinePet']);}
    public function store(Request$request):never
    {
        $user=Auth::requireUser();if(!empty($user['profile_complete']))Response::redirect('/painel');if(!Csrf::validate($request->input('_token')))Response::abort(419);$state=$this->onboarding->state((int)$user['user_id'],(int)$user['business_id']);$step=(int)$state['etapa'];
        $payload=null;try{if($step===6){$migrate=$request->input('migration_choice')==='1';$ids=json_decode((string)$request->input('completed_imports','[]'),true,16,JSON_THROW_ON_ERROR);if($migrate)$this->imports->assertCompleted(is_array($ids)?$ids:[],(int)$user['user_id'],(int)$user['business_id']);$name=$this->onboarding->complete((int)$user['user_id'],(int)$user['business_id']);Csrf::rotate();Auth::completeProfile($name);flash('success','Configuracao concluida. Bem-vindo ao PinePet!');Response::redirect('/painel');}$payload=match($step){1=>$this->identity($request,(int)$user['user_id']),2=>$this->vaccines($request),3=>$this->fiscal($request,(int)$user['business_id']),4=>$this->payments($request),5=>$this->hours($request),default=>throw new \RuntimeException('Etapa invalida.')};$key=[1=>'identidade',2=>'vacinas',3=>'fiscal',4=>'pagamentos',5=>'horarios'][$step];$this->onboarding->save((int)$user['user_id'],(int)$user['business_id'],$step,$key,$payload);}
        catch(\Throwable$e){if(is_array($payload)&&isset($payload['certificado']['path'])){$path=BASE_PATH.'/'.ltrim((string)$payload['certificado']['path'],'/');if(str_starts_with($path,BASE_PATH.'/storage/private/certificates/')&&is_file($path))unlink($path);}error_log('Onboarding step '.$step.': '.$e->getMessage());$safe=$e instanceof \RuntimeException&&!$e instanceof \PDOException?$e->getMessage():'Nao foi possivel salvar esta etapa.';$old=$_POST;unset($old['_token'],$old['certificate_password']);flash('error',$safe);flash('old',$old);Response::redirect('/primeiro-acesso');}Response::redirect('/primeiro-acesso');
    }
    private function identity(Request$r,int$userId):array
    {
        $profile=$this->profiles->data($userId);if(!$profile)throw new \RuntimeException('Nao foi possivel recuperar os dados do cadastro.');$name=(string)$profile['nome_completo'];$birth=(string)$profile['data_nascimento'];$sex=(string)$profile['sexo_biologico'];$cpf=(string)$profile['cpf'];$has=$r->input('has_cnpj')==='1';$cnpj=$this->cnpjValue($r->input('cnpj'));$trade=$this->clean($r->input('trade_name'),120);$role=$this->clean($r->input('role'),40);
        if(!in_array($role,['Proprietario','Socio','Gestor','Profissional'],true))throw new \RuntimeException('Selecione sua funcao no estabelecimento.');
        $company=[];if($has){if(!$this->validCnpj($cnpj))throw new \RuntimeException('Informe um CNPJ valido.');if(mb_strlen($trade)<2)throw new \RuntimeException('Informe o nome do estabelecimento.');try{$this->throttleCnpj();$company=$this->cnpj->lookup($cnpj);}catch(\RuntimeException$e){error_log('Consulta CNPJ indisponivel: '.$e->getMessage());}}elseif(mb_strlen($trade)<2)throw new \RuntimeException('Informe o nome do estabelecimento.');
        return['name'=>$name,'birth_date'=>$birth,'sex'=>$sex,'cpf'=>$cpf,'has_cnpj'=>$has,'cnpj'=>$cnpj,'trade_name'=>$trade,'role'=>$role,'company'=>$company];
    }
    private function vaccines(Request$r):array
    {
        $yes=$r->input('aplica_vacinas')==='1';$data=['aplica_vacinas'=>$yes,'specialty'=>$this->clean($r->input('specialty'),180)];if(!$yes)return$data;$data+=['rt_name'=>$this->clean($r->input('rt_name'),240),'rt_cpf'=>$this->digits($r->input('rt_cpf')),'rt_council'=>strtoupper($this->clean($r->input('rt_council'),20)),'rt_number'=>$this->clean($r->input('rt_number'),40),'rt_uf'=>strtoupper($this->clean($r->input('rt_uf'),2)),'rt_validity'=>trim((string)$r->input('rt_validity')),'rt_email'=>mb_strtolower($this->clean($r->input('rt_email'),254)),'rt_phone'=>$this->digits($r->input('rt_phone'))];if(mb_strlen($data['rt_name'])<3||!$this->document($data['rt_cpf'],11)||!in_array($data['rt_council'],['CRMV'],true)||preg_match('/^[A-Z]{2}$/',$data['rt_uf'])!==1||$data['rt_number']==='')throw new \RuntimeException('Preencha os dados validos do responsavel tecnico.');if($data['rt_email']!==''&&!filter_var($data['rt_email'],FILTER_VALIDATE_EMAIL))throw new \RuntimeException('Informe um e-mail valido para o responsavel tecnico.');return$data;
    }
    private function fiscal(Request$r,int$businessId):array
    {
        $issue=$r->input('emitir_nf')==='1';$has=$issue&&$r->input('has_certificate')==='1';$data=['emitir_nf'=>$issue,'tem_certificado'=>$has,'quer_certificado'=>$issue&&!$has];if(!$issue)return$data;if($has){$password=(string)$r->input('certificate_password');if($password==='')throw new \RuntimeException('Informe a senha do certificado digital.');$data['certificado']=$this->certificates->store($_FILES['certificate']??[],$password,$businessId);}return$data;
    }
    private function payments(Request$r):array{$selected=$r->input('payment_methods',[]);if(!is_array($selected))throw new \RuntimeException('Selecione os metodos de pagamento.');$allowed=array_column($this->onboarding->paymentMethods(),'codigo');$selected=array_values(array_unique(array_filter($selected,fn($v)=>is_string($v)&&in_array($v,$allowed,true))));if($selected===[])throw new \RuntimeException('Selecione ao menos um metodo de pagamento.');return['metodos'=>$selected];}
    private function hours(Request$r):array
    {
        $result=[];for($day=0;$day<7;$day++){$closed=$r->input('closed_'.$day)==='1';$open=trim((string)$r->input('open_'.$day));$close=trim((string)$r->input('close_'.$day));if(!$closed&&(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$open)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$close)||$close<=$open))throw new \RuntimeException('Revise os horarios de funcionamento.');$result[$day]=['closed'=>$closed,'open'=>$closed?null:$open,'close'=>$closed?null:$close];}return$result;
    }
    private function clean(mixed$v,int$max):string{return mb_substr(preg_replace('/\s+/u',' ',trim(is_scalar($v)?(string)$v:'')),0,$max);}
    private function digits(mixed$v):string{return preg_replace('/\D/','',is_scalar($v)?(string)$v:'');}
    private function cnpjValue(mixed$v):string{return strtoupper(preg_replace('/[^A-Za-z0-9]/','',is_scalar($v)?(string)$v:''));}
    private function validCnpj(string$v):bool{if(preg_match('/^[A-Z0-9]{12}[0-9]{2}$/',$v)!==1)return false;$weights=[[5,4,3,2,9,8,7,6,5,4,3,2],[6,5,4,3,2,9,8,7,6,5,4,3,2]];$base=substr($v,0,12);for($round=0;$round<2;$round++){$sum=0;foreach(str_split($base)as$i=>$char)$sum+=(ord($char)-48)*$weights[$round][$i];$remainder=$sum%11;$digit=$remainder<2?0:11-$remainder;if((int)$v[12+$round]!==$digit)return false;$base.=(string)$digit;}return true;}
    private function throttleCnpj():void{$now=time();$attempts=array_values(array_filter($_SESSION['_cnpj_attempts']??[],fn($time)=>is_int($time)&&$time>$now-600));if(count($attempts)>=5)throw new \RuntimeException('Muitas consultas de CNPJ. Aguarde alguns minutos e tente novamente.');$attempts[]=$now;$_SESSION['_cnpj_attempts']=$attempts;}
    private function document(string$v,int$length):bool{if(strlen($v)!==$length||preg_match('/^(\d)\1+$/',$v))return false;$base=$length===11?9:12;for($round=0;$round<2;$round++){$size=$base+$round;$sum=0;for($i=0;$i<$size;$i++){$weight=$length===11?($size+1-$i):(($size-1-$i)%8+2);$sum+=(int)$v[$i]*$weight;}$digit=$length===11?(($sum*10)%11)%10:(($sum%11)<2?0:11-($sum%11));if((int)$v[$size]!==$digit)return false;}return true;}
}
