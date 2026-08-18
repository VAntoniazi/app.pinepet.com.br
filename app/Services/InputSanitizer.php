<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\HttpException;

final class InputSanitizer
{
    public function positiveInt(mixed$value,string$field):int
    {
        if(!is_scalar($value)||filter_var((string)$value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false)$this->invalid($field);return(int)$value;
    }
    public function decimal(mixed$value,string$field,int$scale=3):string
    {
        if(!is_int($value)&&!is_float($value)&&!is_string($value))$this->invalid($field);
        $raw=trim((string)$value);if($raw===''||preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,'.$scale.'})?$/',$raw)!==1)$this->invalid($field);return$raw;
    }
    public function enum(mixed$value,string$field,array$allowed):string{$value=is_string($value)?strtoupper(trim($value)):'';if(!in_array($value,$allowed,true))$this->invalid($field);return$value;}
    public function idempotencyKey(mixed$value):string
    {
        $value=is_string($value)?trim($value):'';if(preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,127}$/',$value)!==1)throw new HttpException(400,'API-400-IDEMPOTENCY-REQUIRED','Idempotency-Key é obrigatória e deve possuir de 16 a 128 caracteres.');return$value;
    }
    private function invalid(string$field):never{throw new HttpException(422,'API-422-VALIDATION-FAILED',"{$field} possui formato inválido.",['field'=>$field]);}
}
