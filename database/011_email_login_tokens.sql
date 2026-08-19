CREATE TABLE IF NOT EXISTS acesso_tokens_login(
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 id_usuario bigint NOT NULL REFERENCES acesso_usuarios(id) ON DELETE CASCADE,
 token_hash char(64) NOT NULL UNIQUE,
 finalidade varchar(30) NOT NULL,
 criado_em timestamptz NOT NULL DEFAULT NOW(),
 expira_em timestamptz NOT NULL,
 usado_em timestamptz NULL,
 CONSTRAINT ck_acesso_tokens_login_finalidade CHECK(finalidade IN('login'))
);
CREATE INDEX IF NOT EXISTS idx_acesso_tokens_login_hash ON acesso_tokens_login(token_hash);
CREATE INDEX IF NOT EXISTS idx_acesso_tokens_login_expira ON acesso_tokens_login(expira_em) WHERE usado_em IS NULL;
