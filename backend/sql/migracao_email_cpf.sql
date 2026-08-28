-- =====================================================================
-- Migração: login com e-mail + CPF obrigatório
-- Aplicar com:
--   psql "host=SEU_IP_SERVIDOR port=5432 dbname=sorteio_microgate user=SEU_USUARIO" \
--        -f backend/sql/migracao_email_cpf.sql
-- =====================================================================

-- CPF obrigatório (único por pessoa). Normalizado: apenas dígitos (11 chars).
ALTER TABLE participantes
    ADD COLUMN IF NOT EXISTS cpf VARCHAR(11) UNIQUE;

-- Tabela de códigos de verificação por e-mail
CREATE TABLE IF NOT EXISTS codigos_verificacao (
    id              BIGSERIAL PRIMARY KEY,
    email           VARCHAR(150) NOT NULL,
    codigo          VARCHAR(6) NOT NULL,           -- 6 dígitos
    expira_em       TIMESTAMPTZ NOT NULL,          -- ex: now() + interval '10 minutes'
    tentativas      INT NOT NULL DEFAULT 0,        -- máx 3
    usado_em        TIMESTAMPTZ,                   -- NULL = não usado
    criado_em       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_codigos_email ON codigos_verificacao (email);
CREATE INDEX IF NOT EXISTS idx_codigos_expira ON codigos_verificacao (expira_em);

-- Comentário: CPF armazenado sem formatação (apenas 11 dígitos).
-- A aplicação formata para exibição (000.000.000-00) e normaliza na entrada.