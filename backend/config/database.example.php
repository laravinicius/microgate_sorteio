<?php
// =====================================================================
// Configuração de conexão com o PostgreSQL (servidor EXTERNO ao servidor web)
// Copie este arquivo para `database.php` e preencha os valores reais.
// NÃO versione o `database.php` (ver .gitignore).
//
// RECOMENDADO: em produção, substitua os valores fixos por variáveis de
// ambiente (getenv('DB_HOST') etc.) configuradas no Apache/Nginx/PHP-FPM,
// para não deixar credenciais em texto puro dentro do repositório.
// =====================================================================

return [
    'host'     => 'SEU_HOST',      // ex: 192.168.10.50 ou host.dominio.com
    'port'     => 5432,
    'dbname'   => 'sorteio_microgate',
    'user'     => 'SEU_USUARIO',
    'password' => 'SUA_SENHA',
    'sslmode'  => 'prefer', // use 'require' se o Postgres externo exigir SSL
];