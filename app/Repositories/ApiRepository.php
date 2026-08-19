<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ApiRepository
{
    public function __construct(private readonly Database $database){}
    public function establishment(int$id):?array{return$this->one('SELECT id,public_id,cnpj,razao_social,nome_fantasia,email,telefone,municipio,uf,created_at,updated_at FROM organizacao_estabelecimentos WHERE id=:business AND deleted_at IS NULL',['business'=>$id]);}
    public function clients(int$id,int$limit,int$offset):array{return$this->many('SELECT c.id,c.nome,c.sobrenome,c.data_nascimento,s.codigo AS sexo_codigo,s.nome AS sexo,c.email,c.whatsapp_com_ddd,c.criado_em,c.atualizado_em FROM clientes c LEFT JOIN clientes_sexos s ON s.id=c.id_sexo WHERE c.id_estabelecimento=:business ORDER BY c.id DESC',$id,$limit,$offset);}
    public function pets(int$id,int$limit,int$offset):array{return$this->many("SELECT p.id,p.nome,p.data_nascimento,e.codigo AS especie_codigo,e.nome AS especie,r.codigo AS raca_codigo,r.nome AS raca,po.codigo AS porte_codigo,po.nome AS porte,p.criado_em,p.atualizado_em,COALESCE((SELECT jsonb_agg(jsonb_build_object('id_cliente',c.id,'nome',c.nome,'sobrenome',c.sobrenome) ORDER BY c.nome) FROM pets_tutores pt JOIN clientes c ON c.id=pt.id_cliente AND c.id_estabelecimento=pt.id_estabelecimento WHERE pt.id_pet=p.id AND pt.id_estabelecimento=p.id_estabelecimento),'[]'::jsonb) AS tutores FROM pets p LEFT JOIN pets_especies e ON e.id=p.id_especie LEFT JOIN pets_racas r ON r.id=p.id_raca AND r.id_especie=p.id_especie LEFT JOIN pets_portes po ON po.id=p.id_porte WHERE p.id_estabelecimento=:business ORDER BY p.id DESC",$id,$limit,$offset);}
    public function schedules(int$id,int$limit,int$offset):array{return$this->many('SELECT a.id,a.id_cliente,a.id_profissional,a.cliente_novo,a.cliente_novo_nome,a.data_hora_inicio,a.data_hora_fim,a.status,a.pagamento_sinal,a.valor_sinal,a.criado_em,a.atualizado_em FROM agenda_agendamentos a WHERE a.id_estabelecimento=:business ORDER BY a.data_hora_inicio DESC',$id,$limit,$offset);}
    public function attendances(int$id,int$limit,int$offset):array{return$this->many('SELECT a.id,a.id_cliente,a.id_servico,a.id_agenda,a.titulo,a.id_profissional,a.id_fluxo_atendimento,a.status,a.criado_em,a.atualizado_em FROM atendimento_atendimentos a WHERE a.id_estabelecimento=:business ORDER BY a.id DESC',$id,$limit,$offset);}
    public function vaccines(int$id,int$limit,int$offset):array{return$this->many('SELECT v.id,v.id_pet,v.id_profissional,v.nome_vacina,v.fabricante,v.lote,v.dose,v.aplicada_em,v.proxima_dose_em,v.status,v.observacoes,v.criado_em,v.atualizado_em FROM saude_vacinas v WHERE v.id_estabelecimento=:business ORDER BY v.aplicada_em DESC NULLS LAST,v.id DESC',$id,$limit,$offset);}
    public function users(int$id,int$limit,int$offset):array{return$this->many('SELECT u.id,u.public_id,u.nome_completo,u.email,u.numero_telefone_ddd,u.status,u.cadastro_completo_em,u.created_at,u.updated_at FROM acesso_usuarios u WHERE u.id_estabelecimento=:business AND u.deleted_at IS NULL ORDER BY u.id',$id,$limit,$offset);}
    public function products(int$id,int$limit,int$offset):array{return$this->many('SELECT p.id,p.nome,p.descricao,p.sku,p.codigo_barras,p.preco_venda,p.ativo,p.criado_em,p.atualizado_em FROM catalogo_produtos p WHERE p.id_estabelecimento=:business ORDER BY p.nome,p.id',$id,$limit,$offset);}
    public function services(int$id,int$limit,int$offset):array{return$this->many('SELECT s.id,s.nome,s.descricao,s.duracao_minutos,s.preco,s.ativo,s.criado_em,s.atualizado_em FROM catalogo_servicos s WHERE s.id_estabelecimento=:business ORDER BY s.nome,s.id',$id,$limit,$offset);}
    private function one(string$sql,array$params):?array{$s=$this->database->pdo()->prepare($sql);$s->execute($params);$row=$s->fetch();return is_array($row)?$row:null;}
    private function many(string$sql,int$businessId,int$limit,int$offset):array
    {
        $s=$this->database->pdo()->prepare($sql.' LIMIT :limit OFFSET :offset');$s->bindValue(':business',$businessId,PDO::PARAM_INT);$s->bindValue(':limit',$limit,PDO::PARAM_INT);$s->bindValue(':offset',$offset,PDO::PARAM_INT);$s->execute();return$s->fetchAll();
    }
}
