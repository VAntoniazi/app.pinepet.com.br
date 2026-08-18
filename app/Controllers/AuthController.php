<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\AuthRepository;
use App\Services\SessionService;

final class AuthController
{
    public function __construct(private readonly AuthRepository $users,private readonly SessionService$sessions) {}
    public function showLogin(): string { if(Auth::user()) Response::redirect('/painel',302); return View::render('auth/login',['title'=>'Entrar | PinePet','error'=>pull_flash('error'),'email'=>pull_flash('email','')]); }
    public function login(Request $request): never
    {
        if(!Csrf::validate($request->input('_token'))) Response::abort(419);
        $email=mb_strtolower(trim((string)$request->input('email'))); $password=(string)$request->input('password');
        $emailHash=hash('sha256',$email); $ipHash=hash('sha256',$request->ip()); $limit=max(3,(int)env('LOGIN_MAX_ATTEMPTS',5)); $minutes=max(1,(int)env('LOGIN_LOCK_MINUTES',15));
        if($this->users->isBlocked($emailHash,$ipHash,$limit,$minutes)){ flash('error','Muitas tentativas. Aguarde alguns minutos e tente novamente.'); Response::redirect('/entrar'); }
        $user=filter_var($email,FILTER_VALIDATE_EMAIL)?$this->users->findByEmail($email):null;
        $valid=is_array($user) && is_string($user['senha']) && password_verify($password,$user['senha']) && $user['status']==='ativo' && (int)$user['id_estabelecimento']>0;
        $this->users->recordAttempt($emailHash,$ipHash,$valid,$request->userAgent());
        if(!$valid){ password_verify($password,'$2y$12$WkEoP3QdGrjH7A4WmDhkuOHsxP6NuBlmfJj39mF1rkWBwzZzP8zbu'); flash('error','E-mail ou senha inválidos.'); flash('email',$email); Response::redirect('/entrar'); }
        if(password_needs_rehash($user['senha'],PASSWORD_DEFAULT)){ $user['senha']=password_hash($password,PASSWORD_DEFAULT);$this->users->updatePasswordHash((int)$user['id'],$user['senha']); }
        Csrf::rotate(); Auth::login($user);$this->sessions->register($user,$request);Response::redirect(empty($user['cadastro_completo_em'])?'/primeiro-acesso':'/painel');
    }
    public function logout(Request $request): never { if(!Csrf::validate($request->input('_token'))) Response::abort(419);$this->sessions->revokeCurrent();Auth::logout();Response::redirect('/entrar'); }
    public function showActivation(Request $request): string { if(Auth::user()) Response::redirect('/painel',302); return View::render('auth/activate',['title'=>'Definir senha | PinePet','token'=>trim((string)($_GET['token']??'')),'error'=>pull_flash('error')]); }
    public function activate(Request $request): never
    {
        if(!Csrf::validate($request->input('_token'))) Response::abort(419);
        $token=trim((string)$request->input('token')); $password=(string)$request->input('password'); $confirmation=(string)$request->input('password_confirmation');
        if(!preg_match('/^[A-Za-z0-9_-]{32,128}$/',$token) || strlen($password)<12 || strlen($password)>128 || $password!==$confirmation){ flash('error','Revise o link e use uma senha de 12 a 128 caracteres, com confirmação idêntica.'); Response::redirect('/definir-senha?token='.rawurlencode($token)); }
        $user=$this->users->activate(hash('sha256',$token),password_hash($password,PASSWORD_DEFAULT));
        if(!$user){ flash('error','Este link é inválido, expirou ou já foi utilizado.'); Response::redirect('/definir-senha'); }
        Csrf::rotate(); Auth::login($user);$this->sessions->register($user,$request);Response::redirect('/primeiro-acesso');
    }
}
