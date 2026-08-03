-- =====================================================================
-- Migração: Perfil de Administrador
-- Aplica em bancos existentes (sorteio_microgate).
--
-- 1) Adiciona a coluna is_admin em participantes (segura para re-rodar).
-- 2) Insere/atualiza o cadastro do administrador. Por padrão o admin
--    NÃO recebe número da sorte, mas pode jogar os minigames.
--
-- Como aplicar:
--   psql "host=SEU_IP port=5432 dbname=sorteio_microgate user=SEU_USUARIO" -f migracao_admin.sql
-- =====================================================================

ALTER TABLE participantes ADD COLUMN IF NOT EXISTS is_admin BOOLEAN NOT NULL DEFAULT FALSE;

INSERT INTO participantes (nome_completo, email, celular, empresa, is_admin)
VALUES ('Administrador', 'ti@microgateinformatica.com.br', '41991942228', 'Microgate Informática', TRUE)
ON CONFLICT (email) DO UPDATE SET is_admin = TRUE;
