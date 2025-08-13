# MauticCustomImportBundle

Import contacts from a filesystem directory using Mautic’s native importer, optionally run imports in parallel from the CLI, and bulk-remove tags on demand.

## Features

- Create imports from CSV files located in a server directory
- Run imports **in parallel** via CLI (robust PHP CLI discovery; works on php-fpm hosts)
- Remove tags from contacts via CLI
- **New:** Select an **Import Owner** in the integration settings  

## Compatibility

- Mautic 5.x 


## Support

<https://mtcextendee.com/plugins>

## Installation

### Composer (recommended)

```bash
composer require mtcextendee/mautic-custom-import-bundle
php bin/console mautic:plugins:reload
```
Then open **Settings → Plugins → Custom Import** and configure the integration.

### Manual

1. Download the latest release from GitHub Releases.
2. Unzip into: `plugins/MauticCustomImportBundle`
3. Clear cache:
   ```bash
   php bin/console cache:clear
   ```
4. In Mautic, go to **Settings → Plugins → Reload Plugins**, then open **Custom Import** and configure.

## Configuration

Open **Settings → Plugins → Custom Import** and review:

- **Template from existing import**  
  Choose a previous (successful) Mautic import to reuse its parser settings and field mapping.

- **Path to directory with CSV files**  
  Absolute server path where your CSV files are placed (e.g. `/var/www/example.com/htdocs/var/import`).  
  The command scans this folder and creates a standard Mautic import per CSV.

- **Import records limit**  
  Batch size used by the CLI importer for each worker.

- **Select Import Owner (new)**  
  Choose a Mautic user. Each created import row will have:
  - `created_by` = selected user’s ID  
  - `created_by_user` = the selected user’s display name  
  (Also applied to `properties.defaults.owner`.)

- **Tags to Remove (for the remove-tags command)**  
  Select tags to strip from contacts before your next import run.

## Usage

### Create imports from a directory

```bash
php bin/console mautic:import:directory
```

Reads the configured folder and creates one queued Mautic import per CSV using the template’s mapping and parser config.

### Run parallel import

```bash
php bin/console mautic:import:parallel
```

Notes:

- The plugin spawns multiple workers up to Mautic’s **parallel import limit** (configure in `app/config/local.php` / `config/local.php` depending on your setup).
- Works on servers that primarily run **php-fpm**. You can force a specific PHP binary by setting:
  ```bash
  export MAUTIC_PHP_BIN=/usr/bin/php
  ```
  The plugin otherwise auto-detects (`PhpExecutableFinder`, `PHP_BINARY`, common paths, then `php` from `PATH`).

### Remove tags via CLI

```bash
php bin/console mautic:remove:tags
```

Removes the tags you configured in the integration. Helpful when your next CSV import needs a clean slate.

## Troubleshooting

- **“php does not exist” (parallel command):**  
  The plugin now tries several strategies to find PHP. If needed, set `MAUTIC_PHP_BIN=/path/to/php` for the web/CLI user.

- **Owner not applied:**  
  Ensure **Select Import Owner** is saved in the integration. New `imports` rows should show the selected owner in both `created_by` and `created_by_user`. Historical rows are unchanged.

- **Permissions:**  
  The web/CLI user must have read/write access to the CSV directory and Mautic’s `var/` (cache/logs/import dir).

- **Logs:**  
  Check `var/logs/` for import-related errors.

## Credits

Icons by [Chanut](https://www.flaticon.com/authors/chanut) on [Flaticon](https://www.flaticon.com/).
