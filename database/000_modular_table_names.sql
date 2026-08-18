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

COMMIT;
