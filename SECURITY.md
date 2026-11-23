# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 3.1.x   | :white_check_mark: |
| 3.0.x   | :white_check_mark: |
| < 3.0   | :x:                |

## Reporting a Vulnerability

Please **do not** report security vulnerabilities through public GitHub issues.

Use GitHub's **Report a vulnerability** button in the **Security** tab of this repository (private report).

Include a description, steps to reproduce, affected versions and potential impact.
We answer within 48 hours, assess within one week and coordinate fix and disclosure with you
(responsible disclosure, credit with your permission).

## Built-in Protections

| Area | Protection |
|------|------------|
| Access control | `CBAuth` checked by CBCore **before** a script runs. All script routes (views, controllers, api, interface, cron): group `admin` by default; `CBAuth('')` = logged-in users, `CBAuth('public')` = everyone, `CBAuth('xyz')` = group `xyz`. No permission: HTML = silent redirect, JSON = 401/403 |
| Host header | `trusted_hosts` in the configuration: requests with other hosts are rejected (BASEURL is built from the Host header) |
| Routing | Route parts only `[A-Za-z0-9_-]` (no path traversal into includes); unknown routes return 404 |
| SQL injection | Prepared statements for all values (`CBDatabase`, `CBObject`); identifiers only via `validNames()`; raw SQL options (`rawWhere`, `select`, `join`) for trusted code only, values via `rawParams`; safety net blocks request input embedded in `rawWhere` |
| XSS | Request values escaped on output (`htmlspecialchars`, `json_encode` with HEX flags in JS); `###request_*###` placeholders escaped; `sanitizeXmlValue()` as safety net for XML fields. CMS content (HTML) is output unescaped on purpose: editing content is an admin right |
| CSRF | CBCore blocks cross-site POST/PUT/DELETE/PATCH to protected scripts (`Sec-Fetch-Site`, else `Origin`/`Referer` vs. own host + `trusted_hosts`; no header = allowed; `CBAuth('public')` exempt). Token additionally injected into `<head>` and every `<form>`, to be checked in the script; `SameSite=Strict` session cookie; the page cache never stores a session token |
| Sessions | `httponly`, `SameSite=Strict`, strict mode, `secure` on HTTPS; new session id on login and logout; 30 min inactivity timeout |
| Login | Timing-safe compare; lockout after 5 failed attempts per IP + login for 15 minutes (file-based, not bypassable by dropping the cookie) |
| Uploads | Extension whitelist on upload **and** rename; target folders only below `data/media/`; `public/data/media/.htaccess` blocks script execution |
| File delivery | Only via a media library record, realpath below `data/` (never a path from the request) |
| Shell | All arguments via `escapeshellarg`; missing ffmpeg/ImageMagick/exec falls back without errors |
| Errors | Debug mode only on localhost; production: no error display, errors in the PHP error log; DB error details never sent to clients (exception: installer, admin only) |
| MCP API | Token auth; only `order`/`direction`/`limit`/`search` options; passwords, tokens and encrypted values never readable; only fields listed in `mcp.json` writable |
| Installer | Admin only; on servers additionally enabled via config; `status` for remote compare only with token |

### Known Trade-offs

- **Passwords are encrypted reversibly** (AES-256-CBC with `ENCRYPTION_KEY32`), not hashed: admins must be able to look up and set passwords (e.g. exam numbers used as passwords). Protect `ENCRYPTION_KEY32` and database backups accordingly.
- **AES-256-CBC without MAC** for `encrypted_*` fields (existing data format).
- **MCP tokens** are stored in plaintext so admins can look them up again; treat database access as full API access.

## Before Deployment

### 1. Configuration

Without `src/inc.localconf.php` the local defaults from `src/inc.localconf.example.php` are used. **Never run a server with these defaults.**

```bash
cp src/inc.localconf.example.php src/inc.localconf.php   # git-ignored
```

In `src/inc.localconf.php` set:

- `DATABASE`: own database user and password (not `root`)
- `ENCRYPTION_KEY32`: 32 random characters, **before** any data is stored (changing it later makes encrypted values unreadable)
- `$arrConf['debug']['token']`: long random token, or `''` (debugger off)
- `$arrConf['installer']`: `false` (set `true` only while you import/export modules)
- `$arrConf['trusted_hosts']`: your domains, e.g. `['www.example.com', 'example.com']` (`*.example.com` for subdomains)
- `SECRETS`: API keys and other access data, read in code only with `CBSecret('name')` (never in code, logs or chats)

`src/inc.localconf.php` is per environment: not in git, **never deployed** (exclude it in your upload tool). Create it once by hand on each server; locally use test keys only.

```bash
php -r "echo bin2hex(random_bytes(16));"   # 32 characters
```

### 2. File Permissions

```bash
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# writable by the web server
chmod -R 775 public/data/ cache/

# configuration not readable by others
chmod 640 src/inc.localconf.php
```

The document root must be `public/` (so `src/` and `cache/` are not reachable via HTTP).

### 3. Database

```sql
CREATE USER 'platformer'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX ON platformer.* TO 'platformer'@'localhost';
```

`CREATE`, `ALTER` and `INDEX` are needed by the installer ("Schema ausfuehren") and the MCP module (adds its token columns).

### 4. Web Server and PHP

- HTTPS only (redirect HTTP to HTTPS); Platformer sends HSTS in production
- Platformer sends `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` itself
- `Options -Indexes`, `expose_php = Off`, `display_errors = Off`, `log_errors = On`
- Keep `allow_url_fopen` on if you use the installer compare with other instances

## Secure Coding Guidelines

```php
// Access: first line of a view/controller (default: group admin)
CBAuth('');            // logged-in users

// SQL: values only via placeholders
$rows = $obj->findAll(['login' => $login]);
$rows = $obj->findAll([], ['rawWhere' => 'name LIKE ?', 'rawParams' => ['%' . $term . '%']]);

// Output: escape request values
echo htmlspecialchars($value, ENT_QUOTES);
?><script>var id = <?php echo json_encode($id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script><?php

// Errors: never show getLastError() to users (contains query and params)
```

Further rules: see `CLAUDE.md` (Security Considerations, Access Control, Database Rules).

## Checklist Before Going Live

- [ ] Own `src/inc.localconf.php` (not the example defaults)
- [ ] Own database user, not `root`, strong password
- [ ] New `ENCRYPTION_KEY32` before storing data
- [ ] Debug token random or `''`; installer `false`; `trusted_hosts` set
- [ ] Document root is `public/`; HTTPS enforced
- [ ] `cache/` and `public/data/` writable, configuration `640`
- [ ] Views/controllers used by non-admins marked with `CBAuth('')` (or a group)
- [ ] Backups of database and `public/data/` tested

## Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Guide](https://www.php.net/manual/en/security.php)

---

**Last Updated**: 2026-11-18
**Version**: 3.1
