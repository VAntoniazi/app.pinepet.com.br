<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\ProfileRepository;

final class OnboardingController
{
    public function __construct(private readonly ProfileRepository $profiles) {}
    public function show(): string
    {
        $user=Auth::requireUser(); if(!empty($user['profile_complete'])) Response::redirect('/painel',302);
        $profile=$this->profiles->data((int)$user['user_id']); if(!$profile) Response::abort(403);
        return View::render('onboarding/index',['title'=>'Complete seu cadastro | PinePet','profile'=>$profile,'error'=>pull_flash('error'),'old'=>pull_flash('old',[])]);
    }
    public function store(Request $request): never
    {
        $user=Auth::requireUser(); if(!empty($user['profile_complete'])) Response::redirect('/painel');
        if(!Csrf::validate($request->input('_token'))) Response::abort(419);
        $data=['name'=>preg_replace('/\s+/u',' ',trim((string)$request->input('name'))),'birth_date'=>trim((string)$request->input('birth_date')),'sex'=>trim((string)$request->input('sex')),'cpf'=>preg_replace('/\D/','',(string)$request->input('cpf')),'trade_name'=>preg_replace('/\s+/u',' ',trim((string)$request->input('trade_name'))),'cnpj'=>preg_replace('/\D/','',(string)$request->input('cnpj'))];
        $errors=$this->validate($data);
        if($errors!==[]){ flash('error',implode(' ',$errors)); flash('old',$data); Response::redirect('/primeiro-acesso'); }
        try { $this->profiles->complete((int)$user['user_id'],(int)$user['business_id'],$data); }
        catch(\PDOException $e){ if($e->getCode()==='23505'){ flash('error','CPF ou CNPJ já vinculado a outra conta.'); flash('old',$data); Response::redirect('/primeiro-acesso'); } throw $e; }
        Csrf::rotate(); Auth::completeProfile($data['name']); flash('success','Cadastro concluído. Bem-vindo ao PinePet!'); Response::redirect('/painel');
    }
    private function validate(array $data): array
    {
        $errors=[];
        if(mb_strlen($data['name'])<3||mb_strlen($data['name'])>120||!preg_match('/\s/u',$data['name'])) $errors[]='Informe seu nome completo.';
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$data['birth_date']); $min=(new \DateTimeImmutable('today'))->modify('-18 years');
        if(!$date||$date->format('Y-m-d')!==$data['birth_date']||$date>$min||$date<new \DateTimeImmutable('1900-01-01')) $errors[]='Informe uma data de nascimento válida para maior de 18 anos.';
        if(!in_array($data['sex'],['feminino','masculino','intersexo','nao_informar'],true)) $errors[]='Selecione uma opção válida para sexo biológico.';
        if(!$this->validDocument($data['cpf'],11)) $errors[]='Informe um CPF válido.';
        if(mb_strlen($data['trade_name'])<2||mb_strlen($data['trade_name'])>120) $errors[]='Informe o nome do estabelecimento.';
        if($data['cnpj']!==''&&!$this->validDocument($data['cnpj'],14)) $errors[]='Informe um CNPJ válido ou deixe o campo vazio.';
        return $errors;
    }
    private function validDocument(string $value,int $length): bool
    {
        if(strlen($value)!==$length||preg_match('/^(\d)\1+$/',$value)) return false;
        $base=$length===11?9:12; $digits=$length===11?[$base+1,$base+2]:[$base+1,$base+2];
        for($round=0;$round<2;$round++){ $size=$base+$round; $sum=0; for($i=0;$i<$size;$i++){ $weight=$length===11?($size+1-$i):(($size-1-$i)%8+2); $sum+=(int)$value[$i]*$weight; } $digit=$length===11?(($sum*10)%11)%10:(($sum%11)<2?0:11-($sum%11)); if((int)$value[$size]!==$digit)return false; }
        return true;
    }
}
