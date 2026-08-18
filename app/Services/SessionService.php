<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Request;
use App\Repositories\SessionRepository;

final class SessionService
{
    public function __construct(private readonly SessionRepository$sessions,private readonly KeyDerivationService$keys){}
    public function register(array$user,Request$request):void
    {
        $passwordHash=(string)($user['senha']??'');if($passwordHash==='')throw new \RuntimeException('Hash de credencial ausente ao registrar sessao.');
        $this->sessions->create($this->sessionHash(),$this->credentialBinding($passwordHash),(int)$user['id'],(int)$user['id_estabelecimento'],$this->uaHash($request->userAgent()),$this->ipHash($request->ip()),$this->idle(),$this->absolute());
    }
    public function validateCurrent(array$auth):bool
    {
        if(session_status()!==PHP_SESSION_ACTIVE||session_id()==='')return false;$row=$this->sessions->findValid($this->sessionHash(),(int)$auth['user_id'],(int)$auth['business_id']);
        if(!$row||!hash_equals((string)$row['user_agent_hash'],$this->uaHash((string)($_SERVER['HTTP_USER_AGENT']??'')))||!hash_equals((string)$row['credential_binding_hash'],$this->credentialBinding((string)$row['password_hash'])))return false;
        $last=strtotime((string)$row['ultima_atividade_em']);if($last===false||$last<=time()-max(30,(int)env('SESSION_TOUCH_INTERVAL',60)))$this->sessions->touch((int)$row['id'],$this->idle());return true;
    }
    public function revokeCurrent():void{if(session_status()===PHP_SESSION_ACTIVE&&session_id()!=='')$this->sessions->revoke($this->sessionHash());}
    private function sessionHash():string{return hash_hmac('sha256',session_id(),$this->keys->derive($this->master(),'session-lookup'));}
    private function credentialBinding(string$passwordHash):string{return hash_hmac('sha256',session_id(),$this->keys->derive($this->master(),'session-credential',$passwordHash));}
    private function uaHash(string$value):string{return hash_hmac('sha256',mb_substr($value,0,500),$this->keys->derive($this->master(),'session-user-agent'));}
    private function ipHash(string$value):string{return hash_hmac('sha256',$value,$this->keys->derive($this->master(),'session-ip'));}
    private function idle():int{return max(300,(int)env('SESSION_IDLE_TIMEOUT',1800));}
    private function absolute():int{return max($this->idle(),(int)env('SESSION_ABSOLUTE_TIMEOUT',43200));}
    private function master():string{$key=(string)env('SESSION_HASH_KEY','');if(strlen($key)<32)throw new \RuntimeException('SESSION_HASH_KEY deve possuir ao menos 32 caracteres.');return$key;}
}
