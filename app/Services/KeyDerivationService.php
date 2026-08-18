<?php
declare(strict_types=1);
namespace App\Services;

final class KeyDerivationService
{
    public function derive(string$master,string$context,string$salt='',int$length=32):string
    {
        if(strlen($master)<32)throw new \RuntimeException('A chave mestra deve possuir ao menos 32 caracteres.');
        return hash_hkdf('sha256',$master,$length,'pinepet/'.$context.'/v1',$salt);
    }
}
