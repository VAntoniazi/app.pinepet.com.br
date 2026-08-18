<?php
declare(strict_types=1);
namespace App\Core;
final class Auth
{
    private static $sessionValidator=null;
    public static function setSessionValidator(callable$validator):void{self::$sessionValidator=$validator;}
    public static function login(array $user): void { session_regenerate_id(true); $_SESSION['auth']=['user_id'=>(int)$user['id'],'public_id'=>(string)$user['public_id'],'business_id'=>(int)$user['id_estabelecimento'],'name'=>(string)$user['nome_completo'],'email'=>(string)$user['email'],'profile_complete'=>!empty($user['cadastro_completo_em']),'issued_at'=>time(),'last_seen'=>time()]; }
    public static function user(): ?array
    {
        $auth=$_SESSION['auth']??null; if(!is_array($auth)) return null;
        if(time()-(int)$auth['last_seen']>(int)env('SESSION_IDLE_TIMEOUT',1800) || time()-(int)$auth['issued_at']>(int)env('SESSION_ABSOLUTE_TIMEOUT',43200)){ self::logout(); return null; }
        if(is_callable(self::$sessionValidator)&&!(self::$sessionValidator)($auth)){self::logout();return null;}
        $_SESSION['auth']['last_seen']=time(); return $_SESSION['auth'];
    }
    public static function requireUser(): array { $user=self::user(); if(!$user) Response::redirect('/entrar',302); return $user; }
    public static function requireCompletedProfile(): array { $user=self::requireUser(); if(empty($user['profile_complete'])) Response::redirect('/primeiro-acesso',302); return $user; }
    public static function completeProfile(string $name): void { $_SESSION['auth']['name']=$name; $_SESSION['auth']['profile_complete']=true; }
    public static function logout(): void { $_SESSION=[]; if(ini_get('session.use_cookies')){ $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); } session_destroy(); }
}
