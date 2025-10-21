# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Enhanced caching mechanisms
- REST API endpoints
- GraphQL support
- Admin dashboard improvements
- Multi-language support

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

3. **Database Schema**:
   ```bash
   # Run migration scripts (if provided)
   mysql -u root -p platformer < migrations/2.x_to_3.0.sql
   ```

4. **Code Updates**:
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

5. **Testing**: Thoroughly test all functionality

## Migration Tools

### Legacy API Compatibility

Version 3.x includes a compatibility layer for legacy code:
- Old methods still work but trigger deprecation notices
- Migrate to new API before 4.0.0 release
- See demo `04-legacy-compat.php` for migration guide

### Automated Migration

```bash
# Find legacy API usage
grep -r "getAttribute" src/
grep -r "getAllEntries" src/

# Use provided migration script (if available)
php tools/migrate-api.php
```

## Support

- **Documentation**: See README.md and CLAUDE.md
- **Issues**: [GitHub Issues](https://github.com/yourusername/platformer/issues)
- **Security**: See SECURITY.md
- **Contributing**: See CONTRIBUTING.md

---

[Unreleased]: https://github.com/yourusername/platformer/compare/v3.1.0...HEAD
[3.1.0]: https://github.com/yourusername/platformer/compare/v3.0.0...v3.1.0
[3.0.0]: https://github.com/yourusername/platformer/compare/v2.5.0...v3.0.0
[2.5.0]: https://github.com/yourusername/platformer/releases/tag/v2.5.0