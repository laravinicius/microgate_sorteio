-- =====================================================================
-- Projeto: Sorteio Microgate - Minigame + Cadastro
-- Banco: PostgreSQL (hospedado em servidor externo ao servidor web)
-- =====================================================================
-- Como aplicar:
--   psql "host=SEU_IP_SERVIDOR port=5432 dbname=postgres user=SEU_USUARIO" -f schema.sql
-- (o script cria o banco "sorteio_microgate" e depois as tabelas dentro dele)
-- =====================================================================

-- 1) Criação do banco (rodar conectado a um banco existente, ex: postgres)
-- Descomente se for criar o banco agora:
-- CREATE DATABASE sorteio_microgate WITH ENCODING 'UTF8' LC_COLLATE='pt_BR.UTF-8' LC_CTYPE='pt_BR.UTF-8' TEMPLATE=template0;

-- A partir daqui, conecte-se ao banco sorteio_microgate:
-- \c sorteio_microgate

-- Extensão necessária para gerar UUIDs (tokens de participante/sessão)
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ---------------------------------------------------------------------
-- Tabela: participantes
-- Um registro por pessoa cadastrada via formulário inicial.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS participantes (
    id              BIGSERIAL PRIMARY KEY,
    token           UUID NOT NULL DEFAULT gen_random_uuid(),  -- usado pelo front-end como credencial da sessão
    nome_completo   VARCHAR(150) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    celular         VARCHAR(20)  NOT NULL,
    empresa         VARCHAR(150),
    ip_origem       VARCHAR(45),
    user_agent      VARCHAR(255),
    criado_em       TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_participantes_token   UNIQUE (token),
    CONSTRAINT uq_participantes_email   UNIQUE (email),
    CONSTRAINT uq_participantes_celular UNIQUE (celular)
);

CREATE INDEX IF NOT EXISTS idx_participantes_email ON participantes (email);

-- ---------------------------------------------------------------------
-- Tabela: sessoes_jogo
-- Criada quando o participante escolhe um jogo e clica em iniciar.
-- Serve para validar, no fim do jogo, que a partida realmente ocorreu.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessoes_jogo (
    id               BIGSERIAL PRIMARY KEY,
    token            UUID NOT NULL DEFAULT gen_random_uuid(),
    participante_id  BIGINT NOT NULL REFERENCES participantes(id) ON DELETE CASCADE,
    jogo             VARCHAR(30) NOT NULL CHECK (jogo IN ('firewall_defense', 'patch_panel_rush')),
    status           VARCHAR(20) NOT NULL DEFAULT 'em_andamento'
                         CHECK (status IN ('em_andamento', 'concluido', 'expirado')),
    iniciado_em      TIMESTAMPTZ NOT NULL DEFAULT now(),
    finalizado_em    TIMESTAMPTZ,
    CONSTRAINT uq_sessoes_jogo_token UNIQUE (token)
);

CREATE INDEX IF NOT EXISTS idx_sessoes_jogo_participante ON sessoes_jogo (participante_id);

-- ---------------------------------------------------------------------
-- Tabela: numeros_sorte
-- Um número por participante (um único jogo/tentativa conta para o sorteio).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS numeros_sorte (
    id               BIGSERIAL PRIMARY KEY,
    participante_id  BIGINT NOT NULL REFERENCES participantes(id) ON DELETE CASCADE,
    sessao_jogo_id   BIGINT NOT NULL REFERENCES sessoes_jogo(id) ON DELETE CASCADE,
    jogo             VARCHAR(30) NOT NULL,
    numero           VARCHAR(6) NOT NULL,
    pontuacao        INTEGER NOT NULL DEFAULT 0,
    gerado_em        TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_numeros_sorte_numero      UNIQUE (numero),        -- garante não repetir no banco
    CONSTRAINT uq_numeros_sorte_participante UNIQUE (participante_id) -- garante 1 número por pessoa
);

CREATE INDEX IF NOT EXISTS idx_numeros_sorte_numero ON numeros_sorte (numero);

-- ---------------------------------------------------------------------
-- View auxiliar para exportar a lista final do sorteio (usar no dia do sorteio)
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW vw_lista_sorteio AS
SELECT
    ns.numero,
    p.nome_completo,
    p.email,
    p.celular,
    p.empresa,
    ns.jogo,
    ns.pontuacao,
    ns.gerado_em
FROM numeros_sorte ns
JOIN participantes p ON p.id = ns.participante_id
ORDER BY ns.gerado_em;
