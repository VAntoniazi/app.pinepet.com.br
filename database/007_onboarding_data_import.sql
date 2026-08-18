BEGIN;
SET search_path TO pinepet,public;

CREATE TABLE IF NOT EXISTS onboarding_importacoes(
 id varchar(64) PRIMARY KEY,id_usuario bigint NOT NULL REFERENCES acesso_usuarios(id) ON DELETE CASCADE,
 id_estabelecimento bigint NOT NULL REFERENCES organizacao_estabelecimentos(id) ON DELETE CASCADE,
 tipo varchar(20) NOT NULL,status varchar(20) NOT NULL DEFAULT 'PREPARADA',arquivo_nome varchar(255),arquivo_hash char(64) NOT NULL,chave_importacao char(64) NOT NULL,
 mapeamento jsonb NOT NULL,total_registros integer NOT NULL,total_processados integer NOT NULL DEFAULT 0,total_importados integer NOT NULL DEFAULT 0,total_rejeitados integer NOT NULL DEFAULT 0,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,concluido_em timestamptz,
 CHECK(tipo IN('CLIENTES','PETS','PRODUTOS','SERVICOS')),CHECK(status IN('PREPARADA','PROCESSANDO','CONCLUIDA','FALHOU')),
 CHECK(jsonb_typeof(mapeamento)='object'),CHECK(total_registros>=0 AND total_processados>=0 AND total_importados>=0 AND total_rejeitados>=0),
 UNIQUE(id,id_estabelecimento),UNIQUE(id_usuario,id_estabelecimento,chave_importacao)
);
CREATE INDEX IF NOT EXISTS ix_onboarding_importacoes_usuario ON onboarding_importacoes(id_usuario,id_estabelecimento,status);

CREATE TABLE IF NOT EXISTS onboarding_importacao_lotes(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,id_importacao varchar(64) NOT NULL,id_estabelecimento bigint NOT NULL,
 numero_lote integer NOT NULL,idempotency_key varchar(128) NOT NULL,payload_hash char(64) NOT NULL,
 quantidade integer NOT NULL,importados integer NOT NULL,rejeitados integer NOT NULL,resultado jsonb NOT NULL,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(id_importacao,id_estabelecimento) REFERENCES onboarding_importacoes(id,id_estabelecimento) ON DELETE CASCADE,
 UNIQUE(id_importacao,numero_lote),UNIQUE(id_estabelecimento,idempotency_key),CHECK(numero_lote>=0),CHECK(quantidade BETWEEN 1 AND 100),CHECK(jsonb_typeof(resultado)='object')
);

DROP TRIGGER IF EXISTS trg_onboarding_importacoes_atualizado_em ON onboarding_importacoes;
CREATE TRIGGER trg_onboarding_importacoes_atualizado_em BEFORE UPDATE ON onboarding_importacoes FOR EACH ROW EXECUTE FUNCTION fn_definir_atualizado_em();
INSERT INTO sistema_schema_versions(version,description,checksum_sha256)VALUES('app-007-onboarding-data-import','Importacao validada, transacional e idempotente no onboarding',NULL)ON CONFLICT(version)DO NOTHING;
COMMIT;
