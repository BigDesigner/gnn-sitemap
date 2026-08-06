# Task Pipeline

## Current Project State
- **Active Version**: v1.1.1
- **Status**: Sentinel Memory Bank initialized.
- **Sprint**: v1.1.1 maintenance & optimization.

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
- **Version Files**: `gnn-sitemap/VERSION` (`1.1.1`) and `gnn-sitemap/gnn-sitemap.php` header (`1.1.1`) are in sync.
