# Backend - Sorteio Microgate

## Requisitos no servidor web
- PHP 8.x com as extensões `pdo_pgsql` e `mbstring` habilitadas (`php -m | grep pgsql`).
  - Debian/Ubuntu: `sudo apt install php-pgsql php-mbstring && sudo systemctl restart apache2` (ou php-fpm)
- Acesso de rede do servidor web até o servidor Postgres (porta 5432 liberada no firewall/roteador entre eles).
- Acesso HTTPS de saída até `https://oauth2.googleapis.com` (usado para validar o token do login com Google).

## Estrutura
```
backend/
├── config/
│   ├── database.php   <- preencher host, usuário, senha do Postgres
│   ├── google.php     <- preencher o Client ID do OAuth do Google
│   └── .htaccess      <- bloqueia acesso direto via navegador
├── lib/
│   ├── bootstrap.php  <- conexão PDO + helpers de resposta JSON
│   └── .htaccess
├── api/
│   ├── google-auth.php     POST -> login exclusivo com Google (valida ID token e devolve token)
│   ├── atualizar-cadastro.php POST -> atualiza dados do participante autenticado
│   ├── meu-numero.php      POST -> consulta dados e número da sorte do participante
│   ├── iniciar-jogo.php    POST -> abre sessão do minigame escolhido (permite replay)
│   ├── finalizar-jogo.php  POST -> encerra sessão e gera o número da sorte (replay não gera novo)
│   ├── excluir-conta.php   POST -> exclui participante e todos os dados (LGPD art. 18)
│   └── admin-usuarios.php  POST -> lista todos os participantes (somente administrador)
└── sql/
    ├── schema.sql          <- script de criação do banco/tabelas
    ├── migracao_admin.sql  <- adiciona is_admin e cria o cadastro do administrador
    └── migracao_google_lgpd.sql <- login com Google + consentimento LGPD (aplicar em bancos existentes)
```

## Passo a passo de instalação

1. **Banco de dados (servidor Postgres externo)**
   ```bash
   psql "host=SEU_IP port=5432 dbname=postgres user=SEU_USUARIO" -f sql/schema.sql
   ```
   Em banco já existente, aplique a migração do login com Google:
   ```bash
   psql "host=SEU_IP port=5432 dbname=sorteio_microgate user=SEU_USUARIO" -f sql/migracao_google_lgpd.sql
   ```

2. **Configuração**
   - `config/database.php`: host/usuário/senha/porta reais do Postgres.
   - **Segurança recomendada:** use variáveis de ambiente (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`,
     `DB_PASSWORD`, `DB_SSL_MODE`) configuradas no pool do PHP-FPM/Apache em vez de valores fixos
      (o `database.php` já lê essas variáveis, com fallback nos valores do arquivo). Mantenha o arquivo
      legível pelo usuário do PHP-FPM (`chmod 644`; um `chmod 600` quebra a leitura se o PHP rodar como
      outro usuário, ex.: `www-data`), crie um usuário Postgres com apenas os privilégios necessários
     (SELECT/INSERT/UPDATE/DELETE nas tabelas do sorteio) e use `sslmode=require` se o Postgres externo
     suportar SSL.
   - `config/google.php`: o Client ID do OAuth (copie de `google.example.php`). O mesmo valor
     também vai no front-end em `js/google-config.js`. Este fluxo **não usa** o client_secret.

3. **Deploy no servidor web**
   Copie a pasta `backend/` para dentro do diretório do site (ex: `/var/www/sorteio/backend/`),
   ao lado do `index.html` já publicado. Os endpoints ficam acessíveis em:
   - `https://seu-dominio/backend/api/google-auth.php`
   - `https://seu-dominio/backend/api/atualizar-cadastro.php`
   - `https://seu-dominio/backend/api/meu-numero.php`
   - `https://seu-dominio/backend/api/iniciar-jogo.php`
   - `https://seu-dominio/backend/api/finalizar-jogo.php`
   - `https://seu-dominio/backend/api/excluir-conta.php`
   - `https://seu-dominio/backend/api/admin-usuarios.php`

4. **Teste rápido**
   O login exige um ID token real do Google (gerado pelo botão "Entrar com o Google" no site),
   então o fluxo completo é testado no navegador. Para testar a validação do backend:
   ```bash
   curl -X POST https://seu-dominio/backend/api/google-auth.php \
     -H "Content-Type: application/json" \
     -d '{"credential":"ID_TOKEN_INVALIDO"}'
   # esperado: {"sucesso":false,"erro":"Login com Google não autorizado."}
   ```

## Regras de negócio já implementadas
- Login **exclusivamente** via conta Google (e-mail verificado). Sem formulário de cadastro/login.
- 1 e-mail e 1 celular por participante (constraint `UNIQUE` no banco; celular é opcional).
- Vínculo por `google_sub`: quem já tinha cadastro antigo é linkado pelo e-mail no primeiro login.
- 1 número da sorte por participante (replay não gera novo número).
- Número da sorte aleatório de 6 dígitos, único no banco (retry automático em caso de colisão).
- Validação de tempo no servidor em `finalizar-jogo.php`: a sessão precisa pertencer ao participante
  (via `participante_token`), mínimo de 5s jogados e máximo de 70s (jogos duram até 60s); sessões com
  mais de 10 minutos de idade expiram e não podem mais ser finalizadas.
- Cadastro precisa estar completo (celular obrigatório + consentimento LGPD) para iniciar o jogo.
  A etapa de conclusão (`completar.html`) também coleta empresa (opcional).
- Todas as respostas em JSON, incluindo erros (`sucesso: false` + `erro: "mensagem"`).

## LGPD
- O aceite da Política de Privacidade fica registrado em `consentimento_em` (data/hora).
- O participante pode excluir todos os dados pelo botão "Excluir meus dados e minha conta"
  (endpoint `excluir-conta.php`), atendendo ao art. 18 da LGPD.

## Administrador
- Identificado pela coluna `is_admin` na tabela `participantes`.
- Faz login com o Google normalmente; o e-mail da conta Google deve ser o mesmo do cadastro
  `is_admin = true` no banco.
- Pode jogar os minigames, mas **não** recebe número da sorte.
- Pode listar todos os usuários em `admin.html` (endpoint `admin-usuarios.php` protegido por token de admin).
- Para ativar, rode `sql/migracao_admin.sql` no banco (cria o cadastro `Administrador` com
  e-mail `ti@microgateinformatica.com.br` e celular `41991942228`).
