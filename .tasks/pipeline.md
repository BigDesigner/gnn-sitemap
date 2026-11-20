# Task Pipeline

## Current Project State
- **Active Version**: v1.1.3
- **Status**: Hardened & up to date with WP 7.1 / PHP 7.4+ / GitHub Actions latest.
- **Sprint**: v1.1.3 release.

---

## Immediate Next Actions
- [ ] Run PHP linter check (`php -l`) on all plugin PHP files.
- [ ] Verify GitHub release workflow behavior on tag creation.

---

## Backlog
- [ ] Add automated PHP syntax check job to GitHub Actions (`.github/workflows/release.yml` or dedicated CI check).
- [ ] Add unit test suite (PHPUnit) for `GNN_Sitemap_Updater` and `gnn_sitemap_force_regenerate()`.

---

## Blockers
- None.

---

## Release Readiness
- **Release Package**: Auto-built via `.github/workflows/release.yml` on `v*` tag push.
- **Version Files**: `gnn-sitemap/VERSION` (`1.1.3`) and `gnn-sitemap/gnn-sitemap.php` header (`1.1.3`) are in sync.
