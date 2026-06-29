<?php

require_once dirname(__DIR__, 2) . '/app.php';

if (!rwa_has_config()) {
    rwa_render_missing_config_page();
    return;
}

$app = rwa_get_app('nuki-lock');
if ($app === null) {
    rwa_render_not_found('App nicht gefunden', 'Die Nuki-App ist nicht registriert.');
}

rwa_require_login('/apps/nuki-lock/debug.php');

if (!rwa_is_debug_enabled()) {
    rwa_render_access_denied($app);
}

if (!rwa_user_can_access_app($app)) {
    rwa_render_access_denied($app);
}

$user = rwa_current_user();
$cache = rwa_load_persistent_group_cache();

rwa_render_header('Nuki Debug', $app);
?>
<div class="card" style="margin-bottom: 18px;">
    <h1>Debug Rollenansicht</h1>
    <p class="muted">Session-Rollen und persistenter Gruppenhierarchie-Cache für die Nuki-App.</p>
</div>

<div class="card" style="margin-bottom: 18px;">
    <h2>Rollen</h2>
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
        <?php foreach (($user['roles'] ?? []) as $role): ?>
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
</div>

<div class="card">
    <h2>Persistenter Cache</h2>
    <?php if (($cache['groups'] ?? []) === []): ?>
        <p class="muted">Noch keine Gruppenhierarchien im persistenten Cache gespeichert.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Root Group ID</th>
                    <th>Ablauf</th>
                    <th>IDs</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cache['groups'] as $rootGroupId => $entry): ?>
                <tr>
                    <td><?= rwa_escape($rootGroupId) ?></td>
                    <td><?= rwa_escape(date('c', (int) ($entry['expires_at'] ?? 0))) ?></td>
                    <td><code><?= rwa_escape(implode(', ', $entry['ids'] ?? [])) ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
rwa_render_footer();
