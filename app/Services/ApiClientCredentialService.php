<?php
declare(strict_types=1);
namespace App\Services;

final class ApiClientCredentialService
{
    public function __construct(private readonly KeyDerivationService$keys){}
    public function generate(string$clientId,int$businessId):array
    {
        $this->requireSodium();
        $secret='pp_'.rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$verifier=password_hash($secret,PASSWORD_ARGON2ID);if(!is_string($verifier))throw new \RuntimeException('Falha ao gerar verificador do cliente.');
        $signingKey=$this->keys->derive($this->master(),'jwt-client-signing/'.$clientId.'/'.$businessId,hash('sha256',$secret,true).$verifier);return['secret'=>$secret,'verifier'=>$verifier,'encrypted_key'=>$this->encrypt($signingKey)];
    }
    public function signingKey(array$client):string{$this->requireSodium();return$this->decrypt((string)($client['chave_assinatura_cifrada']??''));}
    public function authenticate(string$secret,array$client):bool
    {
        $verifier=(string)($client['segredo_verificador']??'');return$verifier!==''&&strlen($secret)<=200&&password_verify($secret,$verifier);
    }
    private function encrypt(string$value):string{$nonce=random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);$cipher=sodium_crypto_secretbox($value,$nonce,$this->encryptionKey());return base64_encode($nonce.$cipher);}
    private function decrypt(string$value):string{$raw=base64_decode($value,true);if($raw===false||strlen($raw)<=\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)throw new \RuntimeException('Credencial cifrada do cliente ausente ou invalida.');$plain=sodium_crypto_secretbox_open(substr($raw,\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($raw,0,\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$this->encryptionKey());if($plain===false||strlen($plain)!==32)throw new \RuntimeException('Nao foi possivel validar a credencial cifrada do cliente.');return$plain;}
    private function encryptionKey():string{return$this->keys->derive($this->master(),'jwt-client-encryption');}
    private function master():string{$key=(string)env('API_JWT_SECRET','');if(strlen($key)<32)throw new \RuntimeException('API_JWT_SECRET deve possuir ao menos 32 caracteres.');return$key;}
    private function requireSodium():void{if(!extension_loaded('sodium')||!function_exists('sodium_crypto_secretbox'))throw new \RuntimeException('A extensao Sodium e obrigatoria para credenciais de API.');}
}
