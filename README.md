# IPM Image Attachment Report

A WordPress admin tool that lists every image attachment in the media library together with its dimensions, a resolution rating (Low / Medium / Good, based on total megapixels), and where it's actually used across the site (featured images, post content, Gutenberg blocks, post/user/term meta). Includes CSV export.

Found under **Media → Image report** (`manage_options` capability required).

This plugin lives in its own GitHub repository (`IPM-Ulricehamn/ipm-image-attachment-report`) and is pulled into client projects either as a Composer VCS dependency or as a plain plugin folder — see "Installing in a project" below. That's why it carries its own `composer.json` and `.gitignore`.

## How it works

Finding where an image is used requires several `LIKE`/`REGEXP` scans across `wp_posts`, `wp_postmeta`, `wp_usermeta` and `wp_termmeta` for **every** image attachment — expensive on a real media library. To keep the admin responsive, this lookup only ever runs when you explicitly click **Run scan** on the report page itself; the result is cached in a transient (`iar_scan_data` / `iar_scan_meta`, no expiry) until the next manual scan.

Once a scan has run:
- The **Exclude post types** (affects the "Used In" column only), **low-resolution** and **Exclude files by type** filters, and pagination (50 images per page), all operate on the cached data in memory — no further database scanning happens on filter/page changes.
- The header shows how long ago the last scan ran and how many images it covered.
- Click **Run scan** again any time the media library or content has changed and you want the report to catch up.

## CSV export

**Export current list to CSV** exports the rows currently shown in the table, i.e. the active filters and only the current page (50 images per page). The file is built in the browser as a Blob (UTF-8 with BOM so Excel handles åäö). Row numbers and thumbnails are left out.

## Translations

Strings are wrapped for translation under the `ipm-image-attachment-report` text domain. A starter `.pot` file lives in `languages/`; regenerate it after changing any user-facing string with:

```sh
wp i18n make-pot . languages/ipm-image-attachment-report.pot --domain=ipm-image-attachment-report --allow-root
```

## Installing in a project

### Option A — plain plugin folder
Copy (or git-clone) this plugin into `wp-content/plugins/` and activate it from **Plugins → Installed Plugins**.

### Option B — Composer (VCS repository)
Add it as a VCS repository in the consuming project's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:IPM-Ulricehamn/ipm-image-attachment-report.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/afragen/git-updater"
    }
  ],
  "require": {
    "ipm-ulricehamn/ipm-image-attachment-report": "dev-master",
    "afragen/git-updater": "^12.0",
    "composer/installers": "^2.0"
  },
  "extra": {
    "installer-paths": {
      "wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
    }
  },
  "minimum-stability": "dev",
  "prefer-stable": true,
  "config": {
    "allow-plugins": {
      "composer/installers": true
    }
  }
}
```

- `"type": "vcs"` tells Composer this is a Git repository rather than a Packagist package.
- Use the HTTPS URL (`https://github.com/IPM-Ulricehamn/ipm-image-attachment-report.git`) instead of the SSH one if that's easier for your environment.
- `composer/installers` makes Composer install the plugin into `wp-content/plugins/` instead of `vendor/`.
- The repo is private, so authenticate Composer with a GitHub Personal Access Token (repo scope):
  ```sh
  composer config --global github-oauth.github.com YOUR_PERSONAL_ACCESS_TOKEN
  ```

Then, in the consuming project:
```sh
composer install
# or, to pull in updates:
composer update ipm-ulricehamn/ipm-image-attachment-report
```

## Updates via GitHub Updater (private repo)

The plugin header declares:
```php
Update URI: https://github.com/IPM-Ulricehamn/ipm-image-attachment-report
GitHub Plugin URI: IPM-Ulricehamn/ipm-image-attachment-report
```

If the repo is private, WordPress needs a GitHub token to check for and fetch releases. Define it in the consuming project's `wp-config.php` — **never** commit it or store it in `wp_options`:

```php
define('GITHUB_ACCESS_TOKEN', 'your-token-here');
```

`image-attachment-report.php` reads that constant and attaches it as a Bearer token, but only to requests aimed at this plugin's own GitHub repo/update endpoint — not to every request that happens to touch `github.com`.

To ship a new version: bump `Version` in the plugin header, update `changelog.txt`, and create a GitHub Release with a matching tag (e.g. `v1.8.1`). WordPress (via GitHub Updater) will pick it up on the Plugins screen.

If you'd rather not rely on GitHub Updater/private-repo auth, publish tagged GitHub Releases and switch the Composer requirement to `"type": "package"` to fetch stable release archives instead of the live VCS branch.
