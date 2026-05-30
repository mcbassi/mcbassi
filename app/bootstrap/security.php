<?php
declare(strict_types=1);

App\Security\Headers::send();
App\Security\Csrf::boot();
