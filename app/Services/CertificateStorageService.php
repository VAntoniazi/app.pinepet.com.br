<?php
declare(strict_types=1);
namespace App\Services;

final class CertificateStorageService
{
    public function store(array$file,string$password,int$businessId):array
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new \RuntimeException('Envie um certificado digital A1 valido.');$size=(int)($file['size']??0);if($size<1||$size>5242880)throw new \RuntimeException('O certificado deve possuir no maximo 5 MB.');
        $name=basename((string)($file['name']??''));if(preg_match('/\.(pfx|p12)$/i',$name)!==1)throw new \RuntimeException('Use um certificado A1 nos formatos .pfx ou .p12.');$raw=file_get_contents((string)$file['tmp_name']);if(!is_string($raw))throw new \RuntimeException('Nao foi possivel ler o certificado.');
        if(!function_exists('openssl_pkcs12_read')||!openssl_pkcs12_read($raw,$parsed,$password)||empty($parsed['cert']))throw new \RuntimeException('Certificado ou senha invalidos.');$info=openssl_x509_parse($parsed['cert']);if(!is_array($info)||((int)($info['validTo_time_t']??0))<=time())throw new \RuntimeException('O certificado informado esta expirado ou e invalido.');
        if(!function_exists('sodium_crypto_secretbox'))throw new \RuntimeException('Sodium e obrigatorio para armazenar certificados.');$dir=BASE_PATH.'/storage/private/certificates';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new \RuntimeException('Nao foi possivel preparar o armazenamento seguro.');$id=bin2hex(random_bytes(24));$path=$dir.'/'.$id.'.enc';$encrypted=$this->encrypt($raw);if(file_put_contents($path,$encrypted,LOCK_EX)===false){throw new \RuntimeException('Nao foi possivel armazenar o certificado.');}chmod($path,0600);
        return['name'=>mb_substr($name,0,255),'path'=>'storage/private/certificates/'.$id.'.enc','password'=>$this->encrypt($password),'sha256'=>hash('sha256',$raw),'size'=>$size,'valid_until'=>date('Y-m-d H:i:sP',(int)$info['validTo_time_t'])];
    }
    private function encrypt(string$value):string{$key=(string)env('CERTIFICATE_ENCRYPTION_KEY','');if(strlen($key)<32)throw new \RuntimeException('CERTIFICATE_ENCRYPTION_KEY nao configurada.');$derived=hash_hkdf('sha256',$key,\SODIUM_CRYPTO_SECRETBOX_KEYBYTES,'pinepet/certificate-storage/v1');$nonce=random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);return base64_encode($nonce.sodium_crypto_secretbox($value,$nonce,$derived));}
}
