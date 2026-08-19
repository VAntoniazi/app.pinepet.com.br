BEGIN;
SET search_path TO pinepet,public;

CREATE OR REPLACE FUNCTION fn_cpf_valido(value text)RETURNS boolean LANGUAGE plpgsql IMMUTABLE STRICT AS $$
DECLARE i integer;sum_value integer;digit integer;base text;
BEGIN
 IF value!~'^[0-9]{11}$' OR value~'^([0-9])\1{10}$' THEN RETURN FALSE;END IF;
 base:=substring(value,1,9);
 FOR round_index IN 0..1 LOOP
  sum_value:=0;
  FOR i IN 1..(9+round_index)LOOP sum_value:=sum_value+(substring(base,i,1)::integer*(11+round_index-i));END LOOP;
  digit:=((sum_value*10)%11)%10;
  IF substring(value,10+round_index,1)::integer<>digit THEN RETURN FALSE;END IF;
  base:=base||digit::text;
 END LOOP;
 RETURN TRUE;
END$$;

CREATE OR REPLACE FUNCTION fn_cnpj_valido(value text)RETURNS boolean LANGUAGE plpgsql IMMUTABLE STRICT AS $$
DECLARE i integer;sum_value integer;digit integer;base text;weight integer;
BEGIN
 IF value!~'^[A-Z0-9]{12}[0-9]{2}$' OR value~'^([0-9])\1{13}$' THEN RETURN FALSE;END IF;
 base:=substring(value,1,12);
 FOR round_index IN 0..1 LOOP
  sum_value:=0;
  FOR i IN 1..(12+round_index)LOOP
   weight:=CASE WHEN round_index=0 THEN (ARRAY[5,4,3,2,9,8,7,6,5,4,3,2])[i] ELSE (ARRAY[6,5,4,3,2,9,8,7,6,5,4,3,2])[i] END;
   sum_value:=sum_value+((ascii(substring(base,i,1))-48)*weight);
  END LOOP;
  digit:=CASE WHEN sum_value%11<2 THEN 0 ELSE 11-(sum_value%11)END;
  IF substring(value,13+round_index,1)::integer<>digit THEN RETURN FALSE;END IF;
  base:=base||digit::text;
 END LOOP;
 RETURN TRUE;
END$$;

CREATE TABLE IF NOT EXISTS sistema_documentos_saneamento(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,tabela varchar(80)NOT NULL,registro_id varchar(80)NOT NULL,
 documento varchar(10)NOT NULL,motivo varchar(120)NOT NULL,ocorrido_em timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

DO $$
BEGIN
 IF EXISTS(SELECT 1 FROM acesso_usuarios WHERE cpf IS NOT NULL AND fn_cpf_valido(NULLIF(regexp_replace(cpf,'[^0-9]','','g'),''))IS NOT TRUE)THEN RAISE EXCEPTION 'Existem CPFs inválidos em acesso_usuarios; a migration foi cancelada sem alterar dados.';END IF;
 IF EXISTS(SELECT 1 FROM saude_responsaveis_tecnicos WHERE fn_cpf_valido(NULLIF(regexp_replace(cpf,'[^0-9]','','g'),''))IS NOT TRUE)THEN RAISE EXCEPTION 'Existem CPFs inválidos em saude_responsaveis_tecnicos; a migration foi cancelada sem alterar dados.';END IF;
 IF EXISTS(SELECT 1 FROM organizacao_estabelecimentos WHERE cnpj IS NOT NULL AND fn_cnpj_valido(NULLIF(upper(regexp_replace(cnpj,'[^A-Za-z0-9]','','g')),''))IS NOT TRUE)THEN RAISE EXCEPTION 'Existem CNPJs inválidos em organizacao_estabelecimentos; a migration foi cancelada sem alterar dados.';END IF;
 IF EXISTS(SELECT 1 FROM organizacao_dados_receita WHERE fn_cnpj_valido(NULLIF(upper(regexp_replace(cnpj,'[^A-Za-z0-9]','','g')),''))IS NOT TRUE)THEN RAISE EXCEPTION 'Existem CNPJs inválidos em organizacao_dados_receita; a migration foi cancelada sem alterar dados.';END IF;
END$$;

INSERT INTO sistema_documentos_saneamento(tabela,registro_id,documento,motivo)
SELECT 'clientes',id::text,'CPF','CPF legado inválido removido; campo opcional' FROM clientes WHERE cpf IS NOT NULL AND fn_cpf_valido(NULLIF(regexp_replace(cpf,'[^0-9]','','g'),''))IS NOT TRUE;
INSERT INTO sistema_documentos_saneamento(tabela,registro_id,documento,motivo)
SELECT 'equipe_profissionais',id::text,'CPF','CPF legado inválido removido; campo opcional' FROM equipe_profissionais WHERE cpf IS NOT NULL AND fn_cpf_valido(NULLIF(regexp_replace(cpf,'[^0-9]','','g'),''))IS NOT TRUE;
UPDATE clientes SET cpf=NULL WHERE cpf IS NOT NULL AND fn_cpf_valido(NULLIF(regexp_replace(cpf,'[^0-9]','','g'),''))IS NOT TRUE;
UPDATE equipe_profissionais SET cpf=NULL WHERE cpf IS NOT NULL AND fn_cpf_valido(NULLIF(regexp_replace(cpf,'[^0-9]','','g'),''))IS NOT TRUE;
UPDATE acesso_usuarios SET cpf=NULLIF(regexp_replace(cpf,'[^0-9]','','g'),'')WHERE cpf IS DISTINCT FROM NULLIF(regexp_replace(cpf,'[^0-9]','','g'),'');
UPDATE clientes SET cpf=NULLIF(regexp_replace(cpf,'[^0-9]','','g'),'')WHERE cpf IS DISTINCT FROM NULLIF(regexp_replace(cpf,'[^0-9]','','g'),'');
UPDATE equipe_profissionais SET cpf=NULLIF(regexp_replace(cpf,'[^0-9]','','g'),'')WHERE cpf IS DISTINCT FROM NULLIF(regexp_replace(cpf,'[^0-9]','','g'),'');
UPDATE saude_responsaveis_tecnicos SET cpf=regexp_replace(cpf,'[^0-9]','','g')WHERE cpf IS DISTINCT FROM regexp_replace(cpf,'[^0-9]','','g');
UPDATE organizacao_estabelecimentos SET cnpj=NULLIF(upper(regexp_replace(cnpj,'[^A-Za-z0-9]','','g')),'')WHERE cnpj IS DISTINCT FROM NULLIF(upper(regexp_replace(cnpj,'[^A-Za-z0-9]','','g')),'');
UPDATE organizacao_dados_receita SET cnpj=upper(regexp_replace(cnpj,'[^A-Za-z0-9]','','g'))WHERE cnpj IS DISTINCT FROM upper(regexp_replace(cnpj,'[^A-Za-z0-9]','','g'));

DO $$DECLARE constraint_name text;BEGIN
 FOR constraint_name IN SELECT conname FROM pg_constraint WHERE conrelid='organizacao_estabelecimentos'::regclass AND contype='c' AND pg_get_constraintdef(oid)ILIKE'%cnpj%' LOOP EXECUTE format('ALTER TABLE organizacao_estabelecimentos DROP CONSTRAINT %I',constraint_name);END LOOP;
 FOR constraint_name IN SELECT conname FROM pg_constraint WHERE conrelid='organizacao_dados_receita'::regclass AND contype='c' AND pg_get_constraintdef(oid)ILIKE'%cnpj%' LOOP EXECUTE format('ALTER TABLE organizacao_dados_receita DROP CONSTRAINT %I',constraint_name);END LOOP;
END$$;
ALTER TABLE organizacao_estabelecimentos ADD CONSTRAINT ck_estabelecimentos_cnpj_valido CHECK(cnpj IS NULL OR fn_cnpj_valido(cnpj));
ALTER TABLE organizacao_dados_receita ADD CONSTRAINT ck_dados_receita_cnpj_valido CHECK(fn_cnpj_valido(cnpj));

DO $$BEGIN
 IF NOT EXISTS(SELECT 1 FROM pg_constraint WHERE conrelid='acesso_usuarios'::regclass AND conname='ck_acesso_usuarios_cpf_valido')THEN ALTER TABLE acesso_usuarios ADD CONSTRAINT ck_acesso_usuarios_cpf_valido CHECK(cpf IS NULL OR fn_cpf_valido(cpf));END IF;
 IF NOT EXISTS(SELECT 1 FROM pg_constraint WHERE conrelid='clientes'::regclass AND conname='ck_clientes_cpf_valido')THEN ALTER TABLE clientes ADD CONSTRAINT ck_clientes_cpf_valido CHECK(cpf IS NULL OR fn_cpf_valido(cpf));END IF;
 IF NOT EXISTS(SELECT 1 FROM pg_constraint WHERE conrelid='equipe_profissionais'::regclass AND conname='ck_equipe_profissionais_cpf_valido')THEN ALTER TABLE equipe_profissionais ADD CONSTRAINT ck_equipe_profissionais_cpf_valido CHECK(cpf IS NULL OR fn_cpf_valido(cpf));END IF;
 IF NOT EXISTS(SELECT 1 FROM pg_constraint WHERE conrelid='saude_responsaveis_tecnicos'::regclass AND conname='ck_responsaveis_tecnicos_cpf_valido')THEN ALTER TABLE saude_responsaveis_tecnicos ADD CONSTRAINT ck_responsaveis_tecnicos_cpf_valido CHECK(fn_cpf_valido(cpf));END IF;
END$$;

CREATE OR REPLACE FUNCTION fn_normalizar_cpf()RETURNS trigger LANGUAGE plpgsql AS $$BEGIN NEW.cpf:=NULLIF(regexp_replace(NEW.cpf,'[^0-9]','','g'),'');RETURN NEW;END$$;
CREATE OR REPLACE FUNCTION fn_normalizar_cnpj()RETURNS trigger LANGUAGE plpgsql AS $$BEGIN NEW.cnpj:=NULLIF(upper(regexp_replace(NEW.cnpj,'[^A-Za-z0-9]','','g')),'');RETURN NEW;END$$;
DROP TRIGGER IF EXISTS trg_acesso_usuarios_normalizar_cpf ON acesso_usuarios;CREATE TRIGGER trg_acesso_usuarios_normalizar_cpf BEFORE INSERT OR UPDATE ON acesso_usuarios FOR EACH ROW EXECUTE FUNCTION fn_normalizar_cpf();
DROP TRIGGER IF EXISTS trg_clientes_normalizar_cpf ON clientes;CREATE TRIGGER trg_clientes_normalizar_cpf BEFORE INSERT OR UPDATE ON clientes FOR EACH ROW EXECUTE FUNCTION fn_normalizar_cpf();
DROP TRIGGER IF EXISTS trg_equipe_profissionais_normalizar_cpf ON equipe_profissionais;CREATE TRIGGER trg_equipe_profissionais_normalizar_cpf BEFORE INSERT OR UPDATE ON equipe_profissionais FOR EACH ROW EXECUTE FUNCTION fn_normalizar_cpf();
DROP TRIGGER IF EXISTS trg_responsaveis_tecnicos_normalizar_cpf ON saude_responsaveis_tecnicos;CREATE TRIGGER trg_responsaveis_tecnicos_normalizar_cpf BEFORE INSERT OR UPDATE ON saude_responsaveis_tecnicos FOR EACH ROW EXECUTE FUNCTION fn_normalizar_cpf();
DROP TRIGGER IF EXISTS trg_estabelecimentos_normalizar_cnpj ON organizacao_estabelecimentos;CREATE TRIGGER trg_estabelecimentos_normalizar_cnpj BEFORE INSERT OR UPDATE ON organizacao_estabelecimentos FOR EACH ROW EXECUTE FUNCTION fn_normalizar_cnpj();
DROP TRIGGER IF EXISTS trg_dados_receita_normalizar_cnpj ON organizacao_dados_receita;CREATE TRIGGER trg_dados_receita_normalizar_cnpj BEFORE INSERT OR UPDATE ON organizacao_dados_receita FOR EACH ROW EXECUTE FUNCTION fn_normalizar_cnpj();

INSERT INTO sistema_schema_versions(version,description,checksum_sha256)VALUES('app-010-document-integrity','CPF numérico, CNPJ alfanumérico e validação dos dígitos verificadores',NULL)ON CONFLICT(version)DO NOTHING;
COMMIT;
