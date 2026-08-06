# ADR 0002: GitHub Actions Automated Tag Release Pipeline

- **Status**: Accepted
- **Confidence**: Verified
- **Date**: 2026-08-06

## Context
Automated packaging and distribution is required whenever a new version tag (e.g. `v1.1.1`) is pushed to the GitHub repository.

## Decision
Use GitHub Actions workflow (`.github/workflows/release.yml`) triggered on tag pushes (`v*`):
1. Checkout code (`actions/checkout@v4`).
2. Extract clean version string from tag ref (`v1.1.1` -> `1.1.1`).
3. Package `gnn-sitemap/` directory into a distribution zip archive (`gnn-sitemap-1.1.1.zip`).
4. Publish a GitHub Release using `softprops/action-gh-release@v2` with the zip artifact attached.

## Consequences
- Releases are strictly tied to git tag format `v*`.
- The plugin updater (`GNN_Sitemap_Updater`) polls GitHub releases (`BigDesigner/gnn-sitemap`) for updates.

## Evidence
- `.github/workflows/release.yml`
- `gnn-sitemap/includes/class-gnn-sitemap-updater.php`
