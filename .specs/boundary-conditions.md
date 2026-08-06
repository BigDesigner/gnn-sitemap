# Boundary Conditions Specification

## Security Constraints & Input Validation `[Verified]`
1. **Direct Access Protection**: Every PHP file in the plugin MUST begin with an execution guard:
   ```php
   if ( ! defined( 'ABSPATH' ) ) { exit; }
   ```
2. **Input Sanitization & Escaping**:
   - All user inputs saved to `gnn_sitemap_settings` option MUST be sanitized using appropriate WordPress functions (`sanitize_text_field`, `absint`, `array_map`).
   - Output rendered in admin forms or public markup MUST be escaped using `esc_html()`, `esc_attr()`, `esc_url()`.
3. **Authorization & Access Control**:
   - Admin settings pages, AJAX handlers, and REST endpoints MUST enforce capability checks (`current_user_can( 'manage_options' )`).
   - Admin form submissions MUST verify nonces via `check_admin_referer()` or `wp_verify_nonce()`.

---

## Ecosystem-Specific Mitigations (WordPress) `[Verified]`
1. **Page Cache & CDN Invalidation**:
   - When sitemaps are regenerated or options updated, cache purging functions MUST be invoked defensively (`function_exists` checks for `rocket_clean_domain`, `w3tc_flush_all`, `litespeed_purge_all`, `wphb_clear_page_cache`, `WpeCommon::purge_varnish_cache`).
2. **Option Key Collisions**:
   - All WordPress option keys, action hooks, and constants MUST be prefixed with `gnn_sitemap_` or `GNN_SITEMAP_` to prevent plugin namespace collisions.

---

## Dependency & Cleanup Safety `[Verified]`
1. **Uninstallation Guard**: `uninstall.php` MUST check `defined( 'WP_UNINSTALL_PLUGIN' )` before executing cleanup.
2. **Database Hygiene**: On uninstall, options saved under `GNN_SITEMAP_OPT` (`gnn_sitemap_settings`) must be deleted via `delete_option()`.

---

## Deployment & CI/CD Integrity Contract `[Verified]`
1. **`(CI/CD) Credential-Config Alignment`**: The GitHub release workflow relies on `GITHUB_TOKEN` with `contents: write` permission (`.github/workflows/release.yml`).
2. **`(CI/CD) CLI/SDK Version Lock`**: GitHub Actions workflow uses pinned actions (`actions/checkout@v4`, `softprops/action-gh-release@v2`).
3. **`(CI/CD) Artifact-to-Job Completeness`**: The build step packages the exact `gnn-sitemap` directory matching the release tag version.
4. **`(CI/CD) Post-Push Live Pipeline Verification`**: Remote release pipeline execution must be monitored on git tag push (`git push origin vX.Y.Z`).

---

## End-to-End Integration Wiring Contract `[Verified]`
All sitemap features must verify the 5-link feature chain:
WordPress Core `wp_sitemaps_init` -> Custom Settings Option -> WP-CLI / Admin Settings UI -> `/sitemap.xml` Alias Rewrite Rule -> Browser / Search Engine Response.
