-- =====================================================================
-- Migração: login exclusivo com Google + adequação LGPD
-- Aplicar com:
--   psql "host=SEU_IP_SERVIDOR port=5432 dbname=sorteio_microgate user=SEU_USUARIO" \
--        -f backend/sql/migracao_google_lgpd.sql
-- =====================================================================

-- Identificador da conta Google (sub). Mantém o vínculo mesmo se o e-mail mudar no Google.
ALTER TABLE participantes
    ADD COLUMN IF NOT EXISTS google_sub VARCHAR(128);

-- Data/hora do aceite da Política de Privacidade (evidência de consentimento - LGPD art. 7º, I).
ALTER TABLE participantes
    ADD COLUMN IF NOT EXISTS consentimento_em TIMESTAMPTZ;

-- Celular deixa de ser obrigatório: o Google não informa telefone.
ALTER TABLE participantes
    ALTER COLUMN celular DROP NOT NULL;

-- Um único vínculo por conta Google (múltiplos NULL são permitidos no Postgres).
CREATE UNIQUE INDEX IF NOT EXISTS uq_participantes_google_sub
    ON participantes (google_sub);
