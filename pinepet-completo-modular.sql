ROLLBACK;

BEGIN;
CREATE SCHEMA IF NOT EXISTS pinepet;
CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;
SET LOCAL search_path TO pinepet, public;
SET LOCAL TIME ZONE 'UTC';

-- Move estruturas eventualmente criadas em public sem alterar seus dados.
DO $$
DECLARE table_name text;
BEGIN
    FOREACH table_name IN ARRAY ARRAY[
        'sistema_schema_versions',
        'acesso_usuarios',
        'organizacao_estabelecimentos',
        'catalogo_planos',
        'catalogo_plano_precos',
        'gateway_precos',
        'onboarding_inscricoes',
        'onboarding_eventos',
        'gateway_assinaturas',
        'gateway_transacoes',
        'gateway_eventos',
        'blog_categorias',
        'blog_autores',
        'blog_posts',
        'marketing_newsletter',
        'sistema_integracao_referencias',
        'sistema_auditoria_eventos',
        'sistema_login_tentativas',
        'sistema_autenticacao_eventos',
        'clientes',
        'clientes_enderecos',
        'pets',
        'pets_tutores',
        'equipe_profissionais',
        'equipe_profissionais_detalhes',
        'atendimento_fluxos',
        'atendimento_fluxos_preferencias',
        'agenda_agendamentos',
        'atendimento_atendimentos',
        'sistema_usuarios_permissoes',
        'saude_vacinas',
        'catalogo_produtos',
        'catalogo_servicos',
        'sistema_sessoes',
        'sistema_api_clientes',
        'sistema_api_rate_limits',
        'estoque_itens',
        'estoque_movimentacoes',
        'onboarding_processos',
        'organizacao_dados_receita',
        'saude_responsaveis_tecnicos',
        'organizacao_configuracoes',
        'fiscal_configuracoes',
        'fiscal_certificados_digitais',
        'financeiro_metodos_pagamento',
        'financeiro_estabelecimentos_metodos',
        'organizacao_horarios',
        'sistema_integracoes',
        'onboarding_importacoes',
        'onboarding_importacao_lotes',
        'cadastro_estabelecimentos_configuracoes',
        'cadastro_estabelecimentos_dados_receita',
        'cadastro_estabelecimentos_horarios',
        'cadastro_profissionais_complementos',
        'cadastro_responsaveis_tecnicos',
        'cadastro_enderecos_clientes',
        'cadastro_pets_tutores',
        'cadastro_pets_vacinas',
        'cadastro_estabelecimentos',
        'cadastro_temporarios',
        'cadastro_funil_eventos',
        'cadastro_profissionais',
        'cadastro_usuarios',
        'cadastro_clientes',
        'cadastro_pets'
    ] LOOP
        IF to_regclass('public.'||table_name) IS NOT NULL THEN
            IF to_regclass('pinepet.'||table_name) IS NOT NULL THEN
                RAISE EXCEPTION 'Conflito: %.% e %.% existem simultaneamente','public',table_name,'pinepet',table_name;
            END IF;
            EXECUTE format('ALTER TABLE public.%I SET SCHEMA pinepet',table_name);
        END IF;
    END LOOP;
END
$$;

-- Renomeia as tabelas legadas para os modulos definitivos.
DO $$
DECLARE item text[];
BEGIN
    FOREACH item SLICE 1 IN ARRAY ARRAY[
        ARRAY['cadastro_estabelecimentos_configuracoes','organizacao_configuracoes'],
        ARRAY['cadastro_estabelecimentos_dados_receita','organizacao_dados_receita'],
        ARRAY['cadastro_estabelecimentos_horarios','organizacao_horarios'],
        ARRAY['cadastro_profissionais_complementos','equipe_profissionais_detalhes'],
        ARRAY['cadastro_responsaveis_tecnicos','saude_responsaveis_tecnicos'],
        ARRAY['cadastro_enderecos_clientes','clientes_enderecos'],
        ARRAY['cadastro_pets_tutores','pets_tutores'],
        ARRAY['cadastro_pets_vacinas','saude_vacinas'],
        ARRAY['cadastro_estabelecimentos','organizacao_estabelecimentos'],
        ARRAY['cadastro_temporarios','onboarding_inscricoes'],
        ARRAY['cadastro_funil_eventos','onboarding_eventos'],
        ARRAY['cadastro_profissionais','equipe_profissionais'],
        ARRAY['cadastro_usuarios','acesso_usuarios'],
        ARRAY['cadastro_clientes','clientes'],
        ARRAY['cadastro_pets','pets']
    ] LOOP
        IF to_regclass('pinepet.'||item[1]) IS NOT NULL THEN
            IF to_regclass('pinepet.'||item[2]) IS NOT NULL THEN
                RAISE EXCEPTION 'Conflito de migracao: pinepet.% e pinepet.% existem simultaneamente',item[1],item[2];
            END IF;
            EXECUTE format('ALTER TABLE pinepet.%I RENAME TO %I',item[1],item[2]);
        END IF;
    END LOOP;
END
$$;

-- Move funcoes associadas quando a instalacao anterior usou public.
DO $$
DECLARE function_name text;
BEGIN
    FOREACH function_name IN ARRAY ARRAY[
        'fn_touch_updated_at','fn_audit_row','fn_block_audit_mutation',
        'fn_definir_atualizado_em','fn_criar_estoque_produto'
    ] LOOP
        IF to_regprocedure('public.'||function_name||'()') IS NOT NULL THEN
            IF to_regprocedure('pinepet.'||function_name||'()') IS NOT NULL THEN
                RAISE EXCEPTION 'Conflito de funcao: public.% e pinepet.% existem simultaneamente',function_name,function_name;
            END IF;
            EXECUTE format('ALTER FUNCTION public.%I() SET SCHEMA pinepet',function_name);
        END IF;
    END LOOP;
END
$$;
SET LOCAL search_path TO pinepet, public;

-- ESTRUTURA CANONICA DO SITE (BLOG PRESERVADO)
-- Estrutura canônica completa do PinePet para PostgreSQL 16+.
-- Não insere dados e deve ser validada primeiro em HML.
CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;
SET LOCAL search_path TO pinepet, public;SET LOCAL TIME ZONE 'UTC';

-- Renomeia tabelas canônicas de versões anteriores antes de aplicar a estrutura.
DO $$
DECLARE item text[];
BEGIN
    FOREACH item SLICE 1 IN ARRAY ARRAY[
        ARRAY['schema_versions','sistema_schema_versions'],
        ARRAY['usuarios','acesso_usuarios'],
        ARRAY['estabelecimentos','organizacao_estabelecimentos'],
        ARRAY['planos','catalogo_planos'],
        ARRAY['plano_precos','catalogo_plano_precos'],
        ARRAY['cadastros_temporarios','onboarding_inscricoes'],
        ARRAY['assinaturas','gateway_assinaturas'],
        ARRAY['pagamentos_transacoes','gateway_transacoes'],
        ARRAY['newsletter','marketing_newsletter'],
        ARRAY['integracao_referencias','sistema_integracao_referencias'],
        ARRAY['auditoria_eventos','sistema_auditoria_eventos']
    ]
    LOOP
        IF to_regclass('pinepet.' || item[1]) IS NOT NULL THEN
            IF to_regclass('pinepet.' || item[2]) IS NOT NULL THEN
                RAISE EXCEPTION 'Conflito de migração: pinepet.% e pinepet.% existem simultaneamente',item[1],item[2];
            END IF;
            EXECUTE format('ALTER TABLE pinepet.%I RENAME TO %I',item[1],item[2]);
        END IF;
    END LOOP;
END
$$;

CREATE OR REPLACE FUNCTION fn_touch_updated_at() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN NEW.updated_at := clock_timestamp(); RETURN NEW; END
$$;

CREATE TABLE IF NOT EXISTS sistema_schema_versions (
    version varchar(40) PRIMARY KEY, description text NOT NULL,
    checksum_sha256 char(64), applied_at timestamptz NOT NULL DEFAULT clock_timestamp()
);

CREATE TABLE IF NOT EXISTS acesso_usuarios (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id uuid NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    nome_completo varchar(120) NOT NULL,
    data_nascimento date, sexo_biologico varchar(15), cpf varchar(11) UNIQUE,
    numero_telefone_ddd varchar(11) NOT NULL,
    email varchar(180) NOT NULL, senha varchar(255),
    cep varchar(8), uf char(2), municipio varchar(80), bairro varchar(80),
    logradouro varchar(120), numero varchar(20), complemento varchar(80),
    endereco_fonte varchar(20) NOT NULL DEFAULT 'viacep',
    termos_versao varchar(40), termos_aceitos_em timestamptz,
    status varchar(30) NOT NULL DEFAULT 'pendente_ativacao',
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    deleted_at timestamptz, version_lock integer NOT NULL DEFAULT 1,
    CHECK (email=lower(btrim(email))),
    CHECK (cpf IS NULL OR cpf ~ '^[0-9]{11}$'),
    CHECK (numero_telefone_ddd ~ '^[0-9]{10,11}$'),
    CHECK (cep IS NULL OR cep ~ '^[0-9]{8}$')
);
CREATE UNIQUE INDEX IF NOT EXISTS uk_acesso_usuarios_email_ci ON acesso_usuarios(lower(email)) WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uk_acesso_usuarios_telefone ON acesso_usuarios(numero_telefone_ddd) WHERE deleted_at IS NULL;

-- Consolida, na própria tabela de usuários, endereços de versões anteriores.
ALTER TABLE acesso_usuarios
    ADD COLUMN IF NOT EXISTS cep varchar(8),
    ADD COLUMN IF NOT EXISTS uf char(2),
    ADD COLUMN IF NOT EXISTS municipio varchar(80),
    ADD COLUMN IF NOT EXISTS bairro varchar(80),
    ADD COLUMN IF NOT EXISTS logradouro varchar(120),
    ADD COLUMN IF NOT EXISTS numero varchar(20),
    ADD COLUMN IF NOT EXISTS complemento varchar(80),
    ADD COLUMN IF NOT EXISTS endereco_fonte varchar(20) NOT NULL DEFAULT 'viacep';

DO $$
BEGIN
    IF to_regclass('pinepet.usuarios_enderecos') IS NOT NULL THEN
        EXECUTE 'UPDATE pinepet.acesso_usuarios u
                    SET cep=e.cep_usuario,
                        uf=e.uf_usuario,
                        municipio=e.municipio_usuario,
                        bairro=e.bairro_usuario,
                        logradouro=e.logradouro_usuario,
                        numero=e.numero_usuario,
                        complemento=e.complemento_usuario,
                        endereco_fonte=e.fonte
                   FROM pinepet.usuarios_enderecos e
                  WHERE e.id_usuario=u.id';
        DROP TABLE pinepet.usuarios_enderecos;
    END IF;
END
$$;

CREATE TABLE IF NOT EXISTS organizacao_estabelecimentos (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id uuid NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    cnpj varchar(14), razao_social varchar(120), nome_fantasia varchar(120) NOT NULL,
    email varchar(180), cep varchar(8), uf char(2), municipio varchar(80),
    bairro varchar(80), logradouro varchar(120), numero varchar(20), complemento varchar(80),
    porte varchar(30), cnae_principal varchar(20), situacao_cadastral varchar(40),
    data_situacao_cadastral date, telefone varchar(20),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp(), deleted_at timestamptz,
    CHECK (cnpj IS NULL OR cnpj ~ '^[0-9]{14}$')
);
CREATE UNIQUE INDEX IF NOT EXISTS uk_organizacao_estabelecimentos_cnpj ON organizacao_estabelecimentos(cnpj) WHERE cnpj IS NOT NULL AND deleted_at IS NULL;

ALTER TABLE acesso_usuarios
    ADD COLUMN IF NOT EXISTS id_estabelecimento bigint REFERENCES organizacao_estabelecimentos(id) ON DELETE RESTRICT;
DROP INDEX IF EXISTS pinepet.idx_usuarios_estabelecimento;
ALTER TABLE acesso_usuarios
    DROP COLUMN IF EXISTS papel_estabelecimento,
    DROP COLUMN IF EXISTS status_estabelecimento,
    DROP COLUMN IF EXISTS permissoes_estabelecimento;
CREATE INDEX IF NOT EXISTS idx_usuarios_estabelecimento
    ON acesso_usuarios(id_estabelecimento)
    WHERE deleted_at IS NULL;

-- Converte associações antigas para a relação 1:N sem perder vínculos.
DO $$
DECLARE origem regclass;
        tem_multiplos boolean;
BEGIN
    origem := coalesce(
        to_regclass('pinepet.estabelecimento_membros'),
        to_regclass('pinepet.estabelecimentos_usuarios'),
        CASE WHEN EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema='pinepet'
              AND table_name='usuarios_estabelecimentos'
              AND column_name='id_estabelecimento'
        ) THEN to_regclass('pinepet.usuarios_estabelecimentos') END
    );

    IF origem IS NOT NULL THEN
        EXECUTE format(
            'SELECT EXISTS (
                 SELECT 1 FROM %s GROUP BY id_usuario
                 HAVING count(DISTINCT id_estabelecimento)>1
             )',
            origem
        ) INTO tem_multiplos;
        IF tem_multiplos THEN
            RAISE EXCEPTION 'Migração interrompida: usuário vinculado a mais de um estabelecimento';
        END IF;

        EXECUTE format(
            'UPDATE pinepet.acesso_usuarios u
                SET id_estabelecimento=v.id_estabelecimento
               FROM %s v
              WHERE v.id_usuario=u.id',
            origem
        );
        EXECUTE format('DROP TABLE %s', origem);
    END IF;
END
$$;
DROP INDEX IF EXISTS pinepet.idx_estabelecimentos_usuarios_estabelecimento;
DROP INDEX IF EXISTS pinepet.idx_estabelecimentos_usuarios_usuario;
DROP INDEX IF EXISTS pinepet.idx_usuarios_estabelecimentos_estabelecimento;
DROP INDEX IF EXISTS pinepet.idx_usuarios_estabelecimentos_usuario;

CREATE TABLE IF NOT EXISTS catalogo_planos (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug varchar(80) NOT NULL UNIQUE, nome varchar(120) NOT NULL,
    descricao_curta varchar(255), ordem integer NOT NULL DEFAULT 0,
    is_ativo boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE TABLE IF NOT EXISTS catalogo_plano_precos (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_plano bigint NOT NULL REFERENCES catalogo_planos(id) ON DELETE CASCADE,
    periodicidade varchar(20) NOT NULL CHECK(periodicidade IN ('mensal','anual')),
    valor_centavos integer NOT NULL CHECK(valor_centavos>0),
    desconto_percent numeric(6,2) NOT NULL DEFAULT 0 CHECK(desconto_percent BETWEEN 0 AND 100),
    moeda char(3) NOT NULL DEFAULT 'BRL',
    is_ativo boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    UNIQUE(id_plano,periodicidade)
);

CREATE TABLE IF NOT EXISTS gateway_precos (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_plano_preco bigint NOT NULL REFERENCES catalogo_plano_precos(id) ON DELETE CASCADE,
    gateway varchar(30) NOT NULL,
    gateway_price_id varchar(120) NOT NULL,
    is_ativo boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    UNIQUE(gateway,gateway_price_id),
    UNIQUE(id_plano_preco,gateway)
);
CREATE INDEX IF NOT EXISTS idx_gateway_precos_plano
    ON gateway_precos(id_plano_preco,gateway)
    WHERE is_ativo IS TRUE;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema='pinepet'
          AND table_name='catalogo_plano_precos'
          AND column_name='pagarme_price_id'
    ) THEN
        EXECUTE 'INSERT INTO pinepet.gateway_precos(id_plano_preco,gateway,gateway_price_id)
                 SELECT id,''pagarme'',pagarme_price_id
                   FROM pinepet.catalogo_plano_precos
                  WHERE pagarme_price_id IS NOT NULL
                 ON CONFLICT(id_plano_preco,gateway) DO UPDATE
                    SET gateway_price_id=EXCLUDED.gateway_price_id';
        ALTER TABLE catalogo_plano_precos DROP COLUMN pagarme_price_id;
    END IF;
END
$$;

CREATE TABLE IF NOT EXISTS onboarding_inscricoes (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id char(32) NOT NULL UNIQUE, resume_token_hash char(64) NOT NULL,
    status varchar(40) NOT NULL DEFAULT 'responsavel',
    etapa_atual smallint NOT NULL DEFAULT 1 CHECK(etapa_atual BETWEEN 1 AND 3),
    nome_completo varchar(120) NOT NULL, email varchar(180) NOT NULL,
    telefone varchar(11) NOT NULL, id_plano bigint REFERENCES catalogo_planos(id),
    id_plano_preco bigint REFERENCES catalogo_plano_precos(id), periodicidade varchar(20),
    valor_contratado_centavos integer, moeda char(3), trial_inicio_em timestamptz,
    trial_fim_em timestamptz, primeira_cobranca_em timestamptz,
    operation_id char(32) NOT NULL UNIQUE, gateway varchar(30),
    gateway_customer_id varchar(100), gateway_payment_method_id varchar(100),
    gateway_subscription_id varchar(100),
    card_brand varchar(40), card_last4 varchar(4), aceite_recorrencia_em timestamptz,
    aceite_ip inet, aceite_user_agent varchar(500), termos_versao varchar(40),
    privacidade_versao varchar(40), aceite_texto text,
    id_usuario bigint REFERENCES acesso_usuarios(id), id_estabelecimento bigint REFERENCES organizacao_estabelecimentos(id),
    ativacao_token_hash char(64), ativacao_expira_em timestamptz,
    ativacao_enviada_em timestamptz, ultimo_reenvio_em timestamptz,
    concluido_em timestamptz, expira_em timestamptz NOT NULL,
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    CHECK(email=lower(btrim(email))), CHECK(telefone ~ '^[0-9]{10,11}$')
);
ALTER TABLE onboarding_inscricoes
    ADD COLUMN IF NOT EXISTS gateway varchar(30),
    ADD COLUMN IF NOT EXISTS gateway_customer_id varchar(100),
    ADD COLUMN IF NOT EXISTS gateway_payment_method_id varchar(100),
    ADD COLUMN IF NOT EXISTS gateway_subscription_id varchar(100);
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema='pinepet' AND table_name='onboarding_inscricoes'
          AND column_name='pagarme_customer_id'
    ) THEN
        EXECUTE 'UPDATE pinepet.onboarding_inscricoes
                    SET gateway=coalesce(gateway,''pagarme''),
                        gateway_customer_id=coalesce(gateway_customer_id,pagarme_customer_id),
                        gateway_payment_method_id=coalesce(gateway_payment_method_id,pagarme_card_id),
                        gateway_subscription_id=coalesce(gateway_subscription_id,pagarme_subscription_id)
                  WHERE pagarme_customer_id IS NOT NULL
                     OR pagarme_card_id IS NOT NULL
                     OR pagarme_subscription_id IS NOT NULL';
        ALTER TABLE onboarding_inscricoes
            DROP COLUMN pagarme_customer_id,
            DROP COLUMN pagarme_card_id,
            DROP COLUMN pagarme_subscription_id;
    END IF;
END
$$;
CREATE INDEX IF NOT EXISTS idx_onboarding_inscricoes_email ON onboarding_inscricoes(lower(email));
CREATE INDEX IF NOT EXISTS idx_onboarding_inscricoes_telefone ON onboarding_inscricoes(telefone);
CREATE INDEX IF NOT EXISTS idx_onboarding_inscricoes_expiracao ON onboarding_inscricoes(status,expira_em);

CREATE TABLE IF NOT EXISTS onboarding_eventos (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    cadastro_id bigint REFERENCES onboarding_inscricoes(id) ON DELETE SET NULL,
    evento varchar(60) NOT NULL, etapa smallint, ip_address inet,
    user_agent varchar(500), metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    correlation_id uuid, created_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE INDEX IF NOT EXISTS idx_funil_cadastro_data ON onboarding_eventos(cadastro_id,created_at DESC);
CREATE INDEX IF NOT EXISTS idx_funil_evento_data ON onboarding_eventos(evento,created_at DESC);

CREATE TABLE IF NOT EXISTS gateway_assinaturas (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id uuid NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    id_estabelecimento bigint NOT NULL REFERENCES organizacao_estabelecimentos(id) ON DELETE RESTRICT,
    id_plano bigint NOT NULL REFERENCES catalogo_planos(id) ON DELETE RESTRICT,
    id_plano_preco bigint NOT NULL REFERENCES catalogo_plano_precos(id) ON DELETE RESTRICT,
    gateway varchar(30) NOT NULL DEFAULT 'pagarme',
    gateway_customer_id varchar(100) NOT NULL, gateway_payment_method_id varchar(100),
    gateway_subscription_id varchar(100) NOT NULL,
    periodicidade varchar(20) NOT NULL CHECK(periodicidade IN ('mensal','anual')),
    moeda char(3) NOT NULL DEFAULT 'BRL', valor_base_centavos integer NOT NULL,
    desconto_percent numeric(6,2) NOT NULL DEFAULT 0, valor_contratado_centavos integer NOT NULL,
    status_acesso varchar(30) NOT NULL, status_gateway varchar(40) NOT NULL,
    em_trial boolean NOT NULL DEFAULT false, trial_dias integer NOT NULL DEFAULT 7,
    trial_inicio_em timestamptz, trial_fim_em timestamptz, ciclo_inicio_em timestamptz,
    ciclo_fim_em timestamptz, proxima_cobranca_em timestamptz,
    ultima_cobranca_em timestamptz, cancelado_em timestamptz,
    card_brand varchar(40), card_last4 varchar(4),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    UNIQUE(gateway,gateway_subscription_id)
);
CREATE INDEX IF NOT EXISTS idx_gateway_assinaturas_estabelecimento_status ON gateway_assinaturas(id_estabelecimento,status_acesso);
CREATE INDEX IF NOT EXISTS idx_gateway_assinaturas_cobranca ON gateway_assinaturas(proxima_cobranca_em) WHERE status_acesso IN ('trial','ativo');

CREATE TABLE IF NOT EXISTS gateway_transacoes (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_assinatura bigint NOT NULL REFERENCES gateway_assinaturas(id) ON DELETE RESTRICT,
    gateway_invoice_id varchar(100), gateway_charge_id varchar(100),
    amount_cents integer, currency char(3) NOT NULL DEFAULT 'BRL',
    status varchar(40) NOT NULL, next_billing_at timestamptz, paid_at timestamptz,
    card_brand varchar(40), card_last4 varchar(4), authorization_code varchar(80), raw jsonb,
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    UNIQUE(id_assinatura,gateway_charge_id)
);

-- Normaliza colunas da tabela financeira legada antes de criar índices e chaves.
ALTER TABLE gateway_transacoes
    ADD COLUMN IF NOT EXISTS id_assinatura bigint REFERENCES gateway_assinaturas(id) ON DELETE RESTRICT,
    ADD COLUMN IF NOT EXISTS gateway_invoice_id varchar(100),
    ADD COLUMN IF NOT EXISTS gateway_charge_id varchar(100);

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema='pinepet' AND table_name='gateway_transacoes'
          AND column_name='pagarme_invoice_id'
    ) THEN
        EXECUTE 'UPDATE pinepet.gateway_transacoes
                    SET gateway_invoice_id=coalesce(gateway_invoice_id,pagarme_invoice_id)';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema='pinepet' AND table_name='gateway_transacoes'
          AND column_name='pagarme_charge_id'
    ) THEN
        EXECUTE 'UPDATE pinepet.gateway_transacoes
                    SET gateway_charge_id=coalesce(gateway_charge_id,pagarme_charge_id)';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema='pinepet' AND table_name='gateway_transacoes'
          AND column_name='pagarme_subscription_id'
    ) THEN
        EXECUTE 'UPDATE pinepet.gateway_transacoes t
                    SET id_assinatura=a.id
                   FROM pinepet.gateway_assinaturas a
                  WHERE t.id_assinatura IS NULL
                    AND a.gateway=''pagarme''
                    AND a.gateway_subscription_id=t.pagarme_subscription_id';
    END IF;

    IF EXISTS (SELECT 1 FROM gateway_transacoes WHERE id_assinatura IS NULL) THEN
        RAISE EXCEPTION 'Migração interrompida: transação sem assinatura correspondente';
    END IF;
END
$$;

ALTER TABLE gateway_transacoes
    DROP CONSTRAINT IF EXISTS pagamentos_transacoes_pagarme_charge_id_key,
    DROP CONSTRAINT IF EXISTS pagamentos_transacoes_gateway_charge_id_key,
    DROP CONSTRAINT IF EXISTS gateway_transacoes_gateway_charge_id_key;
ALTER TABLE gateway_transacoes
    ALTER COLUMN id_assinatura SET NOT NULL,
    DROP COLUMN IF EXISTS id_estabelecimento,
    DROP COLUMN IF EXISTS pagarme_subscription_id,
    DROP COLUMN IF EXISTS pagarme_invoice_id,
    DROP COLUMN IF EXISTS pagarme_charge_id;
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid='pinepet.gateway_transacoes'::regclass
          AND contype='u'
          AND conkey=ARRAY[
              (SELECT attnum FROM pg_attribute WHERE attrelid='pinepet.gateway_transacoes'::regclass AND attname='id_assinatura'),
              (SELECT attnum FROM pg_attribute WHERE attrelid='pinepet.gateway_transacoes'::regclass AND attname='gateway_charge_id')
          ]::smallint[]
    ) THEN
        ALTER TABLE gateway_transacoes
            ADD CONSTRAINT uk_gateway_transacoes_assinatura_charge
            UNIQUE(id_assinatura,gateway_charge_id);
    END IF;
END
$$;
CREATE INDEX IF NOT EXISTS idx_gateway_transacoes_assinatura_data ON gateway_transacoes(id_assinatura,created_at DESC);

CREATE TABLE IF NOT EXISTS gateway_eventos (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    gateway varchar(30) NOT NULL DEFAULT 'pagarme', event_id varchar(120) NOT NULL,
    event_type varchar(80) NOT NULL, gateway_subscription_id varchar(100) NOT NULL,
    event_created_at timestamptz NOT NULL, processed_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    payload_hash char(64), UNIQUE(gateway,event_id)
);
CREATE INDEX IF NOT EXISTS idx_gateway_eventos_subscription ON gateway_eventos(gateway,gateway_subscription_id,event_created_at DESC);

CREATE TABLE IF NOT EXISTS blog_categorias (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome varchar(100) NOT NULL, slug varchar(100) NOT NULL UNIQUE, descricao text,
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE TABLE IF NOT EXISTS blog_autores (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id uuid NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    nome varchar(120) NOT NULL, slug varchar(120) NOT NULL UNIQUE,
    cargo varchar(120), bio text, url_perfil text, ativo boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE TABLE IF NOT EXISTS blog_posts (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id uuid NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    titulo varchar(180) NOT NULL, slug varchar(180) NOT NULL UNIQUE,
    resumo varchar(600), conteudo text NOT NULL,
    id_categoria bigint NOT NULL REFERENCES blog_categorias(id) ON DELETE RESTRICT,
    imagem_capa_r2_key text, imagem_capa_alt varchar(180), imagem_capa_etag varchar(100),
    imagem_capa_largura integer, imagem_capa_altura integer,
    status varchar(20) NOT NULL DEFAULT 'rascunho' CHECK(status IN ('rascunho','revisao','publicado','arquivado')),
    data_publicacao timestamptz, publicado_por_id bigint REFERENCES blog_autores(id) ON DELETE SET NULL,
    revisado_por_id bigint REFERENCES blog_autores(id) ON DELETE SET NULL,
    referencias_editoriais jsonb NOT NULL DEFAULT '[]'::jsonb,
    meta_title varchar(180), meta_description varchar(320), canonical_url text,
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp(), deleted_at timestamptz
);
ALTER TABLE blog_posts
    DROP CONSTRAINT IF EXISTS blog_posts_imagem_capa_r2_key_check,
    DROP CONSTRAINT IF EXISTS ck_blog_posts_r2_key;
ALTER TABLE blog_posts ADD CONSTRAINT ck_blog_posts_r2_key CHECK (
    imagem_capa_r2_key IS NULL
    OR imagem_capa_r2_key ~ '^capa-([a-z0-9]+-)+[a-f0-9]{8}\.(svg|png|jpg|jpeg|webp|gif|avif)$'
);
CREATE INDEX IF NOT EXISTS idx_blog_publicacao ON blog_posts(data_publicacao DESC,id DESC) WHERE status='publicado' AND deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_blog_categoria ON blog_posts(id_categoria,data_publicacao DESC) WHERE status='publicado' AND deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_blog_busca ON blog_posts USING gin(to_tsvector('portuguese',coalesce(titulo,'')||' '||coalesce(resumo,'')||' '||coalesce(conteudo,'')));
CREATE INDEX IF NOT EXISTS idx_blog_posts_r2_key ON blog_posts(imagem_capa_r2_key) WHERE imagem_capa_r2_key IS NOT NULL;

CREATE TABLE IF NOT EXISTS marketing_newsletter (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email varchar(180) NOT NULL, ipv4 inet, ipv6 inet, origem varchar(40) NOT NULL DEFAULT 'blog',
    status varchar(20) NOT NULL DEFAULT 'confirmado', data_cadastro timestamptz NOT NULL DEFAULT clock_timestamp(),
    data_confirmacao timestamptz, updated_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    CHECK(email=lower(btrim(email)))
);
CREATE UNIQUE INDEX IF NOT EXISTS uk_marketing_newsletter_email_ci ON marketing_newsletter(lower(email));

CREATE TABLE IF NOT EXISTS sistema_integracao_referencias (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    origem_sistema varchar(80) NOT NULL, origem_schema varchar(80),
    origem_entidade varchar(100) NOT NULL, origem_id varchar(160) NOT NULL,
    destino_entidade varchar(100) NOT NULL, destino_public_id uuid NOT NULL,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb, sincronizado_em timestamptz,
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    UNIQUE(origem_sistema,origem_entidade,origem_id,destino_entidade)
);

CREATE TABLE IF NOT EXISTS sistema_auditoria_eventos (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tabela_nome varchar(100) NOT NULL, registro_id varchar(160) NOT NULL,
    operacao char(1) NOT NULL CHECK(operacao IN ('I','U','D')),
    ator_tipo varchar(30) NOT NULL DEFAULT 'sistema', ator_id varchar(160),
    correlation_id uuid, request_id varchar(160), ip_address inet, user_agent varchar(500),
    dados_anteriores jsonb, dados_novos jsonb,
    ocorrido_em timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE INDEX IF NOT EXISTS idx_sistema_auditoria_registro ON sistema_auditoria_eventos(tabela_nome,registro_id,ocorrido_em DESC);

CREATE OR REPLACE FUNCTION fn_block_audit_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'A trilha de auditoria é imutável'; END
$$;
DROP TRIGGER IF EXISTS trg_block_audit_mutation ON sistema_auditoria_eventos;
CREATE TRIGGER trg_block_audit_mutation BEFORE UPDATE OR DELETE ON sistema_auditoria_eventos
FOR EACH ROW EXECUTE FUNCTION fn_block_audit_mutation();

CREATE OR REPLACE FUNCTION fn_audit_row() RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER SET search_path=pinepet,pg_temp AS $$
DECLARE old_data jsonb; new_data jsonb; row_id text;
BEGIN
 old_data:=CASE WHEN TG_OP IN ('UPDATE','DELETE') THEN to_jsonb(OLD) END;
 new_data:=CASE WHEN TG_OP IN ('INSERT','UPDATE') THEN to_jsonb(NEW) END;
 row_id:=coalesce(new_data->>'public_id',old_data->>'public_id',new_data->>'id',old_data->>'id','unknown');
 old_data:=old_data-ARRAY['senha','resume_token_hash','ativacao_token_hash','raw'];
 new_data:=new_data-ARRAY['senha','resume_token_hash','ativacao_token_hash','raw'];
 INSERT INTO sistema_auditoria_eventos(tabela_nome,registro_id,operacao,ator_tipo,ator_id,correlation_id,request_id,ip_address,user_agent,dados_anteriores,dados_novos)
 VALUES(TG_TABLE_NAME,row_id,left(TG_OP,1),coalesce(nullif(current_setting('pinepet.audit_actor_type',true),''),'sistema'),
 nullif(current_setting('pinepet.audit_actor_id',true),''),nullif(current_setting('pinepet.audit_correlation_id',true),'')::uuid,
 nullif(current_setting('pinepet.audit_request_id',true),''),nullif(current_setting('pinepet.audit_ip',true),'')::inet,
 left(nullif(current_setting('pinepet.audit_user_agent',true),''),500),old_data,new_data);
 RETURN NULL;
END
$$;

DO $$
DECLARE t text;
BEGIN
 FOREACH t IN ARRAY ARRAY['acesso_usuarios','organizacao_estabelecimentos','catalogo_planos','catalogo_plano_precos','gateway_precos','onboarding_inscricoes','gateway_assinaturas','gateway_transacoes','blog_categorias','blog_autores','blog_posts','marketing_newsletter','sistema_integracao_referencias']
 LOOP
  EXECUTE format('DROP TRIGGER IF EXISTS trg_touch_updated_at ON pinepet.%I',t);
  EXECUTE format('CREATE TRIGGER trg_touch_updated_at BEFORE UPDATE ON pinepet.%I FOR EACH ROW EXECUTE FUNCTION pinepet.fn_touch_updated_at()',t);
 END LOOP;
 FOREACH t IN ARRAY ARRAY['acesso_usuarios','organizacao_estabelecimentos','catalogo_planos','catalogo_plano_precos','gateway_precos','onboarding_inscricoes','onboarding_eventos','gateway_assinaturas','gateway_transacoes','gateway_eventos','blog_categorias','blog_autores','blog_posts','marketing_newsletter','sistema_integracao_referencias']
 LOOP
  EXECUTE format('DROP TRIGGER IF EXISTS trg_audit_row ON pinepet.%I',t);
  EXECUTE format('CREATE TRIGGER trg_audit_row AFTER INSERT OR UPDATE OR DELETE ON pinepet.%I FOR EACH ROW EXECUTE FUNCTION pinepet.fn_audit_row()',t);
 END LOOP;
END
$$;

INSERT INTO sistema_schema_versions(version,description) VALUES('postgres-4.0.0','Estrutura modular e multi-gateway PinePet')
ON CONFLICT(version) DO UPDATE SET description=EXCLUDED.description;


-- MODULOS DO APP
-- ============================================================================
-- APP / 001_auth_security.sql
-- ============================================================================
SET search_path TO pinepet, public;
CREATE TABLE IF NOT EXISTS sistema_login_tentativas (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email_hash char(64) NOT NULL,
    ip_hash char(64) NOT NULL,
    sucesso boolean NOT NULL DEFAULT false,
    user_agent varchar(500),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE INDEX IF NOT EXISTS idx_login_tentativas_email_data ON sistema_login_tentativas(email_hash,created_at DESC);
CREATE INDEX IF NOT EXISTS idx_login_tentativas_ip_data ON sistema_login_tentativas(ip_hash,created_at DESC);
ALTER TABLE acesso_usuarios ADD COLUMN IF NOT EXISTS cadastro_completo_em timestamptz;
CREATE TABLE IF NOT EXISTS sistema_autenticacao_eventos (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_usuario bigint REFERENCES acesso_usuarios(id) ON DELETE SET NULL,
    id_estabelecimento bigint REFERENCES organizacao_estabelecimentos(id) ON DELETE SET NULL,
    evento varchar(60) NOT NULL,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE INDEX IF NOT EXISTS idx_autenticacao_eventos_usuario_data ON sistema_autenticacao_eventos(id_usuario,created_at DESC);
INSERT INTO sistema_schema_versions(version,description,checksum_sha256) VALUES('app-001-auth-security','Tentativas de login protegidas por hashes',NULL) ON CONFLICT(version) DO NOTHING;


-- ============================================================================
-- APP / 002_operational_base.sql
-- ============================================================================
SET search_path TO pinepet, public;

CREATE OR REPLACE FUNCTION fn_definir_atualizado_em() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN NEW.atualizado_em:=CURRENT_TIMESTAMP; RETURN NEW; END;
$$;

CREATE TABLE IF NOT EXISTS clientes (
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,
 nome varchar(120) NOT NULL,sobrenome varchar(120),cpf varchar(11),email varchar(254),whatsapp_com_ddd varchar(20),
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento,id),CHECK(cpf IS NULL OR cpf~'^[0-9]{11}$'),CHECK(email IS NULL OR position('@' IN email)>1)
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_clientes_cpf_por_estabelecimento ON clientes(id_estabelecimento,cpf) WHERE cpf IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_clientes_nome ON clientes(id_estabelecimento,nome,sobrenome);
CREATE INDEX IF NOT EXISTS ix_clientes_whatsapp ON clientes(id_estabelecimento,whatsapp_com_ddd) WHERE whatsapp_com_ddd IS NOT NULL;

CREATE TABLE IF NOT EXISTS clientes_enderecos (
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,id_cliente bigint NOT NULL,
 pais varchar(100) NOT NULL DEFAULT 'Brasil',cep varchar(12),estado varchar(100),cidade varchar(120),bairro varchar(120),logradouro varchar(180),numero varchar(30),complemento varchar(180),
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(id_estabelecimento,id_cliente) REFERENCES clientes(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT
);
CREATE INDEX IF NOT EXISTS ix_enderecos_clientes_cliente ON clientes_enderecos(id_estabelecimento,id_cliente);

CREATE TABLE IF NOT EXISTS pets (
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,nome varchar(120) NOT NULL,data_nascimento date,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento,id)
);
CREATE INDEX IF NOT EXISTS ix_pets_nome ON pets(id_estabelecimento,nome);

CREATE TABLE IF NOT EXISTS pets_tutores (
 id_estabelecimento bigint NOT NULL,id_pet bigint NOT NULL,id_cliente bigint NOT NULL,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id_estabelecimento,id_pet,id_cliente),
 FOREIGN KEY(id_estabelecimento,id_pet) REFERENCES pets(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY(id_estabelecimento,id_cliente) REFERENCES clientes(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT
);
CREATE INDEX IF NOT EXISTS ix_pets_tutores_cliente ON pets_tutores(id_estabelecimento,id_cliente);

CREATE TABLE IF NOT EXISTS equipe_profissionais (
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,
 nome varchar(120) NOT NULL,sobrenome varchar(120),nome_social varchar(120),cpf varchar(11),data_nascimento date,
 email varchar(254),telefone varchar(20),whatsapp_com_ddd varchar(20),cargo varchar(120),especialidade varchar(180),ativo boolean NOT NULL DEFAULT TRUE,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento,id),CHECK(cpf IS NULL OR cpf~'^[0-9]{11}$'),CHECK(email IS NULL OR position('@' IN email)>1)
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_equipe_profissionais_cpf_por_estabelecimento ON equipe_profissionais(id_estabelecimento,cpf) WHERE cpf IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_equipe_profissionais_nome ON equipe_profissionais(id_estabelecimento,nome,sobrenome);
CREATE INDEX IF NOT EXISTS ix_equipe_profissionais_ativo ON equipe_profissionais(id_estabelecimento,ativo);

CREATE TABLE IF NOT EXISTS equipe_profissionais_detalhes (
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,id_profissional bigint NOT NULL,
 tipo_vinculo varchar(40),matricula varchar(60),conselho_classe varchar(40),numero_conselho varchar(60),uf_conselho varchar(2),validade_conselho date,
 formacao varchar(180),instituicao_formacao varchar(180),data_admissao date,data_desligamento date,carga_horaria_semanal numeric(6,2),observacoes text,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento,id_profissional),
 FOREIGN KEY(id_estabelecimento,id_profissional) REFERENCES equipe_profissionais(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK(uf_conselho IS NULL OR uf_conselho~'^[A-Z]{2}$'),CHECK(data_desligamento IS NULL OR data_admissao IS NULL OR data_desligamento>=data_admissao),
 CHECK(carga_horaria_semanal IS NULL OR carga_horaria_semanal>=0)
);

CREATE TABLE IF NOT EXISTS atendimento_fluxos (
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,nome varchar(120) NOT NULL,descricao text,
 etapas jsonb NOT NULL DEFAULT '[]'::jsonb,ativo boolean NOT NULL DEFAULT TRUE,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento,id),CHECK(jsonb_typeof(etapas)='array')
);
CREATE INDEX IF NOT EXISTS ix_atendimento_fluxos_ativo ON atendimento_fluxos(id_estabelecimento,ativo);

CREATE TABLE IF NOT EXISTS atendimento_fluxos_preferencias (
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,id_fluxo_padrao bigint,
 avancar_automaticamente boolean NOT NULL DEFAULT FALSE,permitir_pular_etapas boolean NOT NULL DEFAULT FALSE,notificar_cliente boolean NOT NULL DEFAULT FALSE,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento),FOREIGN KEY(id_estabelecimento,id_fluxo_padrao) REFERENCES atendimento_fluxos(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS agenda_agendamentos (
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,id_cliente bigint,id_profissional bigint,
 cliente_novo boolean NOT NULL DEFAULT FALSE,cliente_novo_nome varchar(240),cliente_novo_whatsapp_com_ddd varchar(20),
 pagamento_sinal boolean NOT NULL DEFAULT FALSE,valor_sinal numeric(12,2),data_hora_inicio timestamptz NOT NULL,data_hora_fim timestamptz,status varchar(40) NOT NULL DEFAULT 'PENDENTE',
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento,id),FOREIGN KEY(id_estabelecimento,id_cliente) REFERENCES clientes(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY(id_estabelecimento,id_profissional) REFERENCES equipe_profissionais(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK(id_cliente IS NOT NULL OR(cliente_novo=TRUE AND cliente_novo_nome IS NOT NULL AND btrim(cliente_novo_nome)<>'')),
 CHECK(valor_sinal IS NULL OR valor_sinal>=0),CHECK(data_hora_fim IS NULL OR data_hora_fim>data_hora_inicio),
 CHECK(status IN('PENDENTE','CONFIRMADO','CANCELADO','CONCLUIDO','NAO_COMPARECEU'))
);
CREATE INDEX IF NOT EXISTS ix_agenda_agendamentos_data ON agenda_agendamentos(id_estabelecimento,data_hora_inicio);
CREATE INDEX IF NOT EXISTS ix_agenda_agendamentos_status ON agenda_agendamentos(id_estabelecimento,status,data_hora_inicio);
CREATE INDEX IF NOT EXISTS ix_agenda_agendamentos_cliente ON agenda_agendamentos(id_estabelecimento,id_cliente) WHERE id_cliente IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_agenda_agendamentos_profissional ON agenda_agendamentos(id_estabelecimento,id_profissional,data_hora_inicio) WHERE id_profissional IS NOT NULL;

CREATE TABLE IF NOT EXISTS atendimento_atendimentos (
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,id_cliente bigint NOT NULL,id_servico bigint NOT NULL,id_agenda bigint,
 titulo varchar(180),id_profissional bigint,id_fluxo_atendimento bigint,status varchar(50) NOT NULL DEFAULT 'PENDENTE',
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento,id),FOREIGN KEY(id_estabelecimento,id_cliente) REFERENCES clientes(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY(id_estabelecimento,id_agenda) REFERENCES agenda_agendamentos(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY(id_estabelecimento,id_profissional) REFERENCES equipe_profissionais(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY(id_estabelecimento,id_fluxo_atendimento) REFERENCES atendimento_fluxos(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT
);
CREATE INDEX IF NOT EXISTS ix_atendimento_atendimentos_cliente ON atendimento_atendimentos(id_estabelecimento,id_cliente);
CREATE INDEX IF NOT EXISTS ix_atendimento_atendimentos_agenda ON atendimento_atendimentos(id_estabelecimento,id_agenda) WHERE id_agenda IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_atendimento_atendimentos_profissional ON atendimento_atendimentos(id_estabelecimento,id_profissional) WHERE id_profissional IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_atendimento_atendimentos_status ON atendimento_atendimentos(id_estabelecimento,status);

DO $$ DECLARE t text; BEGIN
 FOREACH t IN ARRAY ARRAY['clientes','clientes_enderecos','pets','pets_tutores','equipe_profissionais','equipe_profissionais_detalhes','atendimento_fluxos','atendimento_fluxos_preferencias','agenda_agendamentos','atendimento_atendimentos'] LOOP
  EXECUTE format('DROP TRIGGER IF EXISTS %I ON %I','trg_'||t||'_atualizado_em',t);
  EXECUTE format('CREATE TRIGGER %I BEFORE UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION fn_definir_atualizado_em()','trg_'||t||'_atualizado_em',t);
 END LOOP;
END $$;

INSERT INTO sistema_schema_versions(version,description,checksum_sha256)
VALUES('app-002-operational-base','Clientes, pets, profissionais, agenda e atendimento',NULL)
ON CONFLICT(version) DO NOTHING;


-- ============================================================================
-- APP / 003_api_permissions_catalogs.sql
-- ============================================================================
SET search_path TO pinepet,public;

CREATE TABLE IF NOT EXISTS sistema_usuarios_permissoes(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 id_usuario bigint NOT NULL REFERENCES acesso_usuarios(id) ON DELETE CASCADE,
 id_estabelecimento bigint NOT NULL REFERENCES organizacao_estabelecimentos(id) ON DELETE CASCADE,
 recurso varchar(60) NOT NULL,acao varchar(30) NOT NULL,permitido boolean NOT NULL DEFAULT FALSE,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_usuario,id_estabelecimento,recurso,acao),CHECK(recurso~'^[a-z][a-z0-9_]*$'),CHECK(acao~'^[a-z][a-z0-9_]*$')
);
CREATE INDEX IF NOT EXISTS ix_usuarios_permissoes_consulta ON sistema_usuarios_permissoes(id_usuario,id_estabelecimento,recurso,acao) WHERE permitido=TRUE;

CREATE TABLE IF NOT EXISTS saude_vacinas(
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,id_pet bigint NOT NULL,id_profissional bigint,
 nome_vacina varchar(160) NOT NULL,fabricante varchar(120),lote varchar(80),dose varchar(60),aplicada_em date,proxima_dose_em date,
 status varchar(30) NOT NULL DEFAULT 'PENDENTE',observacoes text,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento,id),
 FOREIGN KEY(id_estabelecimento,id_pet) REFERENCES pets(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY(id_estabelecimento,id_profissional) REFERENCES equipe_profissionais(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK(status IN('PENDENTE','APLICADA','ATRASADA','CANCELADA')),CHECK(proxima_dose_em IS NULL OR aplicada_em IS NULL OR proxima_dose_em>=aplicada_em)
);
CREATE INDEX IF NOT EXISTS ix_pets_vacinas_pet ON saude_vacinas(id_estabelecimento,id_pet,aplicada_em DESC);
CREATE INDEX IF NOT EXISTS ix_pets_vacinas_proxima ON saude_vacinas(id_estabelecimento,proxima_dose_em) WHERE status IN('PENDENTE','ATRASADA');

CREATE TABLE IF NOT EXISTS catalogo_produtos(
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,nome varchar(180) NOT NULL,descricao text,
 sku varchar(80),codigo_barras varchar(80),preco_venda numeric(12,2),ativo boolean NOT NULL DEFAULT TRUE,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento,id),CHECK(preco_venda IS NULL OR preco_venda>=0)
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_catalogo_produtos_sku ON catalogo_produtos(id_estabelecimento,sku) WHERE sku IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_catalogo_produtos_codigo_barras ON catalogo_produtos(id_estabelecimento,codigo_barras) WHERE codigo_barras IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_catalogo_produtos_nome ON catalogo_produtos(id_estabelecimento,nome);

CREATE TABLE IF NOT EXISTS catalogo_servicos(
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,nome varchar(180) NOT NULL,descricao text,
 duracao_minutos integer,preco numeric(12,2),ativo boolean NOT NULL DEFAULT TRUE,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento,id),CHECK(duracao_minutos IS NULL OR duracao_minutos>0),CHECK(preco IS NULL OR preco>=0)
);
CREATE INDEX IF NOT EXISTS ix_catalogo_servicos_nome ON catalogo_servicos(id_estabelecimento,nome);

DO $$ DECLARE t text;BEGIN
 FOREACH t IN ARRAY ARRAY['sistema_usuarios_permissoes','saude_vacinas','catalogo_produtos','catalogo_servicos'] LOOP
  EXECUTE format('DROP TRIGGER IF EXISTS %I ON %I','trg_'||t||'_atualizado_em',t);
  EXECUTE format('CREATE TRIGGER %I BEFORE UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION fn_definir_atualizado_em()','trg_'||t||'_atualizado_em',t);
 END LOOP;
END $$;

INSERT INTO sistema_usuarios_permissoes(id_usuario,id_estabelecimento,recurso,acao,permitido)
SELECT u.id,u.id_estabelecimento,p.recurso,'read',TRUE
FROM acesso_usuarios u
CROSS JOIN(VALUES('establishments'),('clients'),('pets'),('schedules'),('attendances'),('vaccines'),('users'),('products'),('services'),('permissions'),('codebook'))p(recurso)
WHERE u.id_estabelecimento IS NOT NULL AND u.status='ativo' AND u.deleted_at IS NULL
ON CONFLICT(id_usuario,id_estabelecimento,recurso,acao)DO NOTHING;

INSERT INTO sistema_schema_versions(version,description,checksum_sha256)
VALUES('app-003-api-permissions-catalogs','Permissões API, vacinas, produtos e serviços',NULL)
ON CONFLICT(version)DO NOTHING;


-- ============================================================================
-- APP / 004_security_sessions_stock.sql
-- ============================================================================
SET search_path TO pinepet,public;

CREATE TABLE IF NOT EXISTS sistema_sessoes(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,session_hash char(64) NOT NULL UNIQUE,
 id_usuario bigint NOT NULL REFERENCES acesso_usuarios(id) ON DELETE CASCADE,
 id_estabelecimento bigint NOT NULL REFERENCES organizacao_estabelecimentos(id) ON DELETE CASCADE,
 credential_binding_hash char(64) NOT NULL,user_agent_hash char(64) NOT NULL,ip_hash char(64) NOT NULL,
 emitida_em timestamptz NOT NULL,ultima_atividade_em timestamptz NOT NULL,
 expira_inatividade_em timestamptz NOT NULL,expira_absoluta_em timestamptz NOT NULL,revogada_em timestamptz,
 CHECK(expira_absoluta_em>emitida_em),CHECK(expira_inatividade_em<=expira_absoluta_em)
);
CREATE INDEX IF NOT EXISTS ix_sessoes_validacao ON sistema_sessoes(session_hash,id_usuario,id_estabelecimento) WHERE revogada_em IS NULL;
CREATE INDEX IF NOT EXISTS ix_sessoes_expiracao ON sistema_sessoes(expira_absoluta_em);

CREATE TABLE IF NOT EXISTS sistema_api_clientes(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,client_id varchar(100) NOT NULL UNIQUE,
 id_estabelecimento bigint NOT NULL REFERENCES organizacao_estabelecimentos(id) ON DELETE CASCADE,
 nome varchar(160) NOT NULL,scopes text[] NOT NULL DEFAULT '{}',ativo boolean NOT NULL DEFAULT TRUE,
 segredo_verificador varchar(255),chave_assinatura_cifrada text,segredo_versao integer NOT NULL DEFAULT 0,
 expira_em timestamptz,criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK(client_id~'^[A-Za-z0-9._-]{3,100}$'),CHECK(array_position(scopes,NULL) IS NULL),CHECK(segredo_versao>=0)
);
ALTER TABLE sistema_sessoes ADD COLUMN IF NOT EXISTS credential_binding_hash char(64);
UPDATE sistema_sessoes SET credential_binding_hash=repeat('0',64),revogada_em=COALESCE(revogada_em,NOW()) WHERE credential_binding_hash IS NULL;
ALTER TABLE sistema_sessoes ALTER COLUMN credential_binding_hash SET NOT NULL;
ALTER TABLE sistema_api_clientes ADD COLUMN IF NOT EXISTS segredo_verificador varchar(255);
ALTER TABLE sistema_api_clientes ADD COLUMN IF NOT EXISTS chave_assinatura_cifrada text;
ALTER TABLE sistema_api_clientes ADD COLUMN IF NOT EXISTS segredo_versao integer NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS ix_api_clientes_tenant ON sistema_api_clientes(id_estabelecimento,ativo);

CREATE TABLE IF NOT EXISTS sistema_api_rate_limits(
 chave_hash char(64) NOT NULL,inicio_janela timestamptz NOT NULL,contador integer NOT NULL DEFAULT 1,expira_em timestamptz NOT NULL,
 PRIMARY KEY(chave_hash,inicio_janela),CHECK(contador>0)
);
CREATE INDEX IF NOT EXISTS ix_api_rate_limits_expiracao ON sistema_api_rate_limits(expira_em);

CREATE TABLE IF NOT EXISTS estoque_itens(
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,id_produto bigint NOT NULL,
 quantidade_atual numeric(15,3) NOT NULL DEFAULT 0,quantidade_reservada numeric(15,3) NOT NULL DEFAULT 0,
 estoque_minimo numeric(15,3) NOT NULL DEFAULT 0,versao bigint NOT NULL DEFAULT 1,
 criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,atualizado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento,id),UNIQUE(id_estabelecimento,id_produto),
 FOREIGN KEY(id_estabelecimento,id_produto) REFERENCES catalogo_produtos(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK(quantidade_atual>=0),CHECK(quantidade_reservada>=0),CHECK(quantidade_reservada<=quantidade_atual),CHECK(estoque_minimo>=0),CHECK(versao>0)
);
CREATE INDEX IF NOT EXISTS ix_estoque_itens_produto ON estoque_itens(id_estabelecimento,id_produto);

CREATE TABLE IF NOT EXISTS estoque_movimentacoes(
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,id_estabelecimento bigint NOT NULL,id_item bigint NOT NULL,
 tipo varchar(20) NOT NULL,quantidade numeric(15,3) NOT NULL,
 quantidade_anterior numeric(15,3) NOT NULL,quantidade_posterior numeric(15,3) NOT NULL,
 reservada_anterior numeric(15,3) NOT NULL,reservada_posterior numeric(15,3) NOT NULL,
 idempotency_key varchar(128) NOT NULL,id_usuario bigint REFERENCES acesso_usuarios(id) ON DELETE RESTRICT,
 id_api_cliente varchar(100),criado_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_estabelecimento,idempotency_key),
 FOREIGN KEY(id_estabelecimento,id_item) REFERENCES estoque_itens(id_estabelecimento,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY(id_api_cliente) REFERENCES sistema_api_clientes(client_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK(tipo IN('ENTRADA','SAIDA','AJUSTE','RESERVA','LIBERACAO')),CHECK(quantidade>=0),
 CHECK(quantidade_anterior>=0),CHECK(quantidade_posterior>=0),CHECK(reservada_anterior>=0),CHECK(reservada_posterior>=0),
 CHECK((id_usuario IS NOT NULL)::integer+(id_api_cliente IS NOT NULL)::integer=1)
);
CREATE INDEX IF NOT EXISTS ix_estoque_movimentacoes_item ON estoque_movimentacoes(id_estabelecimento,id_item,criado_em DESC);

CREATE OR REPLACE FUNCTION fn_criar_estoque_produto()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 INSERT INTO estoque_itens(id_estabelecimento,id_produto) VALUES(NEW.id_estabelecimento,NEW.id)
 ON CONFLICT(id_estabelecimento,id_produto)DO NOTHING;
 RETURN NEW;
END;
$$;
DROP TRIGGER IF EXISTS trg_catalogo_produtos_criar_estoque ON catalogo_produtos;
CREATE TRIGGER trg_catalogo_produtos_criar_estoque AFTER INSERT ON catalogo_produtos FOR EACH ROW EXECUTE FUNCTION fn_criar_estoque_produto();

DROP TRIGGER IF EXISTS trg_sistema_api_clientes_atualizado_em ON sistema_api_clientes;
CREATE TRIGGER trg_sistema_api_clientes_atualizado_em BEFORE UPDATE ON sistema_api_clientes FOR EACH ROW EXECUTE FUNCTION fn_definir_atualizado_em();
DROP TRIGGER IF EXISTS trg_estoque_itens_atualizado_em ON estoque_itens;
CREATE TRIGGER trg_estoque_itens_atualizado_em BEFORE UPDATE ON estoque_itens FOR EACH ROW EXECUTE FUNCTION fn_definir_atualizado_em();

INSERT INTO sistema_usuarios_permissoes(id_usuario,id_estabelecimento,recurso,acao,permitido)
SELECT u.id,u.id_estabelecimento,'stock',a.acao,TRUE FROM acesso_usuarios u CROSS JOIN(VALUES('read'),('write'))a(acao)
WHERE u.id_estabelecimento IS NOT NULL AND u.status='ativo' AND u.deleted_at IS NULL
ON CONFLICT(id_usuario,id_estabelecimento,recurso,acao)DO NOTHING;

INSERT INTO estoque_itens(id_estabelecimento,id_produto)
SELECT id_estabelecimento,id FROM catalogo_produtos ON CONFLICT(id_estabelecimento,id_produto)DO NOTHING;

INSERT INTO sistema_schema_versions(version,description,checksum_sha256)
VALUES('app-004-security-sessions-stock','Sessoes persistentes, clientes JWT, rate limit e estoque idempotente',NULL)
ON CONFLICT(version)DO NOTHING;


-- ============================================================================
-- APP / 005_onboarding_business_setup.sql
-- ============================================================================
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


-- ============================================================================
-- APP / 006_integration_configuration.sql
-- ============================================================================
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


-- ============================================================================
-- APP / 007_onboarding_data_import.sql
-- ============================================================================
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
