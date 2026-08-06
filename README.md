# Sorteio Microgate Informática

Projeto de sorteio interativo da **Microgate Informática**: o participante faz login com a conta **Google**, joga um minigame e ganha um **número da sorte** único (6 dígitos). Um número por participante.

## Estrutura

```
sorteio/
├── index.html          # Login com Google (única forma de autenticação)
├── completar.html      # Novo usuário: celular (obrigatório) + empresa (opcional) + consentimento LGPD
├── jogos.html          # Hub de seleção de minigames
├── pprush.html         # Minigame: PP Rush
├── fdefense.html       # Minigame: F Defense
├── perfil.html         # Perfil (dados + número da sorte + excluir dados)
├── privacidade.html    # Política de Privacidade (LGPD)
├── admin.html          # Painel do administrador (lista de usuários + exportar CSV)
├── components/         # Header/footer compartilhados
├── css/                # Estilos (inclui output.css gerado via Tailwind)
├── js/
│   ├── components.js   # Carrega header/footer
│   ├── theme.js        # Tema claro/escuro
│   ├── google-config.js# Client ID público do OAuth do Google
│   ├── google-auth.js  # Botão "Entrar com o Google" (Google Identity Services)
│   └── sorteio-api.js  # Cliente da API (fetch + sessionStorage)
├── img/
└── backend/            # API em PHP + PostgreSQL (ver backend/README.md)
    ├── config/         # database.php + google.php (ignorados pelo git) + exemplos
    ├── lib/            # bootstrap.php (PDO + helpers JSON)
    ├── api/            # google-auth, atualizar-cadastro, meu-numero, iniciar/finalizar-jogo, excluir-conta, admin-usuarios
    └── sql/            # schema.sql + migrações
```

## Requisitos

- Servidor web com PHP 8.x e extensão `pdo_pgsql`.
- PostgreSQL externo (o esquema está em `backend/sql/schema.sql`).
- OAuth do Google configurado (Client ID web com a origem do site autorizada) — ver `backend/README.md`.

## Instalação

1. Clone o repositório e copie a pasta para o webroot (ex: `/var/www/sorteio`).
2. Configure o banco:
   ```bash
   psql "host=SEU_IP port=5432 dbname=postgres user=SEU_USUARIO" -f backend/sql/schema.sql
   ```
3. Configure a conexão e o Google:
   ```bash
   cp backend/config/database.example.php backend/config/database.php
   cp backend/config/google.example.php backend/config/google.php
   ```
   Edite `backend/config/database.php` com host/usuário/senha reais e `backend/config/google.php`
   com o Client ID do OAuth. **Estes arquivos não são versionados no git.**
4. Acesse `https://seu-dominio/index.html`.

Detalhes da API, endpoints e regras de negócio estão em [backend/README.md](backend/README.md).

## Regras de negócio resumidas

- Login exclusivamente via conta Google (e-mail verificado).
- 1 e-mail e 1 celular por participante (celular é opcional).
- 1 número da sorte (6 dígitos, único) por participante — replay não gera novo número.
- Cadastro precisa estar completo (celular + consentimento LGPD) para jogar.
- Validação de tempo mínimo de jogo (5s) para evitar chamada direta à API.
