<?php
declare(strict_types=1);
namespace App\Security;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\ApiClientRepository;
use App\Repositories\PermissionRepository;
use App\Services\ApiRateLimiter;
use App\Services\InputSanitizer;
use App\Services\JwtService;
use App\Services\ApiClientCredentialService;

final class TenantContext
{
    public function __construct(private readonly PermissionRepository$permissions,private readonly ApiClientRepository$clients,private readonly JwtService$jwt,private readonly ApiClientCredentialService$clientCredentials,private readonly ApiRateLimiter$rateLimiter,private readonly InputSanitizer$input){}
    public function authorize(Request$request,string$resource,string$action='read'):array
    {
        $raw=$request->query('id_estabelecimento',$request->input('id_estabelecimento'));
        if($raw===null||$raw==='')throw new HttpException(400,'API-400-BUSINESS-REQUIRED','id_estabelecimento é obrigatório.',['field'=>'id_estabelecimento']);
        try{$businessId=$this->input->positiveInt($raw,'id_estabelecimento');}catch(HttpException){throw new HttpException(400,'API-400-BUSINESS-INVALID','id_estabelecimento deve ser um inteiro positivo.',['field'=>'id_estabelecimento']);}
        $authorization=$request->header('Authorization');
        if(is_string($authorization)&&$authorization!=='')return$this->authorizeJwt($authorization,$businessId,$resource,$action);
        $auth=Auth::user();
        if(!$auth)throw new HttpException(401,'API-401-UNAUTHENTICATED','A sessão ou token é obrigatório para acessar esta API.');
        if(empty($auth['profile_complete']))throw new HttpException(403,'API-403-PROFILE-INCOMPLETE','Conclua o primeiro acesso antes de usar a API.');
        if($businessId!==(int)$auth['business_id'])throw new HttpException(403,'API-403-BUSINESS-MISMATCH','O estabelecimento informado não pertence à sessão.');
        if(!$this->permissions->activeMembership((int)$auth['user_id'],$businessId)){Auth::logout();throw new HttpException(401,'API-401-UNAUTHENTICATED','A sessão não é mais válida.');}
        if(!$this->permissions->allowed((int)$auth['user_id'],$businessId,$resource,$action))throw new HttpException(403,'API-403-PERMISSION-DENIED','Acesso recusado para este recurso.',['resource'=>$resource,'action'=>$action]);
        $this->rateLimiter->enforce('user:'.(int)$auth['user_id'],$businessId);
        return['auth_type'=>'session','user_id'=>(int)$auth['user_id'],'api_client_id'=>null,'id_estabelecimento'=>$businessId,'resource'=>$resource,'action'=>$action];
    }
    private function authorizeJwt(string$authorization,int$businessId,string$resource,string$action):array
    {
        if(strlen($authorization)>8192||preg_match('/^Bearer ([A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)$/',$authorization,$match)!==1)throw new HttpException(401,'API-401-TOKEN-INVALID','O token de integração é inválido.');
        $identity=$this->jwt->identity($match[1]);if($businessId!==$identity['tenant_id'])throw new HttpException(403,'API-403-BUSINESS-MISMATCH','O estabelecimento informado difere do token.');
        $client=$this->clients->active($identity['client_id'],$businessId);if(!$client||empty($client['chave_assinatura_cifrada']))throw new HttpException(401,'API-401-TOKEN-REVOKED','O cliente de integração foi revogado, expirou ou nao possui credencial.');
        try{$signingKey=$this->clientCredentials->signingKey($client);}catch(\Throwable){throw new HttpException(401,'API-401-TOKEN-REVOKED','A credencial do cliente nao pode ser validada.');}$claims=$this->jwt->validate($match[1],$signingKey,(int)$client['segredo_versao']);
        $databaseScopes=$this->postgresArray((string)$client['scopes']);$scope=$resource.'.'.$action;
        if(!in_array($scope,$claims['scopes'],true)||!in_array($scope,$databaseScopes,true))throw new HttpException(403,'API-403-PERMISSION-DENIED','O token não possui escopo para este recurso.',['resource'=>$resource,'action'=>$action]);
        $this->rateLimiter->enforce('client:'.$claims['client_id'],$businessId);
        return['auth_type'=>'jwt','user_id'=>null,'api_client_id'=>$claims['client_id'],'id_estabelecimento'=>$businessId,'resource'=>$resource,'action'=>$action];
    }
    private function postgresArray(string$value):array{if($value==='{}')return[];$value=trim($value,'{}');if($value==='')return[];return str_getcsv($value,',','"','\\');}
}
