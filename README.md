# Ship Fixes

Small fixes and stopgaps for my sites, each one switched on or off: for now, the Table of Contents block without the Gutenberg plugin.

## What it does

A fix is one small, separate thing, switched on or off in the settings. Fixes are stopgaps: each one says when it is no longer needed.

- **Table of Contents block** (`core/table-of-contents`, on by default): the block of the Gutenberg plugin, for sites without that plugin. WordPress 7.2 ships the block's code but does not register it while it is experimental, so content written with the Gutenberg plugin shows no table of contents (and "unsupported block" in the editor) once that plugin is gone. The fix registers the block under WordPress's own name, so that content keeps working, and only when nothing else registered it: the Gutenberg plugin, or later WordPress itself, win. The block and its rendering follow Gutenberg 24.4.1 (`packages/block-library/src/table-of-contents`, GPL-2.0-or-later); see "Updating the Table of Contents block" below. No longer needed when WordPress registers the block itself.
- **Settings** (site by site): on a single site, or activated on some sites of a network, each site's Settings, Ship Fixes, for its administrators (`manage_options`): one switch per fix. Activated for the whole network, also Network Admin, Settings, Ship Fixes (`manage_network_options`): the fixes for every site, and whether site administrators may decide for their own site (otherwise their screen goes). Where they may, what a site saved wins over the network's choice. DataForm screens (React, `src/admin/settings`) saving through the plugin's REST routes.
- **Uninstall**: removes the settings from every site. The plugin keeps nothing else.

## Adding a fix

1. Its class in `includes/` (`class-<name>.php`, a static `init()` that adds its hooks; it is only called where the fix is on).
2. An entry in `Fixes::FIXES` (`includes/class-fixes.php`): ID in snake_case, class, default.
3. Its label and description in `Fixes::texts()`. The settings screens, the REST routes and the stored options follow from the list.
4. Tests, and a line above and in `readme.txt`.

## Updating the Table of Contents block

The block changes in Gutenberg while it is experimental. To follow a newer Gutenberg release, compare `packages/block-library/src/table-of-contents` at that tag with `src/blocks/table-of-contents` (editor) and `includes/class-table-of-contents.php` (rendering, from the block's `index.php`). Differences kept on purpose: this plugin's text domain; the select of `@wordpress/components` instead of `@wordpress/ui` (WordPress 7.2 does not provide that package to plugins); the browser's parser instead of `__unstableStripHTML`; methods of a class instead of the `block_core_table_of_contents_*` functions (WordPress's own names); TypeScript files written as JavaScript. Saved content is a contract: the block name, its attributes (`onlyIncludeCurrentPage`, `maxLevel`, `ordered`) and the deprecation for posts saved with a list (`deprecated.js`).

## Data model

| Kind | Name | Notes |
|---|---|---|
| Option (each site) | `ship_fixes_settings` | Fix ID => bool, only the fixes the site decided on (autoloaded: read on every request). |
| Network option | `ship_fixes_network` | Network-wide only: fix ID => bool, and `site_overrides` (bool, default true). |
| Fix IDs | `table_of_contents` | Default on. |
| Block | `core/table-of-contents` | WordPress's own name, registered only when missing. Attributes `onlyIncludeCurrentPage` (bool), `maxLevel` (number), `ordered` (bool); context `postId`, `postType`; rendered on the server. |
| REST | `ship-fixes/v1/settings`, `ship-fixes/v1/network-settings` | GET and POST; the site's for its administrators where sites may decide, the network's for network administrators when network-wide. |
| Script handles | `ship-fixes-settings`, the block's `wp-block-table-of-contents-editor` | From `build/`. |

## Privacy

No personal data, no cookies, nothing kept in the visitor's browser. Requests to other sites: see Outside services.

## Outside services

None.

## Development

The block and the admin screens are built with `@wordpress/scripts` (`src/` into `build/`, committed: the release zip ships it as built and tested). The blocks build also writes `build/blocks/blocks-manifest.php` (`--blocks-manifest`), so WordPress reads one PHP file instead of a `block.json` per block. Block icons come from `@wordpress/icons`. Front-end behavior, when a fix needs some, uses the Interactivity API, never plain DOM scripts or jQuery. DataForm (`@wordpress/dataviews/wp`) is bundled: WordPress does not provide it. After every build, `bin/guard-build.mjs` adds the direct access guard to the generated PHP files (`*.asset.php`, `blocks-manifest.php`); `bin/build-zip.sh` refuses a build without it.

```
npm install
npm run format
npm run build
npm run lint:js
npm run lint:style
```

PHP checks run with the dev project's tools (in the DDEV project of the dev network, `tools/`): `tools/check-plugin.sh <repository folder>` runs the smoke run, php -l, PHPCS, PHPStan level 8, JavaScript and style lint, the build, PHPUnit (multisite, then single site) and Playwright, then reads `debug.log`. Run `ddev restart` first.

**On GitHub** (`.github/workflows/ci.yml`, every push to `main` and every pull request): php -l on PHP 8.4 and 8.5, PHPCS, PHPStan (PHP 8.4), JavaScript and style lint with a fresh build equal to the committed `build/`, PHPUnit multisite and single site on PHP 8.4 and 8.5 against the WordPress nightly (`bin/install-wp-tests.sh`). Playwright stays local (it needs the dev network). Wait for the green tick on the commit before tagging a release: a tag `vX.Y.Z` builds `<slug>.zip` (`bin/build-zip.sh`) and publishes the release.

## License

GPL-2.0-or-later.
