<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\ApiResponse;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\StockRepository;
use App\Security\TenantContext;
use App\Services\InputSanitizer;

final class StockController
{
    public function __construct(private readonly TenantContext$tenant,private readonly StockRepository$stock,private readonly InputSanitizer$input){}
    public function index(Request$r):never
    {
        $c=$this->tenant->authorize($r,'stock');$page=$this->page($r->query('page',1),'page',1000000);$perPage=$this->page($r->query('per_page',25),'per_page',100);
        $rows=$this->stock->list($c['id_estabelecimento'],$perPage,($page-1)*$perPage);ApiResponse::accepted($rows,['id_estabelecimento'=>$c['id_estabelecimento'],'page'=>$page,'per_page'=>$perPage,'count'=>count($rows),'has_more'=>count($rows)===$perPage]);
    }
    public function movement(Request$r):never
    {
        $c=$this->tenant->authorize($r,'stock','write');
        if($c['auth_type']==='session'&&!Csrf::validate($r->header('X-CSRF-Token')))throw new HttpException(419,'API-419-CSRF-EXPIRED','Token CSRF ausente ou expirado.');
        $item=$this->input->positiveInt($r->input('id_item'),'id_item');$type=$this->input->enum($r->input('tipo'),'tipo',['ENTRADA','SAIDA','AJUSTE','RESERVA','LIBERACAO']);$quantity=$this->input->decimal($r->input('quantidade'),'quantidade');
        if(preg_match('/^0(?:\.0+)?$/',$quantity)===1&&$type!=='AJUSTE')throw new HttpException(422,'API-422-VALIDATION-FAILED','quantidade deve ser maior que zero.',['field'=>'quantidade']);
        $key=$this->input->idempotencyKey($r->header('Idempotency-Key'));$result=$this->stock->move($c['id_estabelecimento'],$item,$type,$quantity,$key,$c);
        ApiResponse::accepted($result['movement'],['id_estabelecimento'=>$c['id_estabelecimento'],'idempotent_replay'=>$result['replayed']]);
    }
    private function page(mixed$value,string$field,int$max):int{if(!is_scalar($value)||filter_var((string)$value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>$max]])===false)throw new HttpException(422,'API-422-VALIDATION-FAILED',"{$field} é inválido.",['field'=>$field]);return(int)$value;}
}
