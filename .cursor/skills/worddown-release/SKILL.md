---
name: worddown-release
description: >-
  Release the Worddown WordPress plugin. Use when bumping the version, tagging,
  pushing a release, publishing to wordpress.org, or changing SVN deploy,
  .wordpress-org banners and icons, or the release workflow.
---

# Worddown release

A release is a git tag with the same name as `Stable tag` in `readme.txt`. No `v` prefix (`1.2.5`, not `v1.2.5`). Pushing that tag runs `.github/workflows/release.yml`. The `check` job must pass before `deploy` starts. A red check does not deploy.

Pushing `master` alone does not publish.

## Version files

Update all of these to the same version, then refresh the translation template with `npm run make-pot`:

- `worddown.php` header `Version`. `get_plugin_version()` in `helpers.php` reads that header and uses it as the enqueued asset version.
- `readme.txt` `Stable tag` and a changelog section
- `version` in `package.json` and both `version` fields in `package-lock.json`
- `Project-Id-Version` in `resources/languages/worddown.pot` and `resources/languages/worddown-sv_SE.po`

Commit the version bump, then tag that commit and push the tag.

## WordPress.org

The deploy job builds admin assets with `npm ci` and `npm run build`, runs `composer install --no-dev`, then `10up/action-wordpress-plugin-deploy`. `.distignore` decides what enters the plugin zip. `dist/` and `vendor/` are created in the deploy job and must ship. `/.wordpress-org` is excluded from the zip.

`.wordpress-org/` is copied to the SVN `assets/` directory. Current files: `banner-772x250.jpg`, `banner-1544x500.jpg`, `icon-256x256.jpg`. Add new directory images there using the wordpress.org filenames. Do not commit `.DS_Store`.

SVN credentials live only as GitHub Actions secrets on `adamalexandersson/worddown`:

- `SVN_USERNAME` = `adamalexandersson`
- `SVN_PASSWORD` = the WordPress.org SVN password

Never commit or paste those values. A tag push without the secrets can pass the check and fail deploy.

Do not change a tag that is already on wordpress.org. Ship a new version as a new tag.

A two-dot tag, including `1.2.5-beta.1`, matches `release.yml` and would publish to wordpress.org. A local zip from `wp dist-archive` does not publish. Do not push a tag for a build that should stay unpublished.
