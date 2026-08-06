# Bootstrap Specification

## Environment Prerequisites `[Verified]`
- **PHP**: 7.4 or higher (`[Verified]` from `gnn-sitemap.php` plugin header)
- **WordPress**: 5.5 or higher (`[Verified]` from `gnn-sitemap.php` plugin header)
- **WP-CLI**: Optional but recommended for CLI operations (`[Verified]` from `includes/class-gnn-sitemap-cli.php`)
- **Zip / Git**: Required for manual packaging or CI release workflow (`[Verified]` from `.github/workflows/release.yml`)

---

## Local Development & Setup `[Verified]`
1. Clone repository into WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/BigDesigner/gnn-sitemap.git
   ```
2. Activate the plugin via WP Admin (`Plugins -> Installed Plugins -> GNN Sitemap`) or via WP-CLI:
   ```bash
   wp plugin activate gnn-sitemap
   ```

---

## Validation & Linting Commands `[Inferred]`

| Command | Purpose | Requires Tool | Notes |
|---|---|---|---|
| `php -l gnn-sitemap/gnn-sitemap.php` | Syntax check main file | PHP CLI | Fast syntax check |
| `php -l gnn-sitemap/includes/*.php` | Syntax check include files | PHP CLI | Fast syntax check |
| `wp gnn-sitemap status` | Check sitemap status via WP-CLI | WP-CLI | Requires active WP site |
| `wp gnn-sitemap flush` | Force regenerate sitemap via WP-CLI | WP-CLI | Flushes rules & cache |

---

## CI/CD Pipelines `[Verified]`
- **Workflow File**: `.github/workflows/release.yml`
- **Triggers**: Push on tag matching `v*` (e.g. `v1.1.1`)
- **Runner**: `ubuntu-latest`
- **Artifact Output**: `build/gnn-sitemap-<version>.zip` attached to GitHub Release via `softprops/action-gh-release@v2`

---

## Local Setup Caveats `[Verified]`
- When testing the updater locally or on non-production sites, ensure the site can make outbound HTTP requests to `api.github.com`.
- After changing sitemap settings in WordPress admin, use "Force Regenerate" if a third-party caching plugin (W3 Total Cache, WP Rocket, LiteSpeed, Hummingbird, WP Engine Varnish) is active.
