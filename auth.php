<?php

require_once __DIR__ . '/app.php';

if (!rwa_has_config()) {
    rwa_render_missing_config_page();
    return;
}

$action = $_GET['action'] ?? 'login';

if ($action === 'logout') {
    rwa_logout();
    rwa_set_flash('success', 'Du wurdest erfolgreich abgemeldet.');
    rwa_redirect(rwa_url());
}

rwa_start_login_flow($_GET['redirect'] ?? null);
