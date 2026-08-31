-- =====================================================================
-- Migração: Rate limiting por IP + endpoint
-- Aplicar com:
--   psql "host=SEU_IP_SERVIDOR port=5432 dbname=sorteio_microgate user=SEU_USUARIO" \
--        -f backend/sql/migracao_rate_limits.sql
-- =====================================================================

-- Tabela para controle de rate limiting
-- Chave única em (ip, endpoint) permite upsert atômico
CREATE TABLE IF NOT EXISTS rate_limits (
    id              BIGSERIAL PRIMARY KEY,
    ip              VARCHAR(45) NOT NULL,               -- IPv4 ou IPv6
    endpoint        VARCHAR(50) NOT NULL,               -- ex: 'google-auth', 'enviar-codigo'
    contador        INT NOT NULL DEFAULT 1,
    janela_inicio   TIMESTAMPTZ NOT NULL DEFAULT now(), -- início da janela atual
    CONSTRAINT uq_rate_limits_ip_endpoint UNIQUE (ip, endpoint)
);

CREATE INDEX IF NOT EXISTS idx_rate_limits_ip_endpoint ON rate_limits (ip, endpoint);
CREATE INDEX IF NOT EXISTS idx_rate_limits_janela ON rate_limits (janela_inicio);

-- Comentário: janela_inicio marca o início da janela deslizante fixa.
-- A cada requisição, se now() - janela_inicio > window_seconds, reseta contador e janela_inicio.
-- Limpeza periódica: DELETE FROM rate_limits WHERE janela_inicio < now() - interval '24 hours';