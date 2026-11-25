# ADR 0003: Unified GNN GitHub Updater Standard

- **Status**: Accepted
- **Confidence**: Verified
- **Date**: 2026-11-21

## Context
`GNN_Sitemap_Updater` previously lacked:
1. `upgrader_pre_install` / `upgrader_post_install` lifecycle hooks for capturing active state, normalizing the plugin directory, and restoring activation.
2. HTTP 302 `/releases/latest` + `/releases/expanded_assets/{tag}` fallback when the GitHub API is rate-limited.
3. Negative caching (`300s`) for failed lookups, semver tag validation, `codeload.github.com` / `api.github.com` / `*.githubusercontent.com` SSRF allowlist coverage, and `no_update` transient population.
4. `load-update-core.php` cache clearing and `update_plugins` capability enforcement (with HTTP 403) on manual checks.

## Decision
Standardize `GNN_Sitemap_Updater` (`gnn-sitemap/includes/class-gnn-sitemap-updater.php`) on the Unified GNN GitHub Updater architecture while preserving the constructor signature `($plugin_file, $version, $github_owner, $github_repo)`, transient key (`gnn_sitemap_gh_release`), GET parameter (`gnn_sitemap_check_update`), and nonce (`gnn_sitemap_manual_update`):
1. **Hooks**: `pre_set_site_transient_update_plugins`, `plugins_api` (priority `20`, `3` args), `upgrader_pre_install`, `upgrader_post_install` (priority `10`, `3` args), `admin_init`, and `load-update-core.php`.
2. **Release Resolution & Fallback**: Query GitHub Releases API (`12h` cache) with `WordPress/{ver}; {home_url}` User-Agent; fall back to `HEAD /releases/latest` 302 `Location` tag extraction and `/releases/expanded_assets/{tag}` scraping; cache failures as `(object) ['failed' => true]` for `300s`.
3. **Security & Validation**: Enforce semver tag format (`/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/`), HTTPS-only SSRF allowlist (`github.com`, `api.github.com`, `codeload.github.com`, `objects.githubusercontent.com`, `release-assets.githubusercontent.com`, `*.githubusercontent.com`), and `update_plugins` capability with `403` `wp_die()` on manual checks.
4. **Update Transient & Lifecycle**: Populate both `$transient->response` and `$transient->no_update`; normalize `$result['destination']` via `WP_Filesystem` in `after_install()` and restore active state.

## Consequences
- Resilient self-updates even under GitHub API rate limiting.
- Consistent `no_update` reporting and safe directory normalization during WordPress core upgrades.

## Evidence
- `gnn-sitemap/includes/class-gnn-sitemap-updater.php` (`php -l` verified)


## Amendment (2026-11-25): Disk-First Local Version Detection
- **Root Cause:** Reading in-memory version constants (`GNN_*_VERSION` / `$this->version`) first inside `get_local_version()` returned the pre-upgrade version when WordPress fired `pre_set_site_transient_update_plugins` within the same PHP request right after overwriting plugin files, causing the update banner to persist until a second update run.
- **Resolution:** `get_local_version()` now reads the live `Version` header directly from `WP_PLUGIN_DIR . '/' . $this->plugin_basename` via `clearstatcache( true, $main_file )` and `get_file_data()` first, falling back to in-memory constants only if the file is unavailable.
