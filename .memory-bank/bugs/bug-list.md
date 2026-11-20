# Bug List & Environment Tracking

| ID | Category | Description | Source / Evidence | Confidence | Status | Suggested Action |
|---|---|---|---|---|---|---|
| BUG-001 | Environment Warning | Automated PHP syntax check (`php -l`) is active in CI, but no PHPUnit test suite is configured | `.github/workflows/release.yml` | Verified | Open | Add a PHPUnit test suite for `GNN_Sitemap_Updater` and `gnn_sitemap_force_regenerate()` |
