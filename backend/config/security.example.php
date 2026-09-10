<?php
// =====================================================================
// Configuração de segurança (rede/IPs confiáveis para rate limit).
// Copie este arquivo para `security.php` e preencha os valores reais.
// NÃO versione o `security.php` (ver .gitignore).
//
// `proxies_confiaveis` lista os IPs/CIDRs dos proxies reversos (ex.: WAF,
// nginx/Apache da frente da API) cujos headers X-Forwarded-For devem ser
// considerados na detecção do IP real do cliente. A API só confia nesses
// headers se o peer direto (REMOTE_ADDR) estiver na lista.
//
// Se o arquivo estiver ausente ou a lista estiver vazia, a API usa apenas
// REMOTE_ADDR (fail-closed: nenhum header de proxy é confiado).
// =====================================================================

return [
    'proxies_confiaveis' => [
        // '127.0.0.1',
        // '10.0.0.0/8',
        // '172.16.0.0/12',
    ],
];