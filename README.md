# RWA 2.0

RWA 2.0 ist der aufgeräumte Neustart der bisherigen MiData-/Nuki-Lösung als kleine Multi-App-Plattform. Die Plattform übernimmt Login, Session-Handling, Rollen-/Berechtigungsprüfung, Gruppenhierarchie-Cache und das Dashboard; Fachfunktionen leben als einzelne Apps unter `apps/`.

## Enthaltene Funktionen

- MiData / Hitobito OAuth-Login mit Callback unter `auth/midata/index.php`
- Session-basierte Benutzerverwaltung
- app-spezifische Access-Rules über App-Manifeste
- persistenter Gruppenhierarchie-Cache mit TTL und Session-Cache
- Dashboard als Root-Startseite
- erste migrierte App `nuki-lock` für Öffnen / Schliessen eines Nuki Smart Locks
- optionale Debug-Rollenansicht

## Struktur

```text
.
├── app.php                  # gemeinsamer Bootstrap, Auth-, Access- und Cache-Logik
├── auth.php                 # Login/Logout Einstieg
├── config.example.php       # Beispielkonfiguration
├── index.php                # Dashboard / einfacher Router
├── auth/
│   └── midata/
│       └── index.php        # OAuth Callback
├── apps/
│   └── nuki-lock/
│       ├── action.php       # POST-Endpunkt für lock/unlock
│       ├── debug.php        # Debug-Rollenansicht
│       ├── index.php        # UI der Nuki-App
│       └── manifest.php     # App-Registrierung
└── cache/
    └── .gitignore
```

## Setup

1. `config.example.php` nach `config.php` kopieren:

   ```bash
   cp config.example.php config.php
   ```

2. In `config.php` mindestens folgende Werte eintragen:
   - `platform.base_url`
   - OAuth-Daten unter `auth.*`
   - `nuki.api_base_url`
   - `nuki.api_token`
   - `nuki.smartlock_id`
   - gewünschte `nuki.access_rules`

3. Sicherstellen, dass PHP Schreibrechte für `cache/` hat.

## Wie neue Apps hinzugefügt werden

Jede App bekommt einen eigenen Ordner unter `apps/<app-id>/` und mindestens ein `manifest.php`:

```php
<?php

return [
    'id' => 'my-app',
    'name' => 'Meine App',
    'description' => 'Kurzbeschreibung',
    'icon' => '🧩',
    'enabled' => true,
    'entry' => 'index.php',
    'access_rules' => [
        [
            'layer_group_id' => 12345,
            'include_subgroups' => true,
            'allowed_roles' => ['Abteilungsleiter*in'],
        ],
    ],
];
```

Sobald `manifest.php` vorhanden ist, erscheint die App automatisch im Registry-Scan von `app.php`.

## Berechtigungen pro App

- Jede App definiert `access_rules` direkt im Manifest.
- Eine Regel kann auf `group_id` oder `layer_group_id` zielen.
- `include_subgroups => true` aktiviert die Prüfung über die Gruppenhierarchie.
- `allowed_roles` begrenzt die Regel auf bestimmte MiData-Rollen.
- Leere `access_rules` bedeuten: jeder eingeloggte Benutzer darf die App sehen und öffnen.

## Gruppenhierarchie-Cache

Für Regeln mit Untergruppen holt die Plattform die Gruppenhierarchie einmal via MiData/Hitobito, speichert sie in der Session und zusätzlich persistent in `cache/group-hierarchy-cache.json`.

- TTL konfigurierbar über `cache.group_hierarchy_ttl`
- robuste Behandlung für fehlende oder leere Cache-Dateien
- der Cache-Ordner ist versioniert, die JSON-Datei selbst bleibt ignoriert

## Nuki-App

Die erste App `nuki-lock` verwendet die gemeinsame Login-/Access-Schicht und sendet Lock-/Unlock-Aktionen an die konfigurierte Nuki-API. Titel, Beschreibung, Icon und Access-Rules kommen aus `config.php`.
