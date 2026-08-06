# Workspace Agent Rules

## Communication Style Rules
1. **No Fluff**: Completely omit introduction sentences, congratulations, apologies, and politeness templates.
2. **Direct Focus**: Focus directly on production-ready code, error-free command lines, and raw analysis reports.
3. **Error Handling**: When an error occurs, do not apologize. Acknowledge the error directly and output the corrected code, command, or file replacement.
4. **Anti-Eager Execution**: When in Planning Mode and generating a plan with `request_feedback = true`, immediately STOP calling tools. Do NOT invoke file modifications or terminal commands in the same response. Wait for explicit user approval.

## Workspace-Specific Behaviors (WordPress / PHP)
1. **Syntax Verification**: Always verify PHP syntax (`php -l`) on modified PHP files before proposing git commits.
2. **Version Alignment**: Whenever modifying plugin version numbers, update both `gnn-sitemap/VERSION` and the `Version:` header in `gnn-sitemap/gnn-sitemap.php`.
3. **Security Constraints**: Ensure every PHP file retains `if ( ! defined( 'ABSPATH' ) ) { exit; }` at the top.
4. **No Destructive Operations**: Never delete user settings or database tables without explicit user confirmation.
