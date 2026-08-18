<?php
declare(strict_types=1);
namespace App\Core;

final class ApiCodebook
{
    public const ENTRIES=[
        ['http'=>200,'status'=>'accepted','code'=>'API-200-ACCEPTED','meaning'=>'Requisicao aceita e processada.'],
        ['http'=>400,'status'=>'error','code'=>'API-400-BUSINESS-REQUIRED','meaning'=>'id_estabelecimento nao foi informado.'],
        ['http'=>400,'status'=>'error','code'=>'API-400-BUSINESS-INVALID','meaning'=>'id_estabelecimento possui formato invalido.'],
        ['http'=>400,'status'=>'error','code'=>'API-400-INVALID-JSON','meaning'=>'Corpo JSON invalido.'],
        ['http'=>400,'status'=>'error','code'=>'API-400-IDEMPOTENCY-REQUIRED','meaning'=>'Chave de idempotencia ausente ou invalida.'],
        ['http'=>401,'status'=>'unauthenticated','code'=>'API-401-UNAUTHENTICATED','meaning'=>'Sessao ausente ou expirada.'],
        ['http'=>401,'status'=>'unauthenticated','code'=>'API-401-TOKEN-INVALID','meaning'=>'JWT invalido ou assinatura recusada.'],
        ['http'=>401,'status'=>'unauthenticated','code'=>'API-401-TOKEN-EXPIRED','meaning'=>'JWT expirado, futuro ou acima do TTL permitido.'],
        ['http'=>401,'status'=>'unauthenticated','code'=>'API-401-TOKEN-REVOKED','meaning'=>'Cliente de integracao revogado ou expirado.'],
        ['http'=>403,'status'=>'refused','code'=>'API-403-BUSINESS-MISMATCH','meaning'=>'Estabelecimento solicitado difere da identidade autenticada.'],
        ['http'=>403,'status'=>'refused','code'=>'API-403-PROFILE-INCOMPLETE','meaning'=>'Primeiro acesso ainda nao concluido.'],
        ['http'=>403,'status'=>'refused','code'=>'API-403-PERMISSION-DENIED','meaning'=>'Identidade sem permissao ou escopo para o recurso.'],
        ['http'=>404,'status'=>'not_found','code'=>'API-404-NOT-FOUND','meaning'=>'Endpoint ou registro nao encontrado.'],
        ['http'=>405,'status'=>'method_not_allowed','code'=>'API-405-METHOD-NOT-ALLOWED','meaning'=>'Metodo HTTP recusado.'],
        ['http'=>409,'status'=>'conflict','code'=>'API-409-CONFLICT','meaning'=>'Conflito com o estado atual do recurso.'],
        ['http'=>409,'status'=>'conflict','code'=>'API-409-STOCK-INSUFFICIENT','meaning'=>'Saldo disponivel ou reservado insuficiente.'],
        ['http'=>413,'status'=>'error','code'=>'API-413-PAYLOAD-TOO-LARGE','meaning'=>'Corpo acima do limite permitido.'],
        ['http'=>419,'status'=>'error','code'=>'API-419-CSRF-EXPIRED','meaning'=>'Token CSRF ausente ou expirado.'],
        ['http'=>422,'status'=>'error','code'=>'API-422-VALIDATION-FAILED','meaning'=>'Dados nao passaram pela validacao.'],
        ['http'=>429,'status'=>'limited','code'=>'API-429-RATE-LIMITED','meaning'=>'Limite temporario de requisicoes atingido.'],
        ['http'=>500,'status'=>'error','code'=>'API-500-INTERNAL-ERROR','meaning'=>'Falha interna nao detalhada ao cliente.'],
    ];
}
