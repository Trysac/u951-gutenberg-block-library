# Stack

## Used

| Tool | Why |
|---|---|
| PHP 8.4 | Security support, `declare(strict_types=1)`, and typed constants. |
| Composer 2 | Standard package management with strict PSR-4 autoloading. |
| PHP-CS-Fixer | Enforces PER Coding Style. |
| PHPStan (level max) | Static analysis for type and logic errors. |
| PHPUnit + pcov | Test execution and coverage tracking. |
| OSV-Scanner | Vulnerability scanning for `composer.lock`. |
| gitleaks | Secret scanning. |
| GitHub Actions | CI/CD automation. |

## Discarded

| Tool | Why not |
|---|---|
| Psalm | PHPStan provides sufficient strict analysis. |
| Eris | Data providers are sufficient for property testing. |
| A `version` in composer.json | Versioning is handled by git tags on the repository. |