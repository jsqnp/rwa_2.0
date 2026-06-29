<?php

$app = $rwaCurrentApp ?? rwa_get_app('nuki-lock');
rwa_render_header($app['name'], $app);
?>
<div class="card" style="margin-bottom: 18px;">
    <h1><?= rwa_escape($app['icon'] ?? '🔐') ?> <?= rwa_escape($app['name']) ?></h1>
    <p class="muted"><?= rwa_escape($app['description'] ?? '') ?></p>
    <?php if (!rwa_nuki_config_is_complete()): ?>
        <div class="flash info">
            Die Nuki-Konfiguration ist noch unvollständig. Ergänze <code>nuki.api_base_url</code>, <code>nuki.api_token</code> und <code>nuki.smartlock_id</code> in <code>config.php</code>.
        </div>
    <?php endif; ?>
    <div class="actions">
        <form method="post" action="<?= rwa_escape(rwa_url('apps/nuki-lock/action.php?action=unlock')) ?>">
            <button type="submit">Tür öffnen</button>
        </form>
        <form method="post" action="<?= rwa_escape(rwa_url('apps/nuki-lock/action.php?action=lock')) ?>">
            <button class="button secondary" type="submit">Tür schliessen</button>
        </form>
        <?php if (rwa_is_debug_enabled()): ?>
            <a class="button secondary" href="<?= rwa_escape(rwa_url('apps/nuki-lock/debug.php')) ?>">Debug Rollenansicht</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>Wie der Zugriff geprüft wird</h2>
    <p class="muted">Diese App verwendet die gemeinsamen Access-Rules aus ihrem Manifest. Rollen, Gruppen und Untergruppen werden über dieselbe MiData-Session geprüft wie auf dem Dashboard.</p>
</div>
<?php
rwa_render_footer();
