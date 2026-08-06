# Engineering Constitution

## 1. Code Quality & Formatting
- All PHP code must adhere to WordPress Coding Standards (WPCS).
- Always run `php -l` on modified PHP files prior to proposing git commits.
- Maintain readable indentations, explicit docblocks for functions, and avoid anonymous un-hookable functions where named callbacks provide better extensibility.

## 2. Security Standards
- Direct execution guards (`defined('ABSPATH') || exit;`) are mandatory in every PHP file.
- Unsanitized superglobals (`$_POST`, `$_GET`, `$_REQUEST`) are strictly forbidden.
- Always sanitize inputs and escape outputs according to WordPress security guidelines.

## 3. Versioning & Backward Compatibility
- Follow Semantic Versioning (`MAJOR.MINOR.PATCH`).
- Keep `gnn-sitemap/VERSION` synchronized with the plugin header version in `gnn-sitemap/gnn-sitemap.php`.
- Tag releases with format `vX.Y.Z` to trigger automated GitHub Release workflow.

## 4. Commit Hygiene & Agent Standards
- Commit messages must follow Conventional Commits standard (e.g. `feat:`, `fix:`, `refactor:`, `chore:`).
- AI Agents must never commit directly without explicit user approval in Interactive mode.
- AI Agents must not modify core WordPress code or third-party files.
