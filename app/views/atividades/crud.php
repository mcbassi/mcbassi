<?php declare(strict_types=1); ?>
<section class="card"><h2>Atividades</h2><form method="post" action="/atividades/gerar"><?= \App\Security\Csrf::inputField() ?><button type="submit">Gerar</button></form></section>
