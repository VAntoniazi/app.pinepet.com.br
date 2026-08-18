<?php
declare(strict_types=1);
namespace App\Services;

final class IntegrationSecretService
{
    public function encrypt(string$value):string{$this->assertAvailable();if($value==='')throw new \InvalidArgumentException('Segredo de integracao vazio.');$nonce=random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);return base64_encode($nonce.sodium_crypto_secretbox($value,$nonce,$this->key()));}
    public function decrypt(string$value):string{$this->assertAvailable();$raw=base64_decode($value,true);if($raw===false||strlen($raw)<=\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)throw new \RuntimeException('Segredo cifrado invalido.');$plain=sodium_crypto_secretbox_open(substr($raw,\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($raw,0,\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$this->key());if($plain===false)throw new \RuntimeException('Nao foi possivel decifrar a integracao.');return$plain;}
    private function key():string{$master=(string)env('INTEGRATION_ENCRYPTION_KEY','');if(strlen($master)<32)throw new \RuntimeException('INTEGRATION_ENCRYPTION_KEY nao configurada.');return hash_hkdf('sha256',$master,\SODIUM_CRYPTO_SECRETBOX_KEYBYTES,'pinepet/integration-secrets/v1');}
    private function assertAvailable():void{if(!extension_loaded('sodium')||!function_exists('sodium_crypto_secretbox')||!defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES')||!defined('SODIUM_CRYPTO_SECRETBOX_KEYBYTES'))throw new \RuntimeException('A extensao Sodium e obrigatoria.');}
}
