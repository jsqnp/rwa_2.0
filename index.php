<?php

require_once __DIR__ . '/app.php';

if (!rwa_has_config()) {
    rwa_render_missing_config_page();
    return;
}

$requestedAppId = rwa_requested_app_id();
if ($requestedAppId !== null) {
    $app = rwa_get_app($requestedAppId);
    if ($app === null) {
        rwa_render_not_found('App nicht gefunden', 'Die angeforderte App ist nicht registriert.');
    }

    rwa_require_login($_SERVER['REQUEST_URI'] ?? '/');

    if (!rwa_user_can_access_app($app)) {
        rwa_render_access_denied($app);
    }

    $entryPath = $app['path'] . '/' . $app['entry'];
    if (!file_exists($entryPath)) {
        rwa_render_not_found('App-Datei fehlt', 'Der Entry-Point der App konnte nicht geladen werden.');
    }

    $rwaCurrentApp = $app;
    require $entryPath;
    return;
}

if (!rwa_is_logged_in()) {
    rwa_render_header('Login');
    ?>
    <div class="hero">
        <div class="card">
            <h1><?= rwa_escape((string) rwa_config('platform.login_headline', rwa_platform_name())) ?></h1>
            <p class="muted"><?= rwa_escape((string) rwa_config('platform.login_text', 'Melde dich an, um deine Apps zu sehen.')) ?></p>
            <p><a class="button" href="<?= rwa_escape(rwa_url('auth.php?action=login')) ?>"><?= rwa_escape((string) rwa_config('platform.login_button_label', 'Mit MiData anmelden')) ?></a></p>
        </div>
    </div>
    <?php
    rwa_render_footer();
    return;
}

$user = rwa_current_user();
$visibleApps = rwa_visible_apps($user);

rwa_render_header('Dashboard');
?>
<div class="hero">
    <div class="card">
        <h1><?= rwa_escape(rwa_platform_name()) ?></h1>
        <p class="muted"><?= rwa_escape((string) rwa_config('platform.subtitle', '')) ?></p>
        <p><?= rwa_escape((string) rwa_config('platform.dashboard_intro', '')) ?></p>
        <div class="actions muted">
            <span><?= rwa_escape($user['name'] ?? 'MiData User') ?></span>
            <?php if (($user['email'] ?? '') !== ''): ?>
                <span><?= rwa_escape($user['email']) ?></span>
            <?php endif; ?>
            <span><?= count($user['roles'] ?? []) ?> Rollen geladen</span>
            <?php if (rwa_is_debug_enabled()): ?>
                <a class="button secondary" href="<?= rwa_escape(rwa_url('?debug=roles')) ?>">Debug Rollen</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (rwa_is_debug_enabled() && ($_GET['debug'] ?? '') === 'roles'): ?>
    <div class="card" style="margin-bottom: 18px;">
        <h2>Geladene Rollen</h2>
        <?php if (($user['roles'] ?? []) === []): ?>
            <p class="muted">Keine Rollen im Session-Profil gefunden.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Rolle</th>
                        <th>Group ID</th>
                        <th>Gruppe</th>
                        <th>Layer Group ID</th>
                        <th>Layer</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($user['roles'] as $role): ?>
                    <tr>
                        <td><?= rwa_escape($role['role'] ?? '') ?></td>
                        <td><?= rwa_escape($role['group_id'] ?? '') ?></td>
                        <td><?= rwa_escape($role['group_name'] ?? '') ?></td>
                        <td><?= rwa_escape($role['layer_group_id'] ?? '') ?></td>
                        <td><?= rwa_escape($role['layer_group_name'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid">
    <?php foreach ($visibleApps as $app): ?>
        <a class="card" href="<?= rwa_escape(rwa_url('?app=' . rawurlencode($app['id']))) ?>">
            <div style="font-size: 2rem;"><?= rwa_escape($app['icon'] ?? '🧩') ?></div>
            <h2><?= rwa_escape($app['name']) ?></h2>
            <p class="muted"><?= rwa_escape($app['description'] ?? '') ?></p>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($visibleApps === []): ?>
    <div class="card" style="margin-top: 18px;">
        <h2>Keine App freigeschaltet</h2>
        <p class="muted">Für deinen Account wurde aktuell keine App freigegeben. Prüfe die Access-Rules in den App-Manifests oder die Rollen in MiData.</p>
    </div>
<?php endif; ?>
<?php
rwa_render_footer();
