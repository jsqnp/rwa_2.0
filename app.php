<?php

$rwaConfigFile = __DIR__ . '/config.php';
$rwaConfig = file_exists($rwaConfigFile) ? require $rwaConfigFile : [];

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function rwa_config(string $path = '', mixed $default = null): mixed
{
    global $rwaConfig;

    if ($path === '') {
        return $rwaConfig;
    }

    $value = $rwaConfig;
    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function rwa_has_config(): bool
{
    return rwa_config() !== [];
}

function rwa_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rwa_platform_name(): string
{
    return (string) rwa_config('platform.name', 'RWA 2.0');
}

function rwa_platform_base_url(): string
{
    $configured = rtrim((string) rwa_config('platform.base_url', ''), '/');
    if ($configured !== '') {
        return $configured;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $directory = trim(str_replace('\\', '/', dirname($scriptName)), '/');

    return $directory === '' ? sprintf('%s://%s', $scheme, $host) : sprintf('%s://%s/%s', $scheme, $host, $directory);
}

function rwa_url(string $path = ''): string
{
    $base = rwa_platform_base_url();
    $cleanPath = ltrim($path, '/');

    return $cleanPath === '' ? $base . '/' : $base . '/' . $cleanPath;
}

function rwa_redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function rwa_set_flash(string $type, string $message): void
{
    $_SESSION['rwa_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function rwa_get_flash(): ?array
{
    if (!isset($_SESSION['rwa_flash'])) {
        return null;
    }

    $flash = $_SESSION['rwa_flash'];
    unset($_SESSION['rwa_flash']);

    return is_array($flash) ? $flash : null;
}

function rwa_is_logged_in(): bool
{
    return isset($_SESSION['rwa_user']) && is_array($_SESSION['rwa_user']);
}

function rwa_current_user(): ?array
{
    return rwa_is_logged_in() ? $_SESSION['rwa_user'] : null;
}

function rwa_require_login(?string $redirectTarget = null): void
{
    if (!rwa_is_logged_in()) {
        $target = $redirectTarget ?? ($_SERVER['REQUEST_URI'] ?? '/');
        rwa_redirect(rwa_url('auth.php?action=login&redirect=' . rawurlencode($target)));
    }
}

function rwa_logout(): void
{
    unset($_SESSION['rwa_user'], $_SESSION['rwa_tokens'], $_SESSION['rwa_group_hierarchy_session_cache'], $_SESSION['rwa_oauth_state'], $_SESSION['rwa_post_login_redirect']);
    session_regenerate_id(true);
}

function rwa_is_debug_enabled(): bool
{
    return (bool) rwa_config('debug', false);
}

function rwa_http_request(string $method, string $url, array $headers = [], array|string|null $bodyPayload = null, ?string $basicAuth = null, ?string $bearerToken = null, ?int $timeout = null, ?string $bodyFormat = null): array
{
    $timeout ??= (int) rwa_config('auth.timeout', 20);
    $method = strtoupper($method);
    $requestHeaders = $headers;

    if ($bearerToken !== null && $bearerToken !== '') {
        $requestHeaders[] = 'Authorization: Bearer ' . $bearerToken;
    }

    $body = null;
    if (is_array($bodyPayload)) {
        if ($bodyFormat === 'form') {
            $body = http_build_query($bodyPayload);
            $requestHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
        } else {
            $body = json_encode($bodyPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $requestHeaders[] = 'Content-Type: application/json';
        }
    } elseif (is_string($bodyPayload) && $bodyPayload !== '') {
        $body = $bodyPayload;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HEADER => true,
        ]);

        if ($basicAuth !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $rawResponse = curl_exec($ch);
        if ($rawResponse === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $headerLength = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $responseHeaders = substr($rawResponse, 0, $headerLength);
        $responseBody = substr($rawResponse, $headerLength);
        curl_close($ch);

        return [
            'status' => $statusCode,
            'headers' => $responseHeaders,
            'body' => $responseBody,
        ];
    }

    $contextOptions = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $requestHeaders),
            'ignore_errors' => true,
            'timeout' => $timeout,
        ],
    ];
    if ($basicAuth !== null) {
        $contextOptions['http']['header'] .= "\r\nAuthorization: Basic " . base64_encode($basicAuth);
    }
    if ($body !== null) {
        $contextOptions['http']['content'] = $body;
    }

    $context = stream_context_create($contextOptions);
    $responseBody = file_get_contents($url, false, $context);
    $responseHeaders = isset($http_response_header) ? implode("\n", $http_response_header) : '';
    preg_match('/\s(\d{3})\s/', $responseHeaders, $matches);

    return [
        'status' => isset($matches[1]) ? (int) $matches[1] : 0,
        'headers' => $responseHeaders,
        'body' => $responseBody === false ? '' : $responseBody,
    ];
}

function rwa_build_oauth_redirect_uri(): string
{
    $configured = trim((string) rwa_config('auth.redirect_uri', ''));
    if ($configured !== '') {
        return $configured;
    }

    return rwa_url('auth/midata');
}

function rwa_normalize_redirect_target(?string $target): string
{
    if (!is_string($target) || $target === '') {
        return '/';
    }

    if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://') || str_contains($target, "\n")) {
        return '/';
    }

    if (!str_starts_with($target, '/')) {
        return '/' . ltrim($target, '/');
    }

    return $target;
}

function rwa_start_login_flow(?string $redirectTarget = null): never
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['rwa_oauth_state'] = $state;

    if ($redirectTarget !== null && $redirectTarget !== '') {
        $_SESSION['rwa_post_login_redirect'] = rwa_normalize_redirect_target($redirectTarget);
    }

    $query = http_build_query([
        'client_id' => rwa_config('auth.client_id'),
        'redirect_uri' => rwa_build_oauth_redirect_uri(),
        'response_type' => 'code',
        'scope' => rwa_config('auth.scope', ''),
        'state' => $state,
    ]);

    rwa_redirect((string) rwa_config('auth.authorize_url') . '?' . $query);
}

function rwa_exchange_code_for_token(string $code): array
{
    $response = rwa_http_request(
        'POST',
        (string) rwa_config('auth.token_url'),
        ['Accept: application/json'],
        [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => rwa_build_oauth_redirect_uri(),
        ],
        rwa_config('auth.client_id') . ':' . rwa_config('auth.client_secret'),
        null,
        null,
        'form'
    );

    $decoded = json_decode($response['body'], true);
    if (!is_array($decoded) || empty($decoded['access_token'])) {
        throw new RuntimeException('OAuth token response did not contain an access token.');
    }

    return $decoded;
}

function rwa_fetch_remote_user(array $tokens): array
{
    $accessToken = (string) ($tokens['access_token'] ?? '');
    $profileResponse = rwa_http_request(
        'GET',
        (string) rwa_config('auth.profile_url'),
        ['Accept: application/json'],
        null,
        null,
        $accessToken
    );

    $profile = json_decode($profileResponse['body'], true);
    if (!is_array($profile)) {
        throw new RuntimeException('Could not decode MiData profile response.');
    }

    $rolesUrl = rwa_config('auth.roles_url');
    if (is_string($rolesUrl) && $rolesUrl !== '') {
        $rolesResponse = rwa_http_request(
            'GET',
            $rolesUrl,
            ['Accept: application/json'],
            null,
            null,
            $accessToken
        );
        $rolesPayload = json_decode($rolesResponse['body'], true);
        if (is_array($rolesPayload)) {
            $profile['roles_payload'] = $rolesPayload;
        }
    }

    return $profile;
}

function rwa_extract_first_value(array $payload, array $paths, mixed $default = null): mixed
{
    foreach ($paths as $path) {
        $segments = explode('.', $path);
        $value = $payload;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                continue 2;
            }

            $value = $value[$segment];
        }

        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    return $default;
}

function rwa_normalize_roles(array $payload): array
{
    $roleCollections = [];

    foreach (['roles', 'roles_payload.roles', 'person.roles', 'person.roles_payload.roles'] as $path) {
        $value = rwa_extract_first_value($payload, [$path], []);
        if (is_array($value)) {
            $roleCollections[] = $value;
        }
    }

    if ($roleCollections === []) {
        return [];
    }

    $normalized = [];
    foreach ($roleCollections as $collection) {
        foreach ($collection as $role) {
            if (!is_array($role)) {
                continue;
            }

            $name = (string) rwa_extract_first_value($role, ['label', 'name', 'role', 'role_name'], '');
            $groupId = (int) rwa_extract_first_value($role, ['group_id', 'group.id'], 0);
            $layerGroupId = (int) rwa_extract_first_value($role, ['layer_group_id', 'layer_group.id', 'layer.id'], 0);

            $normalized[] = [
                'role' => $name,
                'group_id' => $groupId,
                'group_name' => (string) rwa_extract_first_value($role, ['group_name', 'group.name'], ''),
                'layer_group_id' => $layerGroupId,
                'layer_group_name' => (string) rwa_extract_first_value($role, ['layer_group_name', 'layer_group.name', 'layer.name'], ''),
            ];
        }
    }

    $unique = [];
    foreach ($normalized as $role) {
        $key = implode(':', [$role['role'], $role['group_id'], $role['layer_group_id']]);
        $unique[$key] = $role;
    }

    return array_values($unique);
}

function rwa_store_authenticated_user(array $profile, array $tokens): void
{
    $user = [
        'id' => rwa_extract_first_value($profile, ['id', 'person.id', 'sub'], ''),
        'name' => (string) rwa_extract_first_value($profile, ['nickname', 'name', 'person.name', 'person.nickname', 'preferred_username'], 'MiData User'),
        'email' => (string) rwa_extract_first_value($profile, ['email', 'person.email'], ''),
        'provider' => (string) rwa_config('auth.provider_name', 'MiData / Hitobito'),
        'avatar_url' => (string) rwa_extract_first_value($profile, ['picture', 'avatar_url', 'person.picture'], ''),
        'roles' => rwa_normalize_roles($profile),
        'profile' => $profile,
    ];

    $_SESSION['rwa_user'] = $user;
    $_SESSION['rwa_tokens'] = $tokens;
}

function rwa_group_hierarchy_cache_file(): string
{
    return (string) rwa_config('cache.group_hierarchy_file', __DIR__ . '/cache/group-hierarchy-cache.json');
}

function rwa_group_hierarchy_ttl(): int
{
    return max(60, (int) rwa_config('cache.group_hierarchy_ttl', 7 * 24 * 60 * 60));
}

function rwa_load_persistent_group_cache(): array
{
    $file = rwa_group_hierarchy_cache_file();
    if (!file_exists($file) || filesize($file) === 0) {
        return ['groups' => []];
    }

    $decoded = json_decode((string) file_get_contents($file), true);

    return is_array($decoded) && isset($decoded['groups']) && is_array($decoded['groups'])
        ? $decoded
        : ['groups' => []];
}

function rwa_write_persistent_group_cache(array $cache): void
{
    $file = rwa_group_hierarchy_cache_file();
    $directory = dirname($file);

    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    file_put_contents(
        $file,
        json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function rwa_get_cached_group_hierarchy(int $groupId): ?array
{
    $now = time();
    $sessionCache = $_SESSION['rwa_group_hierarchy_session_cache'][$groupId] ?? null;
    if (is_array($sessionCache) && (int) ($sessionCache['expires_at'] ?? 0) >= $now) {
        return $sessionCache['ids'] ?? null;
    }

    $persistentCache = rwa_load_persistent_group_cache();
    $entry = $persistentCache['groups'][(string) $groupId] ?? null;
    if (is_array($entry) && (int) ($entry['expires_at'] ?? 0) >= $now) {
        $_SESSION['rwa_group_hierarchy_session_cache'][$groupId] = $entry;

        return $entry['ids'] ?? null;
    }

    return null;
}

function rwa_store_cached_group_hierarchy(int $groupId, array $groupIds): array
{
    $entry = [
        'ids' => array_values(array_unique(array_map('intval', $groupIds))),
        'expires_at' => time() + rwa_group_hierarchy_ttl(),
        'cached_at' => time(),
    ];

    $_SESSION['rwa_group_hierarchy_session_cache'][$groupId] = $entry;

    $persistentCache = rwa_load_persistent_group_cache();
    $persistentCache['groups'][(string) $groupId] = $entry;
    rwa_write_persistent_group_cache($persistentCache);

    return $entry['ids'];
}

function rwa_collect_group_ids_from_tree(mixed $payload): array
{
    $groupIds = [];
    $walker = function (mixed $node) use (&$walker, &$groupIds): void {
        if (!is_array($node)) {
            return;
        }

        foreach (['id', 'group_id'] as $key) {
            if (isset($node[$key]) && is_numeric($node[$key])) {
                $groupIds[] = (int) $node[$key];
            }
        }

        foreach (['children', 'groups', 'subgroups', 'descendants'] as $childKey) {
            if (!isset($node[$childKey]) || !is_array($node[$childKey])) {
                continue;
            }

            foreach ($node[$childKey] as $child) {
                $walker($child);
            }
        }
    };

    $walker($payload);

    return array_values(array_unique($groupIds));
}

function rwa_fetch_group_hierarchy(int $groupId): array
{
    $cached = rwa_get_cached_group_hierarchy($groupId);
    if (is_array($cached) && $cached !== []) {
        return $cached;
    }

    $template = (string) rwa_config('auth.group_hierarchy_url_template', '');
    $accessToken = (string) ($_SESSION['rwa_tokens']['access_token'] ?? '');
    if ($template === '' || $accessToken === '') {
        return [$groupId];
    }

    $url = sprintf($template, $groupId);
    $response = rwa_http_request('GET', $url, ['Accept: application/json'], null, null, $accessToken);
    $decoded = json_decode($response['body'], true);

    if (!is_array($decoded)) {
        return [$groupId];
    }

    $groupIds = rwa_collect_group_ids_from_tree($decoded);
    $groupIds[] = $groupId;

    return rwa_store_cached_group_hierarchy($groupId, $groupIds);
}

function rwa_role_matches_rule(array $role, array $rule): bool
{
    $allowedRoles = $rule['allowed_roles'] ?? [];
    if (is_array($allowedRoles) && $allowedRoles !== [] && !in_array($role['role'], $allowedRoles, true)) {
        return false;
    }

    $targetGroupId = (int) ($rule['group_id'] ?? 0);
    $targetLayerGroupId = (int) ($rule['layer_group_id'] ?? 0);
    $includeSubgroups = (bool) ($rule['include_subgroups'] ?? false);

    if ($targetGroupId === 0 && $targetLayerGroupId === 0) {
        return true;
    }

    $candidateIds = array_filter([(int) ($role['group_id'] ?? 0), (int) ($role['layer_group_id'] ?? 0)]);

    if ($targetGroupId > 0) {
        if (in_array($targetGroupId, $candidateIds, true)) {
            return true;
        }

        if ($includeSubgroups) {
            $descendants = rwa_fetch_group_hierarchy($targetGroupId);
            if (array_intersect($candidateIds, $descendants) !== []) {
                return true;
            }
        }
    }

    if ($targetLayerGroupId > 0) {
        if (in_array($targetLayerGroupId, $candidateIds, true)) {
            return true;
        }

        if ($includeSubgroups) {
            $descendants = rwa_fetch_group_hierarchy($targetLayerGroupId);
            if (array_intersect($candidateIds, $descendants) !== []) {
                return true;
            }
        }
    }

    return false;
}

function rwa_user_matches_access_rules(array $rules, ?array $user = null): bool
{
    $user ??= rwa_current_user();
    if ($user === null) {
        return false;
    }

    if ($rules === []) {
        return true;
    }

    $roles = $user['roles'] ?? [];
    foreach ($rules as $rule) {
        if (!is_array($rule)) {
            continue;
        }

        foreach ($roles as $role) {
            if (rwa_role_matches_rule($role, $rule)) {
                return true;
            }
        }
    }

    return false;
}

function rwa_apps_path(): string
{
    return __DIR__ . '/apps';
}

function rwa_load_apps(): array
{
    static $apps;

    if ($apps !== null) {
        return $apps;
    }

    $apps = [];
    foreach (glob(rwa_apps_path() . '/*/manifest.php') ?: [] as $manifestFile) {
        $manifest = require $manifestFile;
        if (!is_array($manifest) || empty($manifest['id'])) {
            continue;
        }

        $manifest['enabled'] = (bool) ($manifest['enabled'] ?? true);
        $manifest['entry'] = (string) ($manifest['entry'] ?? 'index.php');
        $manifest['path'] = dirname($manifestFile);
        $manifest['access_rules'] = is_array($manifest['access_rules'] ?? null) ? $manifest['access_rules'] : [];

        $apps[$manifest['id']] = $manifest;
    }

    return $apps;
}

function rwa_get_registered_apps(): array
{
    return rwa_load_apps();
}

function rwa_get_app(?string $appId): ?array
{
    if ($appId === null || $appId === '') {
        return null;
    }

    $apps = rwa_load_apps();

    return $apps[$appId] ?? null;
}

function rwa_user_can_access_app(array $app, ?array $user = null): bool
{
    if (!($app['enabled'] ?? false)) {
        return false;
    }

    return rwa_user_matches_access_rules($app['access_rules'] ?? [], $user);
}

function rwa_visible_apps(?array $user = null): array
{
    $visible = [];
    foreach (rwa_get_registered_apps() as $app) {
        if (rwa_user_can_access_app($app, $user)) {
            $visible[] = $app;
        }
    }

    return $visible;
}

function rwa_requested_app_id(): ?string
{
    $appId = $_GET['app'] ?? null;

    return is_string($appId) && $appId !== '' ? $appId : null;
}

function rwa_render_header(string $title, ?array $activeApp = null): void
{
    $user = rwa_current_user();
    $platformName = rwa_platform_name();
    $navLabel = $activeApp['name'] ?? 'Dashboard';
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= rwa_escape($title) ?> · <?= rwa_escape($platformName) ?></title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #0f172a; color: #e2e8f0; }
        a { color: inherit; text-decoration: none; }
        .shell { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .topbar { display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 24px; }
        .brand small { color: #94a3b8; display: block; margin-top: 4px; }
        .nav { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .button, button { background: #2563eb; color: #fff; border: 0; border-radius: 12px; padding: 12px 16px; cursor: pointer; font: inherit; }
        .button.secondary { background: rgba(148, 163, 184, 0.18); color: #e2e8f0; }
        .card { background: rgba(15, 23, 42, 0.82); border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 18px; padding: 20px; box-shadow: 0 18px 60px rgba(15, 23, 42, 0.3); }
        .grid { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .hero { margin: 32px 0; }
        .muted { color: #94a3b8; }
        .flash { padding: 14px 16px; border-radius: 12px; margin-bottom: 18px; }
        .flash.success { background: rgba(34, 197, 94, 0.16); border: 1px solid rgba(34, 197, 94, 0.35); }
        .flash.error { background: rgba(248, 113, 113, 0.16); border: 1px solid rgba(248, 113, 113, 0.35); }
        .flash.info { background: rgba(96, 165, 250, 0.16); border: 1px solid rgba(96, 165, 250, 0.35); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border-bottom: 1px solid rgba(148, 163, 184, 0.14); text-align: left; vertical-align: top; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <div class="brand">
                <strong><?= rwa_escape($platformName) ?></strong>
                <small><?= rwa_escape($navLabel) ?></small>
            </div>
            <div class="nav">
                <a class="button secondary" href="<?= rwa_escape(rwa_url()) ?>">Dashboard</a>
                <?php if ($activeApp !== null): ?>
                    <span class="muted"><?= rwa_escape($activeApp['icon'] ?? '') ?> <?= rwa_escape($activeApp['name'] ?? '') ?></span>
                <?php endif; ?>
                <?php if ($user !== null): ?>
                    <span class="muted"><?= rwa_escape($user['name'] ?? 'MiData User') ?></span>
                    <a class="button secondary" href="<?= rwa_escape(rwa_url('auth.php?action=logout')) ?>">Logout</a>
                <?php endif; ?>
            </div>
        </div>
        <?php $flash = rwa_get_flash(); ?>
        <?php if ($flash !== null): ?>
            <div class="flash <?= rwa_escape($flash['type'] ?? 'info') ?>"><?= rwa_escape($flash['message'] ?? '') ?></div>
        <?php endif; ?>
<?php
}

function rwa_render_footer(): void
{
    ?>
        <div class="hero muted" style="margin-top: 32px; font-size: 0.95rem;">
            <?= rwa_escape((string) rwa_config('platform.footer_text', '')) ?>
        </div>
    </div>
</body>
</html>
<?php
}

function rwa_render_missing_config_page(): void
{
    rwa_render_header('Konfiguration fehlt');
    ?>
    <div class="card">
        <h1>Konfiguration fehlt</h1>
        <p>Kopiere <code>config.example.php</code> nach <code>config.php</code> und trage dort OAuth-, Cache- und Nuki-Werte ein.</p>
    </div>
    <?php
    rwa_render_footer();
}

function rwa_render_access_denied(array $app): never
{
    http_response_code(403);
    rwa_render_header('Zugriff verweigert', $app);
    ?>
    <div class="card">
        <h1>Zugriff verweigert</h1>
        <p>Dein MiData-Account erfüllt die hinterlegten Berechtigungen für <strong><?= rwa_escape($app['name']) ?></strong> aktuell nicht.</p>
    </div>
    <?php
    rwa_render_footer();
    exit;
}

function rwa_render_not_found(string $title, string $message): never
{
    http_response_code(404);
    rwa_render_header($title);
    ?>
    <div class="card">
        <h1><?= rwa_escape($title) ?></h1>
        <p><?= rwa_escape($message) ?></p>
    </div>
    <?php
    rwa_render_footer();
    exit;
}

function rwa_nuki_config_is_complete(): bool
{
    return (string) rwa_config('nuki.api_base_url', '') !== ''
        && (string) rwa_config('nuki.api_token', '') !== ''
        && (string) rwa_config('nuki.smartlock_id', '') !== '';
}

function rwa_nuki_action_label(string $action): string
{
    return $action === 'unlock' ? 'öffnen' : 'schliessen';
}

function rwa_nuki_perform_action(string $action): array
{
    if (!in_array($action, ['unlock', 'lock'], true)) {
        throw new InvalidArgumentException('Unsupported Nuki action.');
    }

    if (!rwa_nuki_config_is_complete()) {
        throw new RuntimeException('Die Nuki-Konfiguration ist unvollständig.');
    }

    $apiBase = rtrim((string) rwa_config('nuki.api_base_url'), '/');
    $endpoint = sprintf((string) rwa_config('nuki.action_endpoint_template', '/smartlock/%s/action'), rwa_config('nuki.smartlock_id'));
    $actionValue = $action === 'unlock'
        ? (int) rwa_config('nuki.unlock_action', 1)
        : (int) rwa_config('nuki.lock_action', 2);

    $response = rwa_http_request(
        'POST',
        $apiBase . $endpoint,
        ['Accept: application/json'],
        ['action' => $actionValue],
        null,
        (string) rwa_config('nuki.api_token'),
        (int) rwa_config('nuki.request_timeout', 20)
    );

    $decoded = json_decode($response['body'], true);

    return [
        'status' => $response['status'],
        'payload' => is_array($decoded) ? $decoded : ['raw' => $response['body']],
    ];
}
