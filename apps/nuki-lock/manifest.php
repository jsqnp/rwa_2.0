<?php

return [
    'id' => 'nuki-lock',
    'name' => (string) rwa_config('nuki.title', 'MiData Nuki Smart Lock Control'),
    'description' => (string) rwa_config('nuki.description', 'Öffnen und Schliessen des konfigurierten Smart Locks.'),
    'icon' => (string) rwa_config('nuki.icon', '🔐'),
    'enabled' => (bool) rwa_config('nuki.enabled', true),
    'entry' => 'index.php',
    'access_rules' => rwa_config('nuki.access_rules', []),
];
