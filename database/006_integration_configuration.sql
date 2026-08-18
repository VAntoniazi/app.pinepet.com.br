BEGIN;
SET search_path TO pinepet,public;

CREATE TABLE IF NOT EXISTS sistema_integracoes(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,codigo varchar(60) NOT NULL UNIQUE,provedor varchar(60) NOT NULL,
 ambiente varchar(20) NOT NULL,cliente_id varchar(255) NOT NULL,cliente_segredo_cifrado text NOT NULL,ativo boolean NOT NULL DEFAULT TRUE,
 versao integer NOT NULL DEFAULT 1,configuracao jsonb NOT NULL DEFAULT '{}'::jsonb,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK(codigo~'^[A-Z][A-Z0-9_]{2,59}$'),CHECK(provedor IN('SERPRO_CNPJ_V2')),CHECK(ambiente IN('HOMOLOGACAO','PRODUCAO')),
 CHECK(versao>0),CHECK(jsonb_typeof(configuracao)='object')
);
DROP TRIGGER IF EXISTS trg_sistema_integracoes_atualizado_em ON sistema_integracoes;
CREATE TRIGGER trg_sistema_integracoes_atualizado_em BEFORE UPDATE ON sistema_integracoes FOR EACH ROW EXECUTE FUNCTION fn_definir_atualizado_em();

INSERT INTO sistema_schema_versions(version,description,checksum_sha256)VALUES('app-006-integration-configuration','Credenciais cifradas e perfis controlados de integracao',NULL)ON CONFLICT(version)DO NOTHING;
COMMIT;
