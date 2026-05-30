<?php declare(strict_types=1); ?>
<section class="activity-page">
    <div class="activity-alert activity-alert--error">
        <strong><?= h(t('activity.load_error_title')) ?></strong><br>
        <?= h((string) ($message ?? t('activity.unknown_error'))) ?>
    </div>
</section>
