# Verified Worklog

## Overview
Historical log of verified changes, refactors, and feature additions based on git history and repository evidence.

---

## Completed Work

### Tag v1.1.3 (2026-11-20)
- **Security & Hardening:** Enforced GitHub host allowlist (`github.com`, `objects.githubusercontent.com`, `release-assets.githubusercontent.com`) in `GNN_Sitemap_Updater::get_package_url()` and prevented invalid API responses from being cached in transients (`34273f3`).
- **Enhancement:** Added `last_updated` to `plugins_api()` response and applied WPCS late escaping (`wp_kses_post`, `esc_url`, `esc_html`) on admin notices and sitemap URLs (`34273f3`).
- **Release:** Bumped version to `1.1.3` across `VERSION`, `gnn-sitemap.php`, `.pot`, and `.po` (`b8ecc05`); GitHub Release workflow `44647121825` succeeded.

### Tag v1.1.2 (2026-11-18)
- **Compatibility & Security:** Updated `Tested up to: 7.1`, moved `load_plugin_textdomain` to `init` priority `1`, added `LIBXML_NONET` to `DOMDocument::loadXML()`, sanitized `$_SERVER['REQUEST_METHOD']`, normalized paths for Windows (`wp_normalize_path`), and added `rel="noopener noreferrer"` to external links (`e6dd305`).
- **Accessibility & UI:** Added `:focus-visible`, `aria-hidden="true"`, `<legend class="screen-reader-text">`, `<label for="gnn-sitemap-excluded-ids">`, and responsive `.gnn-inline-desc` styling (`e6dd305`).
- **Updater & Uninstall:** Added `requires`/`tested`/`requires_php` metadata, `get_package_url()` HTTPS sanitization, and transient/multisite cleanup in `uninstall.php` (`e6dd305`).
- **CI/CD:** Upgraded `.github/workflows/release.yml` to `actions/checkout@v7` and `softprops/action-gh-release@v3`, adding `php -l` and `VERSION`/tag verification (`e6dd305`).
- **Release:** Bumped version to `1.1.2` (`058f211`); GitHub Release workflow `44238261167` succeeded.

### Tag v1.1.1 (2026-08-06)
- **Refactor:** Moved all plugin source files into a dedicated `gnn-sitemap/` root subdirectory for cleaner repository layout (`de05a83`).
- **Feature:** Added automated sitemap health check and culprit detection (`823ad9c`).
- **Bug Fix:** Removed self-destructive folder-rename step from `GNN_Sitemap_Updater` (`c551e93`).
- **Bug Fix:** Integrated WPMU DEV Hummingbird page cache purging on force-regenerate action (`eb7928b`).
- **Bug Fix:** Removed `activate_plugin()` call from updater which caused broken installs (`30f066e`).
- **Feature:** Added admin "Force Regenerate" action for stale or broken sitemaps (`b28c2a3`).
- **Feature:** Core GNN Sitemap release — `/sitemap.xml` alias, GNN plugin standard compliance, WP-CLI integration, GitHub release updater (`806e305`).

---

## Known Incomplete Work
- None identified in the current version (v1.1.3).

---

## Validation Status
- **Syntax Check:** Verified with `php -l` on all PHP files (`gnn-sitemap.php`, `includes/class-gnn-sitemap-updater.php`, `includes/class-gnn-sitemap-cli.php`, `uninstall.php`).
- **CI/CD:** GitHub Actions release workflow (`actions/checkout@v7`, `softprops/action-gh-release@v3`) verified via run `44647121825` (`v1.1.3`).
