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

rwa_require_login('/?app=nuki-lock');

if (!rwa_user_can_access_app($app)) {
    rwa_render_access_denied($app);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$action = (string) ($_GET['action'] ?? '');

try {
    $result = rwa_nuki_perform_action($action);
    $status = (int) ($result['status'] ?? 0);
    if ($status >= 200 && $status < 300) {
        rwa_set_flash('success', 'Nuki-Aktion "' . rwa_nuki_action_label($action) . '" wurde erfolgreich ausgelöst.');
    } else {
        $payload = json_encode($result['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        rwa_set_flash('error', 'Nuki-Aktion fehlgeschlagen (' . $status . '): ' . $payload);
    }
} catch (Throwable $throwable) {
    rwa_set_flash('error', 'Nuki-Aktion fehlgeschlagen: ' . $throwable->getMessage());
}

rwa_redirect(rwa_url('?app=nuki-lock'));
