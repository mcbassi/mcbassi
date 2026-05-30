<?php
declare(strict_types=1);

return [
    'main_db' => [
        // Ajuste para a base do sistema principal
        'dsn'  => 'mysql:host=127.0.0.1;dbname=form_app;charset=utf8mb4',
        'user' => 'root',
        'pass' => '',
    ],

    'generator' => [
        // API do gerador de PPT já validado
        'base_url' => 'http://localhost/ppt_dynamic_builder/api',
        'bearer_token' => '',
        'timeout_seconds' => 180,
        // Se true, ignora o gerador externo e usa o gerador local integrado.
        'local_only' => false,

        // Pasta física do output do gerador (usada no proxy de download)
        'output_root' => 'C:/xampp/htdocs/ppt_dynamic_builder/output',
    ],

    'default_values' => [
        'user_id' => 1,
        'metric_year' => 2024,
        'industry_name' => 'Advertising',
    ],

    'presentation_options' => [
        ['name' => 'TESTE_DIAGNOSTICO', 'label' => 'Teste Diagnóstico'],
    ],

    // Quantidade máxima de sessões carregadas na tela
    'sessions_limit' => 200,
];
