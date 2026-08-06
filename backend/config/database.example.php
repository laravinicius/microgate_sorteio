<?php
// =====================================================================
// Configuração de conexão com o PostgreSQL (servidor EXTERNO ao servidor web)
// Copie este arquivo para `database.php` e preencha os valores reais.
// NÃO versione o `database.php` (ver .gitignore).
//
// RECOMENDADO: em produção, use variáveis de ambiente em vez de valores
// fixos (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_SSL_MODE),
// configuradas no Apache/Nginx/PHP-FPM, para não deixar credenciais em
// texto puro dentro do repositório/servidor.
// =====================================================================

return [
    'host'     => getenv('DB_HOST') ?: 'SEU_HOST',      // ex: 192.168.10.50 ou host.dominio.com
    'port'     => (int)(getenv('DB_PORT') ?: 5432),
    'dbname'   => getenv('DB_NAME') ?: 'sorteio_microgate',
    'user'     => getenv('DB_USER') ?: 'SEU_USUARIO',
    'password' => getenv('DB_PASSWORD') ?: 'SUA_SENHA',
    'sslmode'  => getenv('DB_SSL_MODE') ?: 'prefer', // use 'require' se o Postgres externo exigir SSL
];