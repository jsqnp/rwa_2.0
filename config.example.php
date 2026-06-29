<?php

return [
    'platform' => [
        'name' => 'RWA 2.0',
        'subtitle' => 'Multi-App-Portal mit MiData / Hitobito Login',
        'base_url' => 'https://example.org/rwa_2.0',
        'login_headline' => 'Willkommen bei der RWA 2.0',
        'login_text' => 'Melde dich mit MiData / Hitobito an, um deine freigeschalteten Apps zu öffnen.',
        'login_button_label' => 'Mit MiData anmelden',
        'dashboard_intro' => 'Verfügbare Apps für deinen aktuellen MiData-Account.',
        'footer_text' => 'RWA 2.0 – Multi-App-Plattform für MiData-basierte Tools',
    ],
    'debug' => false,
    'auth' => [
        'provider_name' => 'MiData / Hitobito',
        'client_id' => 'your-client-id',
        'client_secret' => 'your-client-secret',
        'scope' => 'name email',
        'authorize_url' => 'https://db.scout.ch/oauth/authorize',
        'token_url' => 'https://db.scout.ch/oauth/token',
        'profile_url' => 'https://db.scout.ch/oauth/profile',
        'roles_url' => null,
        'group_hierarchy_url_template' => 'https://db.scout.ch/groups/%d.json',
        'timeout' => 20,
    ],
    'cache' => [
        'group_hierarchy_ttl' => 7 * 24 * 60 * 60,
        'group_hierarchy_file' => __DIR__ . '/cache/group-hierarchy-cache.json',
    ],
    'nuki' => [
        'enabled' => true,
        'title' => 'MiData Nuki Smart Lock Control',
        'description' => 'Öffnet und schliesst das konfigurierte Nuki Smart Lock.',
        'icon' => '🔐',
        'api_base_url' => 'https://api.nuki.io',
        'api_token' => 'your-nuki-api-token',
        'smartlock_id' => 'your-smartlock-id',
        'action_endpoint_template' => '/smartlock/%s/action',
        'request_timeout' => 20,
        'unlock_action' => 1,
        'lock_action' => 2,
        'access_rules' => [
            [
                'name' => 'Leitung mit Untergruppen',
                'layer_group_id' => 12345,
                'include_subgroups' => true,
                'allowed_roles' => [
                    'Abteilungsleiter*in',
                    'Einheitsleiter*in',
                    'Mitleiter*in',
                ],
            ],
        ],
    ],
];
