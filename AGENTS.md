# AGENTS.md

Sorteio interativo Microgate: cadastro → minigame → número da sorte único (6 dígitos). Front estático (HTML/CSS/JS) + API PHP + PostgreSQL externo.

**Idioma:** todo o código, comentários, mensagens de erro e READMEs estão em pt-BR. Mantenha esse padrão em código e mensagens novas.

## Sem tooling

Não há npm, composer, CI, testes, linter ou build. Nada de `npm install`/`yarn`/`composer`. Verificação disponível:

- `php -l backend/api/*.php backend/lib/*.php` (sintaxe)
- Teste manual de endpoints com `curl -X POST .../backend/api/<endpoint>.php -H 'Content-Type: application/json' -d '{...}'` (exemplos no `backend/README.md`)
- Conexão real exige Postgres externo configurado (ver "Config de banco" abaixo)

## Arquitetura / fluxo

- `index.html` → cadastro/login → `jogos.html` → jogo (`pprush.html` / `fdefense.html`) → `finalizar-jogo` → `perfil.html`. `admin.html` lista usuários + exporta CSV.
- Sessão/auth NÃO usa cookies: token do participante fica em `sessionStorage` (`participante_token`) e vai no **corpo** da requisição como `participante_token`. Nenhum endpoint lê cookie.
- Front fala com a API somente via cliente `js/sorteio-api.js` (const `SorteioAPI`). Reutilize-o; não faça `fetch` solto nos pages.
- Header/footer compartilhados: cada página tem `<div id="header-placeholder">` / `<div id="footer-placeholder">` preenchidos por `js/components.js`. Ao tocar em `components/header.html`, verifique a lógica em `components.js` (`data-nav-logado`, `data-nav-admin`, `data-nav-sair`).

## Convenções da API (backend/)

- Endpoints em `backend/api/*.php` são **só POST**, JSON in/out, e começam com `require __DIR__ . '/../lib/bootstrap.php';` + `exigir_post()`.
- Use os helpers de `backend/lib/bootstrap.php`: `json_input()`, `sucesso()`, `erro(status, msg)`, `get_pdo()`, `get_participante_por_token()`, `exigir_admin()`.
- Formato de resposta: `{sucesso:true, ...}` ou `{sucesso:false, erro:"..."}`. Toda mensagem de erro em pt-BR.
- Admin é autenticado por token no corpo via `exigir_admin()`.

## Regras de negócio que não podem regredir

- 1 cadastro por e-mail e por celular (`UNIQUE` no schema).
- 1 número da sorte por participante; replay **não** gera novo (sessão é criada, mas devolve o número existente).
- `finalizar-jogo` exige `segundos_jogados >= 5` e status `em_andamento` (proteção contra chamada direta à API).
- Admin joga mas **não** recebe número (`finalizar-jogo.php` retorna `admin: true`).
- Jogos válidos são whitelist hardcoded: `firewall_defense` e `patch_panel_rush` (também CHECK no schema).

## Config de banco (gotcha)

- `backend/config/database.php` **não é versionado** (gitignored) e é o único arquivo de conexão; `bootstrap.php` faz `require` dele. Se faltar, toda a API retorna 503.
- Para criar local: `cp backend/config/database.example.php backend/config/database.php` e preencher. Banco é PostgreSQL externo (`pdo_pgsql`).
- Ao mexer no schema: altere `backend/sql/schema.sql` (fonte da verdade) **e** adicione/atualize migração em `backend/sql/` (ex: `migracao_admin.sql`). Aplicar com `psql "host=... port=5432 dbname=sorteio_microgate user=..." -f backend/sql/schema.sql`.

## CSS/Tailwind (gotcha)

- `css/output.css` é saída gerada do Tailwind, mas **não há** config/scripts de build no repositório (sem `tailwind.config.js`, sem package.json). Não edite `output.css` diretamente e não espere regenerar classes Tailwind aqui — o build é feito fora do repo.
- Estilos por página ficam em `css/*.css` (ex: `index.css`, `pprush.css`, `games.css`, `style.css` para ajustes compartilhados). Edite nesses arquivos.

## Estrutura de referência

- `components/` — header/footer compartilhados
- `css/`, `js/`, `img/` — front estático
- `backend/api/` — endpoints PHP (cadastro, login, atualizar-cadastro, meu-numero, iniciar/finalizar-jogo, admin-usuarios)
- `backend/sql/` — `schema.sql` + `migracao_admin.sql`
