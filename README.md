



```json
{
    "name": "ipm-ulricehamn/image-attachment-report",
    "description": "A WordPress plugin for generating reports on image attachments.",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {}
}
```
* "type": "wordpress-plugin" tells Composer that this is a WordPress plugin.
* "ipm-ulricehamn/image-attachment-report" is the GitHub namespace.

## Add the plugin to your project
Go to your WordPress installation (which might also be a Git repository), and open or create a composer.json file.

Inside your WordPress installation's composer.json, add your plugin as a VCS (Version Control System) repository:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:IPM-Ulricehamn/image-attachment-report.git"
        }
    ],
    "require": {
        "ipm-ulricehamn/image-attachment-report": "dev-master",
        "afragen/wp-github-updater": "^10.0"
    },
    "extra": {
        "installer-paths": {
            "wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
        }
    }
}
```

* "type": "vcs" tells Composer that this is a Version Control System (VCS) repository (i.e., a Git repo, rather than a package from Packagist).
* "url": "git@github.com:IPM-Ulricehamn/image-attachment-report.git" is the SSH URL to your private GitHub repository.
* "require" ensures the plugin is installed under wp-content/plugins/.
* "dev-main" refers to the default branch in your Git repository.
  By default, Composer assumes main as the primary branch.
  _If your default branch is master_, you need to change "dev-main" to "dev-master".
* If using HTTPS instead of SSH, use:
```json
"url": "https://github.com/IPM-Ulricehamn/image-attachment-report.git"
```

### Private repo
Since your repository is private, you need to authenticate Composer with GitHub.

#### Generate a GitHub Personal Access Token (PAT)
* Go to GitHub Developer Settings → Tokens (Classic).
* Click "Generate new token" (classic).
* Select repo scope.
* Copy the generated token.
#### Use the Token in Composer

```sh
# Run
composer config --global github-oauth.github.com YOUR_PERSONAL_ACCESS_TOKEN
```
This tells Composer to use your GitHub token when accessing private repositories.

### Install the Plugin Using Composer
```sh
# Now, in your WordPress installation directory, run:
composer install
# or if updating
composer update
```

## Activating and Managing Updates
* Activate the Plugin in WordPress
* Go to `Plugins → Installed Plugins` and activate **Image Attachment Report**.
Updating the Plugin

If you push new changes to GitHub, update the plugin with:
```sh
composer update itest-ulricehamn/image-attachment-report
```

## Alternative: Use a Custom GitHub Release (Optional)
If you want to avoid using "type": "vcs", you can publish GitHub Releases (tags) and use "type": "package" instead. This is useful if you prefer fetching only stable versions.


## Github Updater
This is added to require to listen for updates on a private repo (not needed if the repo is public).
You'd also need to add these rows to the plugin header
```php
/*
{other stuff}
 
Update URI: https://github.com/IPM-Ulricehamn/image-attachment-report
GitHub Plugin URI: IPM-Ulricehamn/image-attachment-report
GitHub Access Token: YOUR_GITHUB_ACCESS_TOKEN
*/
```
**When you release a new version**  
Create a new GitHub Release with a tag matching the new version (e.g., v1.1.0).
WordPress will now detect the update in the Plugins screen.
