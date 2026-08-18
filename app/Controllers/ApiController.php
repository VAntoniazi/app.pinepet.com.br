<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\ApiCodebook;
use App\Core\ApiResponse;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\ApiRepository;
use App\Repositories\PermissionRepository;
use App\Security\TenantContext;

final class ApiController
{
    public function __construct(private readonly TenantContext$tenant,private readonly ApiRepository$data,private readonly PermissionRepository$permissions){}
    public function establishment(Request$r):never{$c=$this->tenant->authorize($r,'establishments');$row=$this->data->establishment($c['id_estabelecimento']);if(!$row)throw new HttpException(404,'API-404-NOT-FOUND','Estabelecimento não encontrado.');ApiResponse::accepted($row,['id_estabelecimento'=>$c['id_estabelecimento']]);}
    public function clients(Request$r):never{$this->collection($r,'clients',fn($id,$l,$o)=>$this->data->clients($id,$l,$o));}
    public function pets(Request$r):never{$this->collection($r,'pets',fn($id,$l,$o)=>$this->data->pets($id,$l,$o));}
    public function schedules(Request$r):never{$this->collection($r,'schedules',fn($id,$l,$o)=>$this->data->schedules($id,$l,$o));}
    public function attendances(Request$r):never{$this->collection($r,'attendances',fn($id,$l,$o)=>$this->data->attendances($id,$l,$o));}
    public function vaccines(Request$r):never{$this->collection($r,'vaccines',fn($id,$l,$o)=>$this->data->vaccines($id,$l,$o));}
    public function users(Request$r):never{$this->collection($r,'users',fn($id,$l,$o)=>$this->data->users($id,$l,$o));}
    public function products(Request$r):never{$this->collection($r,'products',fn($id,$l,$o)=>$this->data->products($id,$l,$o));}
    public function services(Request$r):never{$this->collection($r,'services',fn($id,$l,$o)=>$this->data->services($id,$l,$o));}
    public function permissions(Request$r):never{$c=$this->tenant->authorize($r,'permissions');if($c['auth_type']!=='session')throw new HttpException(403,'API-403-PERMISSION-DENIED','Consulta de permissoes de usuario exige sessao.');$rows=$this->permissions->listForUser($c['user_id'],$c['id_estabelecimento']);ApiResponse::accepted($rows,['id_estabelecimento'=>$c['id_estabelecimento'],'count'=>count($rows)]);}
    public function checkPermission(Request$r):never
    {
        $c=$this->tenant->authorize($r,'permissions');if($c['auth_type']!=='session')throw new HttpException(403,'API-403-PERMISSION-DENIED','Consulta de permissoes de usuario exige sessao.');$resource=$r->query('recurso');$action=$r->query('acao','read');
        if(!is_string($resource)||preg_match('/^[a-z][a-z0-9_]{0,59}$/',$resource)!==1||!is_string($action)||preg_match('/^[a-z][a-z0-9_]{0,29}$/',$action)!==1)throw new HttpException(422,'API-422-VALIDATION-FAILED','recurso ou acao possui formato inválido.');
        ApiResponse::accepted(['recurso'=>$resource,'acao'=>$action,'permitido'=>$this->permissions->allowed($c['user_id'],$c['id_estabelecimento'],$resource,$action)],['id_estabelecimento'=>$c['id_estabelecimento']]);
    }
    public function codebook(Request$r):never{$c=$this->tenant->authorize($r,'codebook');ApiResponse::accepted(ApiCodebook::ENTRIES,['id_estabelecimento'=>$c['id_estabelecimento'],'count'=>count(ApiCodebook::ENTRIES)]);}
    private function collection(Request$r,string$resource,callable$loader):never
    {
        $c=$this->tenant->authorize($r,$resource);$page=$this->positive($r->query('page',1),'page',1,1000000);$perPage=$this->positive($r->query('per_page',25),'per_page',1,100);
        $rows=$loader($c['id_estabelecimento'],$perPage,($page-1)*$perPage);
        ApiResponse::accepted($rows,['id_estabelecimento'=>$c['id_estabelecimento'],'page'=>$page,'per_page'=>$perPage,'count'=>count($rows),'has_more'=>count($rows)===$perPage]);
    }
    private function positive(mixed$value,string$field,int$min,int$max):int
    {
        if(!is_scalar($value)||filter_var((string)$value,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]])===false)throw new HttpException(422,'API-422-VALIDATION-FAILED',"{$field} é inválido.",['field'=>$field]);
        return(int)$value;
    }
}
