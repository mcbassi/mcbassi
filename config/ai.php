<?php
declare(strict_types=1);

use App\Support\Env;

return [
    'openai_api_key' => Env::get('OPENAI_API_KEY', ''),
    'openai_model' => Env::get('OPENAI_MODEL', 'gpt-4.1-mini'),
];
