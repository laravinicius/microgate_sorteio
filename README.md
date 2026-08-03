# Sorteio Microgate Informática

Projeto de sorteio interativo da **Microgate Informática**: o participante se cadastra, joga um minigame e ganha um **número da sorte** único (6 dígitos). Um número por participante.

## Estrutura

```
sorteio/
├── index.html          # Cadastro / login do participante
├── jogos.html          # Hub de seleção de minigames
├── pprush.html         # Minigame: PP Rush
├── fdefense.html       # Minigame: F Defense
├── perfil.html         # Perfil do participante (dados + número da sorte + sair)
├── admin.html          # Painel do administrador (lista de usuários + exportar CSV)
├── components/         # Header/footer compartilhados
├── css/                # Estilos (inclui output.css gerado via Tailwind)
├── js/
│   ├── components.js   # Carrega header/footer
│   ├── theme.js        # Tema claro/escuro
│   └── sorteio-api.js  # Cliente da API (fetch + sessionStorage)
├── img/
└── backend/            # API em PHP + PostgreSQL (ver backend/README.md)
    ├── config/         # database.php (ignorado pelo git) + database.example.php
    ├── lib/            # bootstrap.php (PDO + helpers JSON)
    ├── api/            # cadastro, login, atualizar-cadastro, meu-numero, iniciar/finalizar-jogo
    └── sql/schema.sql  # Script de criação do banco
```

## Requisitos

- Servidor web com PHP 8.x e extensão `pdo_pgsql`.
- PostgreSQL externo (o esquema está em `backend/sql/schema.sql`).

## Instalação

1. Clone o repositório e copie a pasta para o webroot (ex: `/var/www/sorteio`).
2. Configure o banco:
   ```bash
   psql "host=SEU_IP port=5432 dbname=postgres user=SEU_USUARIO" -f backend/sql/schema.sql
   ```
3. Configure a conexão:
   ```bash
   cp backend/config/database.example.php backend/config/database.php
   ```
   Edite `backend/config/database.php` com host/usuário/senha reais (ou use variáveis
   de ambiente — recomendado em produção). **Este arquivo não é versionado no git.**
4. Acesse `https://seu-dominio/index.html`.

Detalhes da API, endpoints e regras de negócio estão em [backend/README.md](backend/README.md).

## Regras de negócio resumidas

- 1 cadastro por e-mail e por celular.
- 1 número da sorte (6 dígitos, único) por participante — replay não gera novo número.
- Login pelo par e-mail + celular.
- Validação de tempo mínimo de jogo (5s) para evitar chamada direta à API.
