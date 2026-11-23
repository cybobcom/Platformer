# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.2.1] - 2026-11-23

### Update notes for instances
- `src/inc.localconf.php` must exist on servers (the example config is only used on localhost)
- Set `$arrConf['trusted_hosts']` on servers (the dashboard shows a warning otherwise)
- An `Address` override (custom/agent/admin) must extend `\Capps\Modules\Address\Classes\Address`
- `core/functions.php` is now `core/helpers.php`; own global functions go into `<vendor>/helpers.php`

### Added
- `CBSecret()` + `SECRETS` in `inc.localconf.php` for API keys and tokens
- `CBResponse('html'|'json')` to set the response type of a script
- `$arrConf['session_timeout']` (default 1800 seconds)
- Optional `<vendor>/helpers.php` for own global functions
- Dashboard warning if `trusted_hosts` is empty on a server
- Media upload: `$overwriteGroup` at the top of `uploadFile.php` (default admin)

### Changed
- Script routes (views, controller, api, interface, cron) closed by default (group admin), opened via `CBAuth()`
- Debug mode detected via `REMOTE_ADDR` (127.0.0.1/::1) instead of `SERVER_NAME`
- Fallback to `inc.localconf.example.php` on localhost only
- `$objPlatformUser` is created via `CBinitObject()` (vendor overrides apply)
- Media upload: targets below `data/media/` or `SECUREDIR`; extensions checked case-insensitive
- Final placeholder cleanup removes only unknown `###x###` (`[[...]]`, `%%...%%`, `@[...]` stay, e.g. JS arrays)
- `.htaccess`: `.well-known/` reachable again, existing files served with `[L]`

### Security
- Central CSRF check for cross-site POST/PUT/DELETE/PATCH (Sec-Fetch-Site/Origin, `trusted_hosts`)
- Login: "NULL" and "NOT ..." are never accepted as login (findAll() special values)
- Debug mode can no longer be enabled with a faked Host header
- Servers without `inc.localconf.php` no longer run with the public dev key
- `checkIntersection()`: empty entries and spaces ignored ("admin," no longer matches users without groups)
- Session timeout: the already loaded user is dropped in the same request
- Media upload: only `$overwriteGroup` may overwrite existing files, others get a unique `_UT` name
- MCP API: fields with roles are not filterable/searchable/sortable without that role
- `decryptValue()`: wrong key returns '' instead of the ciphertext
- `.htaccess`: dotfiles and bak/sql/log files blocked

### Removed
- `medialibrary/controller/addDocument.php`, `insertItem.php` (legacy, used undefined `connectClass()`)

## [3.2.0] - 2026-11-18

### Added
- `src/capps/VERSION`, dashboard shows the version and "Update verfügbar" (GitHub, checked daily)
- Override layers `src/admin`, `src/agent`, `src/custom` (priority custom > agent > admin > capps), never overwritten by updates
- `CBCore::resolveTemplatePath()`: CMS template paths are searched in all vendors
- Trusted hosts (`$arrConf['trusted_hosts']`)
- MCP API: content/structure modules, XML field wildcards, page routes
- Installer: enable on servers via `$arrConf['installer']`, status with token

### Changed
- Admin modules (`cms`, `dashboard`, `tools`, console login) moved to `src/capps/modules`
- Production mode by default, debug only on localhost
- Previews work without ffmpeg/exec

### Security
- Access defaults via `CBAuth` (views/controller: group admin)
- Login hardening (lockout, new session id, `hash_equals`)
- SQL injection and XSS fixes, CSRF checks
- MCP API: no raw SQL options, hidden fields, only configured fields writable
- `SECURITY.md` rewritten

### Removed
- Dead code and unused XML files
- `error_log` calls in application code

## [3.1.0] - 2025-01-21

### Added
- **Auto-Timestamps**: Automatic `date_created` and `date_updated` management in CBObject
- **Query Helpers**: New methods `count()`, `exists()`, `first()` for easier queries
- **Bulk Operations**: High-performance `insertBatch()` for large datasets
- **Soft Delete**: `softDelete()` and `restoreSoftDeleted()` methods
- **Smart Save**: `save()` method with auto-detection of create vs update
- **Column Helpers**: `hasColumn()` and `getColumns()` utility methods
- **XSS Protection**: Automatic HTML escaping for XML fields
- **Transaction Support**: Full ACID transaction support in CBDatabase
- **Connection Retry**: Automatic retry logic (3 attempts) for failed connections
- **UUID Generation**: `generateUuid()` method in CBDatabase
- **Demo Suite**: 5 interactive demos with browser interface
  - 01-basic-usage.php
  - 02-xml-fields.php
  - 03-crud-operations.php
  - 04-legacy-compat.php
  - 05-custom-class.php
- **Documentation**: Comprehensive CLAUDE.md, README.md, SECURITY.md, CONTRIBUTING.md
- **GitHub Ready**: .gitignore, configuration templates, and security guidelines

### Changed
- **Database Layer**: Completely simplified following KISS principle
  - Reduced to 2 standalone files (CBDatabase.php, CBObject.php)
  - Removed external dependencies (SecurityValidator, PerformanceMonitor, NullLogger, CBObjectFactory)
  - ~900 lines total (down from ~2000+)
- **Configuration**: New template system with `inc.localconf.example.php`
- **Security**: Enhanced credential protection, passwords never stored in memory
- **Performance**: Optimized bulk operations, smart caching

### Removed
- ❌ SecurityValidator.php (validation now built-in)
- ❌ PerformanceMonitor.php (simplified logging)
- ❌ NullLogger.php (using PHP's error_log)
- ❌ CBObjectFactory.php (object creation simplified)

### Fixed
- Database connection handling edge cases
- XML field parsing for special characters
- Memory leaks in long-running processes
- Transaction rollback consistency

### Security
- SQL injection protection (prepared statements)
- XSS protection (automatic escaping)
- Credential security (no memory storage)
- Session security improvements
- Input sanitization enhancements

## [3.0.0] - 2024-12-01

### Added
- Production-ready release
- Modern PHP 8.0+ features (strict types, type hints)
- PSR-4 compliant autoloader
- Namespace support (both lowercase and uppercase)
- XML field support (data_, media_, settings_)
- Module-based architecture
- Custom routing system
- User authentication and authorization
- Template system

### Changed
- Complete codebase refactoring
- KISS principle implementation
- Improved security
- Enhanced performance
- Better documentation

### Deprecated
- Legacy API methods (getAttribute, setAttribute, getAllEntries)
  - Still functional but marked for removal in 4.0.0
  - Use modern API instead (get, set, findAll)

## [2.x] - Legacy Versions

### [2.5.0] - 2024-06-15
- Last version before major refactoring
- Legacy API
- Original architecture

### Earlier Versions
See git history for older versions.

---

## Version Numbering

We use [Semantic Versioning](https://semver.org/):
- **MAJOR**: Breaking changes
- **MINOR**: New features (backwards-compatible)
- **PATCH**: Bug fixes (backwards-compatible)

## Upgrade Guides

### Upgrading from 3.0 to 3.1

1. **Database Classes** (no breaking changes):
   ```php
   // Old way (still works)
   $user = new CBObject(null, 'users', 'user_id');
   $user->create(['name' => 'John']);

   // New way (recommended)
   $user = new CBObject(null, 'users', 'user_id');
   $user->set('name', 'John');
   $user->save(); // Auto-detects create vs update
   ```

2. **New Query Helpers**:
   ```php
   // Count records
   $total = $user->count();
   $active = $user->count(['active' => '1']);

   // Check existence
   if ($user->exists(['email' => $email])) {
       // Handle duplicate
   }

   // Get first match
   $admin = $user->first(['role' => 'admin']);
   ```

3. **Bulk Operations**:
   ```php
   // Old way (loop)
   foreach ($data as $row) {
       $user->create($row);
   }

   // New way (bulk)
   $ids = $user->insertBatch($data);
   ```

4. **Configuration**:
   - No changes required if using existing `inc.localconf.php`
   - For new installations, use `inc.localconf.example.php` as template

### Upgrading from 2.x to 3.x

**This is a major upgrade with breaking changes!**

1. **PHP Version**: Upgrade to PHP 8.0+
   ```bash
   php -v  # Verify version
   ```

2. **Configuration**:
   ```bash
   # Backup old config
   cp src/capps/inc.localconf.php src/capps/inc.localconf.php.bak

   # Use new template
   cp src/capps/inc.localconf.example.php src/capps/inc.localconf.php
   # Migrate settings from backup
   ```

3. **Code Updates**:
   - Update to modern API:
     ```php
     // Old
     $user->getAttribute('name');
     $user->setAttribute('name', 'John');
     $user->getAllEntries();

     // New
     $user->get('name');
     $user->set('name', 'John');
     $user->findAll();
     ```

   - Use type hints:
     ```php
     // Old
     public function process($data) { }

     // New
     public function process(array $data): bool { }
     ```

4. **Testing**: Thoroughly test all functionality

## Migration Tools

### Legacy API Compatibility

Version 3.x includes a compatibility layer for legacy code:
- Old methods still work but trigger deprecation notices
- Migrate to new API before 4.0.0 release
- See demo `04-legacy-compat.php` for migration guide

## Support

- **Documentation**: See README.md and CLAUDE.md
- **Issues**: [GitHub Issues](https://github.com/cybobcom/Platformer/issues)
- **Security**: See SECURITY.md
- **Contributing**: See CONTRIBUTING.md

---

[Unreleased]: https://github.com/cybobcom/Platformer/compare/v3.2.0...HEAD
[3.2.0]: https://github.com/cybobcom/Platformer/compare/v3.1.0...v3.2.0
[3.1.0]: https://github.com/cybobcom/Platformer/compare/v3.0.0...v3.1.0
[3.0.0]: https://github.com/cybobcom/Platformer/compare/v2.5.0...v3.0.0
[2.5.0]: https://github.com/cybobcom/Platformer/releases/tag/v2.5.0