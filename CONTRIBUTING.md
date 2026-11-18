# Contributing to Platformer

Thanks for helping! Keep it short and simple.

## Setup

```bash
git clone https://github.com/cybobcom/Platformer.git
```

Runs on MAMP out of the box (defaults from `src/inc.localconf.example.php`).
For your own config copy it to `src/inc.localconf.php` (git-ignored).

## Workflow

1. Open an issue first for larger changes.
2. Branch: `feature/...` or `fix/...`
3. Commit message: `type(scope): subject` (types: `feat`, `fix`, `docs`, `refactor`, `chore`)
4. Pull request against `main` with a short "what and why".

## Rules

- **KISS**: simplest solution that works, minimal changes, no abstractions "just in case".
- **Zero dependencies**: no external PHP libraries.
- **English** for code, comments and commit messages.
- **CB prefix** for framework classes and functions (`CBObject`, `CBinitObject`).
- **Database**: follow the rules in `CLAUDE.md` (placeholders, `validNames()`, `hasError()`).
- **Security**: escape output, check CSRF on POST, see `SECURITY.md`.
- Update `CHANGELOG.md` and `CLAUDE.md` if behavior or architecture changes.

## Bugs and security

- Bugs: [GitHub Issues](https://github.com/cybobcom/Platformer/issues) (PHP/MySQL version, steps to reproduce)
- Security issues: not public, see `SECURITY.md`

License: by contributing you agree that your code is released under the MIT license.
