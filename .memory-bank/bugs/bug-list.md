# Bug List & Environment Tracking

| ID | Category | Description | Source / Evidence | Confidence | Status | Suggested Action |
|---|---|---|---|---|---|---|
| BUG-001 | Environment Warning | No automated PHP linting or PHPUnit testing workflow configured in CI/CD pipeline | `.github/workflows/` | Verified | Open | Add a PHP syntax check / PHPUnit job in GitHub Actions before building release packages |
