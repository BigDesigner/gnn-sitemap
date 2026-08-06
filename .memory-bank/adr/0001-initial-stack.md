# ADR 0001: WordPress Plugin Architecture Stack

- **Status**: Accepted
- **Confidence**: Verified
- **Date**: 2026-08-06

## Context
The project is a custom WordPress plugin named **GNN Sitemap** designed to utilize native WordPress Core sitemap infrastructure (`wp_sitemaps_init`) while adding custom admin controls, XML route aliasing (`/sitemap.xml`), WP-CLI support, and page cache flushing integrations.

## Decision
1. **Core Integration**: Build on top of WordPress Core sitemap functionality introduced in WP 5.5+.
2. **PHP Constraints**: Target PHP 7.4+ minimum requirement and WordPress 5.5+ compatibility.
3. **Module Structure**: Keep main entry point in `gnn-sitemap/gnn-sitemap.php` with modular helper classes in `gnn-sitemap/includes/` (`GNN_Sitemap_Updater`, `GNN_Sitemap_CLI`).
4. **Localization**: Store i18n translation assets in `gnn-sitemap/languages/` (`gnn-sitemap-tr_TR`).
5. **Updater**: Integrate self-hosted GitHub release update checker via `GNN_Sitemap_Updater`.

## Consequences
- Requires WordPress 5.5 or higher for core sitemap capabilities.
- Depends on external caching plugin functions (`w3tc_flush_all`, `rocket_clean_domain`, `litespeed_purge_all`, etc.) for clean sitemap cache clearing.

## Evidence
- `gnn-sitemap/gnn-sitemap.php` (Header declarations and core hooks)
- `gnn-sitemap/VERSION` (Version tracking)
- `gnn-sitemap/includes/class-gnn-sitemap-updater.php` (Updater implementation)
- `gnn-sitemap/includes/class-gnn-sitemap-cli.php` (WP-CLI integration)
