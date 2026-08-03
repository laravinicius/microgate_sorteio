# Backend - Sorteio Microgate

## Requisitos no servidor web
- PHP 8.x com a extensão `pdo_pgsql` habilitada (`php -m | grep pgsql`).
  - Debian/Ubuntu: `sudo apt install php-pgsql && sudo systemctl restart apache2` (ou php-fpm)
- Acesso de rede do servidor web até o servidor Postgres (porta 5432 liberada no firewall/roteador entre eles).

## Estrutura
```
backend/
├── config/
│   ├── database.php   <- preencher host, usuário, senha do Postgres
│   └── .htaccess       <- bloqueia acesso direto via navegador
├── lib/
│   ├── bootstrap.php   <- conexão PDO + helpers de resposta JSON
│   └── .htaccess
├── api/
│   ├── cadastro.php        POST -> cadastra participante (devolve token)
│   ├── login.php           POST -> autentica participante existente (e-mail + celular)
│   ├── atualizar-cadastro.php POST -> atualiza dados do participante autenticado
│   ├── meu-numero.php      POST -> consulta dados e número da sorte do participante
│   ├── iniciar-jogo.php    POST -> abre sessão do minigame escolhido (permite replay)
│   └── finalizar-jogo.php  POST -> encerra sessão e gera o número da sorte (replay não gera novo)
│   └── admin-usuarios.php POST -> lista todos os participantes (somente administrador)
└── sql/
    ├── schema.sql      <- script de criação do banco/tabelas
    └── migracao_admin.sql <- adiciona is_admin e cria o cadastro do administrador
```

## Passo a passo de instalação

1. **Banco de dados (servidor Postgres externo)**
   ```bash
   psql "host=SEU_IP port=5432 dbname=postgres user=SEU_USUARIO" -f sql/schema.sql
   ```
   Ajuste o `CREATE DATABASE` (comentado no início do script) conforme o nome que preferir,
   ou peça para o time de infra criar o banco `sorteio_microgate` antes.

2. **Configuração**
   Edite `config/database.php` com host/usuário/senha/porta reais do Postgres.
   Idealmente, mova esse arquivo para fora do webroot público, ou use variáveis de
   ambiente do próprio Apache/PHP-FPM em vez de valores fixos no arquivo.

3. **Deploy no servidor web**
   Copie a pasta `backend/` para dentro do diretório do site (ex: `/var/www/sorteio/backend/`),
   ao lado do `index.html` já publicado. Os endpoints ficam acessíveis em:
   - `https://seu-dominio/backend/api/cadastro.php`
   - `https://seu-dominio/backend/api/login.php`
   - `https://seu-dominio/backend/api/atualizar-cadastro.php`
   - `https://seu-dominio/backend/api/meu-numero.php`
   - `https://seu-dominio/backend/api/iniciar-jogo.php`
   - `https://seu-dominio/backend/api/finalizar-jogo.php`
   - `https://seu-dominio/backend/api/admin-usuarios.php`

4. **Teste rápido**
   ```bash
   curl -X POST https://seu-dominio/backend/api/cadastro.php \
     -H "Content-Type: application/json" \
     -d '{"nome":"Teste da Silva","email":"teste@teste.com","celular":"41999998888"}'

   curl -X POST https://seu-dominio/backend/api/login.php \
     -H "Content-Type: application/json" \
     -d '{"email":"teste@teste.com","celular":"41999998888"}'
   ```

## Regras de negócio já implementadas
- 1 cadastro por e-mail e por celular (constraint `UNIQUE` no banco).
- 1 número da sorte por participante (replay não gera novo número).
- Login pelo par e-mail + celular (ambos precisam pertencer ao mesmo cadastro).
- Número da sorte aleatório de 6 dígitos, único no banco (retry automático em caso de colisão).
- Validação de tempo mínimo jogado (5s) para dificultar chamada direta à API sem jogar.
- Todas as respostas em JSON, incluindo erros (`sucesso: false` + `erro: "mensagem"`).

## Administrador
- Identificado pela coluna `is_admin` na tabela `participantes`.
- Pode jogar os minigames, mas **não** recebe número da sorte.
- Pode listar todos os usuários em `admin.html` (endpoint `admin-usuarios.php` protegido por token de admin).
- Para ativar, rode `sql/migracao_admin.sql` no banco (cria o cadastro `Administrador` com
  e-mail `ti@microgateinformatica.com.br` e celular `41991942228`).
