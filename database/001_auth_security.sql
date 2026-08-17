BEGIN;
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
ALTER TABLE cadastro_usuarios ADD COLUMN IF NOT EXISTS cadastro_completo_em timestamptz;
CREATE TABLE IF NOT EXISTS sistema_autenticacao_eventos (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_usuario bigint REFERENCES cadastro_usuarios(id) ON DELETE SET NULL,
    id_estabelecimento bigint REFERENCES cadastro_estabelecimentos(id) ON DELETE SET NULL,
    evento varchar(60) NOT NULL,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE INDEX IF NOT EXISTS idx_autenticacao_eventos_usuario_data ON sistema_autenticacao_eventos(id_usuario,created_at DESC);
INSERT INTO sistema_schema_versions(version,description,checksum_sha256) VALUES('app-001-auth-security','Tentativas de login protegidas por hashes',NULL) ON CONFLICT(version) DO NOTHING;
COMMIT;
