<?php

require_once dirname(__DIR__, 2) . '/app.php';

if (!rwa_has_config()) {
    rwa_render_missing_config_page();
    return;
}

if (isset($_GET['error'])) {
    rwa_set_flash('error', 'MiData Login fehlgeschlagen: ' . (string) $_GET['error']);
    rwa_redirect(rwa_url());
}

$state = (string) ($_GET['state'] ?? '');
if ($state === '' || !hash_equals((string) ($_SESSION['rwa_oauth_state'] ?? ''), $state)) {
    rwa_set_flash('error', 'Ungültiger OAuth-Status. Bitte erneut anmelden.');
    rwa_redirect(rwa_url());
}

unset($_SESSION['rwa_oauth_state']);

$code = (string) ($_GET['code'] ?? '');
if ($code === '') {
    rwa_set_flash('error', 'Im OAuth-Callback wurde kein Code geliefert.');
    rwa_redirect(rwa_url());
}

try {
    $tokens = rwa_exchange_code_for_token($code);
    $profile = rwa_fetch_remote_user($tokens);
    rwa_store_authenticated_user($profile, $tokens);
    rwa_set_flash('success', 'Anmeldung mit MiData erfolgreich.');
} catch (Throwable $throwable) {
    rwa_set_flash('error', 'Anmeldung fehlgeschlagen: ' . $throwable->getMessage());
    rwa_redirect(rwa_url());
}

$redirectTarget = rwa_normalize_redirect_target((string) ($_SESSION['rwa_post_login_redirect'] ?? '/'));
unset($_SESSION['rwa_post_login_redirect']);

rwa_redirect(rwa_url(ltrim($redirectTarget, '/')));
