# Task Pipeline

## Current Project State
- **Active Version**: v1.1.3
- **Status**: Hardened & up to date with WP 7.1 / PHP 7.4+ / GitHub Actions latest.
- **Sprint**: v1.1.3 release.

---

## Immediate Next Actions
- [x] Run PHP linter check (`php -l`) on all plugin PHP files.
- [x] Verify GitHub release workflow behavior on tag creation (`v1.1.3` run `44647121825`).

---

## Backlog
- [x] Add automated PHP syntax check job to GitHub Actions (`.github/workflows/release.yml`).
- [ ] Add unit test suite (PHPUnit) for `GNN_Sitemap_Updater` and `gnn_sitemap_force_regenerate()`.
- [ ] Recompile `gnn-sitemap/languages/gnn-sitemap-tr_TR.mo` with `msgfmt` when a gettext environment is available.

---

## Blockers
- None.

---

## Release Readiness
- **Release Package**: Auto-built via `.github/workflows/release.yml` on `v*` tag push.
- **Version Files**: `gnn-sitemap/VERSION` (`1.1.3`) and `gnn-sitemap/gnn-sitemap.php` header (`1.1.3`) are in sync.
