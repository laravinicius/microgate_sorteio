<?php
// =====================================================================
// Configuração de e-mail (SMTP Hostinger)
// Copie este arquivo para `email.php` e preencha os valores reais.
// NÃO versione o `email.php` (ver .gitignore).
//
// RECOMENDADO: em produção, use variáveis de ambiente (EMAIL_HOST,
// EMAIL_PORT, EMAIL_USER, EMAIL_PASSWORD, EMAIL_FROM, EMAIL_FROM_NAME)
// configuradas no Apache/Nginx/PHP-FPM, para não deixar credenciais em
// texto puro dentro do repositório/servidor.
// =====================================================================

return [
    'host'       => getenv('EMAIL_HOST') ?: 'smtp.hostinger.com.br',
    'port'       => (int)(getenv('EMAIL_PORT') ?: 465),
    'username'   => getenv('EMAIL_USER') ?: 'service@microgateinformatica.com.br',
    'password'   => getenv('EMAIL_PASSWORD') ?: 'SUA_SENHA_AQUI',
    'from_email' => getenv('EMAIL_FROM') ?: 'service@microgateinformatica.com.br',
    'from_name'  => getenv('EMAIL_FROM_NAME') ?: 'Sorteio Microgate',
    'ssl'        => true,  // porta 465 = SSL implícito
];