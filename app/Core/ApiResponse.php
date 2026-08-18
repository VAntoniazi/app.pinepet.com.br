<?php
declare(strict_types=1);
namespace App\Core;

final class ApiResponse
{
    public static function accepted(array $data,array $meta=[]):never
    {
        Response::json(['ok'=>true,'status'=>'accepted','code'=>'API-200-ACCEPTED','message'=>'Requisição aceita.','data'=>$data,'meta'=>$meta+self::baseMeta()],200);
    }
    public static function error(string $code,string $message,int $httpStatus,array $details=[],?string $requestId=null):never
    {
        if($httpStatus===429&&isset($details['retry_after']))header('Retry-After: '.max(1,(int)$details['retry_after']));
        Response::json(['ok'=>false,'status'=>self::statusName($httpStatus),'code'=>$code,'message'=>$message,'details'=>(object)$details,'meta'=>self::baseMeta($requestId)],$httpStatus);
    }
    private static function baseMeta(?string $requestId=null):array{return['request_id'=>$requestId??bin2hex(random_bytes(8)),'timestamp'=>gmdate('c')];}
    private static function statusName(int$status):string{return match($status){400,413,419,422=>'error',401=>'unauthenticated',403=>'refused',404=>'not_found',405=>'method_not_allowed',409=>'conflict',429=>'limited',default=>'error'};}
}
