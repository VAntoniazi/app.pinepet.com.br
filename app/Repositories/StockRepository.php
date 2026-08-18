<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\Database;
use App\Core\HttpException;
use PDO;

final class StockRepository
{
    public function __construct(private readonly Database$database){}
    public function list(int$businessId,int$limit,int$offset):array
    {
        $s=$this->database->pdo()->prepare('SELECT e.id,e.id_produto,p.nome AS produto,p.sku,e.quantidade_atual,e.quantidade_reservada,(e.quantidade_atual-e.quantidade_reservada) AS quantidade_disponivel,e.estoque_minimo,e.versao,e.atualizado_em FROM estoque_itens e JOIN catalogo_produtos p ON p.id_estabelecimento=e.id_estabelecimento AND p.id=e.id_produto WHERE e.id_estabelecimento=:business ORDER BY p.nome,e.id LIMIT :limit OFFSET :offset');
        $s->bindValue(':business',$businessId,PDO::PARAM_INT);$s->bindValue(':limit',$limit,PDO::PARAM_INT);$s->bindValue(':offset',$offset,PDO::PARAM_INT);$s->execute();return$s->fetchAll();
    }
    public function move(int$businessId,int$itemId,string$type,string$quantity,string$idempotencyKey,array$actor):array
    {
        return$this->database->transaction(function(PDO$pdo)use($businessId,$itemId,$type,$quantity,$idempotencyKey,$actor):array{
            $lock=$pdo->prepare('SELECT pg_advisory_xact_lock(hashtextextended(:key,0))');$lock->execute(['key'=>$businessId.':'.$idempotencyKey]);
            $existing=$this->findMovement($pdo,$businessId,$idempotencyKey);if($existing)return['movement'=>$existing,'replayed'=>true];
            $s=$pdo->prepare('SELECT id,quantidade_atual,quantidade_reservada,versao FROM estoque_itens WHERE id_estabelecimento=:business AND id=:item FOR UPDATE');$s->execute(['business'=>$businessId,'item'=>$itemId]);$item=$s->fetch();
            if(!is_array($item))throw new HttpException(404,'API-404-NOT-FOUND','Item de estoque não encontrado.');
            $current=(string)$item['quantidade_atual'];$reserved=(string)$item['quantidade_reservada'];
            $update=$pdo->prepare("UPDATE estoque_itens SET quantidade_atual=CASE WHEN :type_a='ENTRADA' THEN quantidade_atual+CAST(:qty_a AS numeric) WHEN :type_b='SAIDA' THEN quantidade_atual-CAST(:qty_b AS numeric) WHEN :type_c='AJUSTE' THEN CAST(:qty_c AS numeric) ELSE quantidade_atual END,quantidade_reservada=CASE WHEN :type_d='RESERVA' THEN quantidade_reservada+CAST(:qty_d AS numeric) WHEN :type_e='LIBERACAO' THEN quantidade_reservada-CAST(:qty_e AS numeric) ELSE quantidade_reservada END,versao=versao+1 WHERE id=:item AND id_estabelecimento=:business AND versao=:version AND ((:type_f='ENTRADA') OR (:type_g='SAIDA' AND quantidade_atual-quantidade_reservada>=CAST(:qty_g AS numeric)) OR (:type_h='AJUSTE' AND CAST(:qty_h AS numeric)>=quantidade_reservada) OR (:type_i='RESERVA' AND quantidade_atual-quantidade_reservada>=CAST(:qty_i AS numeric)) OR (:type_j='LIBERACAO' AND quantidade_reservada>=CAST(:qty_j AS numeric))) RETURNING quantidade_atual,quantidade_reservada,versao");
            $params=['item'=>$itemId,'business'=>$businessId,'version'=>$item['versao']];foreach(['a','b','c','d','e','f','g','h','i','j']as$suffix)$params['type_'.$suffix]=$type;foreach(['a','b','c','d','e','g','h','i','j']as$suffix)$params['qty_'.$suffix]=$quantity;$update->execute($params);$updated=$update->fetch();
            if(!is_array($updated))throw new HttpException(409,'API-409-STOCK-INSUFFICIENT','Saldo disponível ou reservado insuficiente para a movimentação.');
            $insert=$pdo->prepare('INSERT INTO estoque_movimentacoes(id_estabelecimento,id_item,tipo,quantidade,quantidade_anterior,quantidade_posterior,reservada_anterior,reservada_posterior,idempotency_key,id_usuario,id_api_cliente) VALUES(:business,:item,:type,:quantity,:before,:after,:reserved_before,:reserved_after,:key,:user_id,:client_id) RETURNING id,id_estabelecimento,id_item,tipo,quantidade,quantidade_anterior,quantidade_posterior,reservada_anterior,reservada_posterior,idempotency_key,criado_em');
            $insert->execute(['business'=>$businessId,'item'=>$itemId,'type'=>$type,'quantity'=>$quantity,'before'=>$current,'after'=>$updated['quantidade_atual'],'reserved_before'=>$reserved,'reserved_after'=>$updated['quantidade_reservada'],'key'=>$idempotencyKey,'user_id'=>$actor['user_id']??null,'client_id'=>$actor['api_client_id']??null]);
            return['movement'=>$insert->fetch(),'replayed'=>false];
        });
    }
    private function findMovement(PDO$pdo,int$businessId,string$key):?array{$s=$pdo->prepare('SELECT id,id_estabelecimento,id_item,tipo,quantidade,quantidade_anterior,quantidade_posterior,reservada_anterior,reservada_posterior,idempotency_key,criado_em FROM estoque_movimentacoes WHERE id_estabelecimento=:business AND idempotency_key=:key LIMIT 1');$s->execute(['business'=>$businessId,'key'=>$key]);$row=$s->fetch();return is_array($row)?$row:null;}
}
