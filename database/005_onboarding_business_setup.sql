BEGIN;
SET search_path TO pinepet,public;

CREATE TABLE IF NOT EXISTS onboarding_processos(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,id_usuario bigint NOT NULL REFERENCES acesso_usuarios(id) ON DELETE CASCADE,
 id_estabelecimento bigint NOT NULL REFERENCES organizacao_estabelecimentos(id) ON DELETE CASCADE,etapa smallint NOT NULL DEFAULT 1,
 dados jsonb NOT NULL DEFAULT '{}'::jsonb,concluido_em timestamptz,criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_usuario,id_estabelecimento),CHECK(etapa BETWEEN 1 AND 7),CHECK(jsonb_typeof(dados)='object')
);

CREATE TABLE IF NOT EXISTS organizacao_dados_receita(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL UNIQUE REFERENCES organizacao_estabelecimentos(id) ON DELETE CASCADE,
 cnpj varchar(14) NOT NULL,nome_empresarial varchar(255),nome_fantasia varchar(255),situacao_cadastral varchar(80),natureza_juridica varchar(180),
 data_abertura date,cnae_principal_codigo varchar(12),cnae_principal_descricao varchar(255),cep varchar(8),logradouro varchar(255),numero varchar(30),
 complemento varchar(180),bairro varchar(120),municipio varchar(120),uf char(2),email varchar(254),telefone varchar(30),fonte varchar(30) NOT NULL DEFAULT 'SERPRO',
 consultado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,resposta_hash char(64) NOT NULL,criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK(cnpj~'^[A-Z0-9]{12}[0-9]{2}$'),CHECK(uf IS NULL OR uf~'^[A-Z]{2}$')
);

ALTER TABLE organizacao_estabelecimentos ALTER COLUMN cnpj TYPE varchar(14) USING upper(regexp_replace(cnpj::text,'[^A-Za-z0-9]','','g'));
DO $$DECLARE constraint_name text;BEGIN FOR constraint_name IN SELECT conname FROM pg_constraint WHERE conrelid='organizacao_estabelecimentos'::regclass AND contype='c' AND pg_get_constraintdef(oid) ILIKE '%cnpj%' LOOP EXECUTE format('ALTER TABLE organizacao_estabelecimentos DROP CONSTRAINT %I',constraint_name);END LOOP;END$$;
ALTER TABLE organizacao_estabelecimentos ADD CONSTRAINT ck_estabelecimentos_cnpj_formato CHECK(cnpj IS NULL OR cnpj~'^[A-Z0-9]{12}[0-9]{2}$');

CREATE TABLE IF NOT EXISTS saude_responsaveis_tecnicos(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL UNIQUE REFERENCES organizacao_estabelecimentos(id) ON DELETE CASCADE,
 nome varchar(240) NOT NULL,cpf varchar(11) NOT NULL,conselho varchar(20) NOT NULL DEFAULT 'CRMV',numero_conselho varchar(40) NOT NULL,
 uf_conselho char(2) NOT NULL,validade_conselho date,email varchar(254),telefone varchar(20),ativo boolean NOT NULL DEFAULT TRUE,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK(cpf~'^[0-9]{11}$'),CHECK(uf_conselho~'^[A-Z]{2}$')
);

CREATE TABLE IF NOT EXISTS organizacao_configuracoes(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL UNIQUE REFERENCES organizacao_estabelecimentos(id) ON DELETE CASCADE,
 aplica_vacinas boolean NOT NULL DEFAULT FALSE,criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fiscal_configuracoes(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL UNIQUE REFERENCES organizacao_estabelecimentos(id) ON DELETE CASCADE,
 emitir_nota boolean NOT NULL DEFAULT FALSE,status_certificado varchar(30) NOT NULL DEFAULT 'NAO_APLICAVEL',
 oferta_certificado_solicitada boolean NOT NULL DEFAULT FALSE,criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK(status_certificado IN('NAO_APLICAVEL','PENDENTE_UPLOAD','ENVIADO','QUER_CONTRATAR'))
);

CREATE TABLE IF NOT EXISTS fiscal_certificados_digitais(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL REFERENCES organizacao_estabelecimentos(id) ON DELETE CASCADE,
 nome_original varchar(255) NOT NULL,caminho_cifrado varchar(500) NOT NULL,senha_cifrada text NOT NULL,sha256 char(64) NOT NULL,
 tamanho_bytes bigint NOT NULL,validade_em timestamptz NOT NULL,status varchar(20) NOT NULL DEFAULT 'ATIVO',criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,revogado_em timestamptz,
 CHECK(status IN('ATIVO','REVOGADO')),CHECK(tamanho_bytes BETWEEN 1 AND 5242880)
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_certificado_ativo_estabelecimento ON fiscal_certificados_digitais(id_estabelecimento) WHERE status='ATIVO';

CREATE TABLE IF NOT EXISTS financeiro_metodos_pagamento(
 id smallint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,codigo varchar(40) NOT NULL UNIQUE,nome varchar(100) NOT NULL,ativo boolean NOT NULL DEFAULT TRUE,ordem smallint NOT NULL DEFAULT 0
);
INSERT INTO financeiro_metodos_pagamento(codigo,nome,ordem)VALUES
('DINHEIRO','Dinheiro',10),('PIX','Pix',20),('CARTAO_DEBITO','Cartao de debito',30),('CARTAO_CREDITO','Cartao de credito',40),
('BOLETO','Boleto',50),('TRANSFERENCIA','Transferencia bancaria',60),('CARTEIRA_DIGITAL','Carteira digital',70),('VALE','Vale ou convenio',80)
ON CONFLICT(codigo)DO UPDATE SET nome=EXCLUDED.nome,ordem=EXCLUDED.ordem;

CREATE TABLE IF NOT EXISTS financeiro_estabelecimentos_metodos(
 id_estabelecimento bigint NOT NULL REFERENCES organizacao_estabelecimentos(id) ON DELETE CASCADE,id_metodo smallint NOT NULL REFERENCES financeiro_metodos_pagamento(id) ON DELETE RESTRICT,
 ativo boolean NOT NULL DEFAULT TRUE,criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id_estabelecimento,id_metodo)
);

CREATE TABLE IF NOT EXISTS organizacao_horarios(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL REFERENCES organizacao_estabelecimentos(id) ON DELETE CASCADE,
 dia_semana smallint NOT NULL,fechado boolean NOT NULL DEFAULT FALSE,abre_as time,fecha_as time,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento,dia_semana),CHECK(dia_semana BETWEEN 0 AND 6),
 CHECK((fechado=TRUE AND abre_as IS NULL AND fecha_as IS NULL)OR(fechado=FALSE AND abre_as IS NOT NULL AND fecha_as IS NOT NULL AND fecha_as>abre_as))
);

DO $$DECLARE t text;BEGIN FOREACH t IN ARRAY ARRAY['onboarding_processos','organizacao_dados_receita','saude_responsaveis_tecnicos','organizacao_configuracoes','fiscal_configuracoes','organizacao_horarios'] LOOP
 EXECUTE format('DROP TRIGGER IF EXISTS %I ON %I','trg_'||t||'_atualizado_em',t);EXECUTE format('CREATE TRIGGER %I BEFORE UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION fn_definir_atualizado_em()','trg_'||t||'_atualizado_em',t);
END LOOP;END$$;

INSERT INTO sistema_schema_versions(version,description,checksum_sha256)VALUES('app-005-onboarding-business-setup','Onboarding fiscal, sanitario, pagamentos e horarios',NULL)ON CONFLICT(version)DO NOTHING;
COMMIT;
