# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Platformer is a PHP-based modular CMS/application framework running on MAMP with a custom MVC-like architecture. The system uses a module-based structure with XML data storage, custom routing, and a database abstraction layer.

## Development Environment

**Server**: MAMP (Apache + MySQL + PHP)
- Document Root: `/Applications/MAMP/htdocs/Platformer`
- Entry Point: `public/index.php`
- PHP Version: Modern PHP with strict types enabled in core modules

**Database**:
- Type: MySQL
- Host: localhost
- Database: platformer
- User: root / Password: root (defaults in `src/inc.localconf.example.php`)
- Port: Default (3306)

**Running the Application**:
```bash
# Access via MAMP at:
# http://localhost:8888/Platformer/public/
# Fresh clone works at once: without src/inc.localconf.php the defaults from
# src/inc.localconf.example.php are used (own config: copy it, it is git-ignored)
```

## Core Architecture

### Bootstrap Sequence

1. **Entry Point**: `public/index.php`
   - Overload protection (`core/overload.php`, optional)
   - Debug mode only on localhost (`SERVER_NAME` localhost/127.0.0.1), everywhere else production
   - Session (httponly, samesite, strict mode)
   - Loads `core/functions.php` and the configuration
   - Trusted hosts: `$arrConf['trusted_hosts']` (e.g. `['www.example.com', '*.example.com']`, `[]` = all): other Host headers get 400
   - `?cbdebug=TOKEN&show_config=1`: config output (token from `CONFIGURATION['debug']['token']`)
   - Page cache (`core/cache.php`, optional), autoloader, module namespaces
   - Loads user (`$objPlatformUser`), structure (`$coreArrSortedStructure`), routes (`$coreArrRoute`)
   - Runs `CBCore`

2. **Configuration**: `src/inc.localconf.php` (git-ignored; fallback `src/inc.localconf.example.php`)
   - Only sets values: constants (BASEURL, BASEDIR, SOURCEDIR, ...), `CONFIGURATION`, `DATABASE`, `ENCRYPTION_KEY32`
   - No output, no auth rules, no error_reporting
   - SECURITY: on servers replace DB credentials, `ENCRYPTION_KEY32` and the debug token, set `trusted_hosts`

3. **Application**: `src/capps/modules/core/classes/CBCore.php`
   - Request routing (page or script route), auth check, security headers
   - Template rendering (CBParser for `<cb:...>` tags, `###placeholders###`)

### Module Structure

Modules are located in vendor folders under `src/` (`src/capps/modules/` core, `src/custom/modules/` customizations, also `src/admin/modules/`, `src/agent/modules/`):

```
modules/
├── core/           # Core system functionality
│   ├── classes/    # CBCore, CBParser, CBController, CBAutoloader, CBDebugger
│   ├── cache.php   # Page cache (optional)
│   ├── overload.php # Load shedding (optional)
│   └── functions.php # Global functions
├── database/       # Database layer
│   ├── classes/    # CBDatabase.php, CBObject.php (standalone)
│   ├── demo/       # Demos (01-quickstart, 02-xml-fields, 03-advanced), may be outdated
│   └── tests/      # Test database schema and fixtures
├── address/        # User/address management
│   ├── classes/    # Address.php
│   ├── controller/ # CRUD operations
│   └── views/      # Templates
├── content/        # Content management
├── structure/      # Site structure/navigation
└── [custom]/       # Custom modules in src/custom/modules/
```

### Key Classes and Patterns

**CBAutoloader** (`src/capps/modules/core/classes/CBAutoloader.php`):
- PSR-4 compliant autoloader
- Supports both lowercase (`capps\modules\core\classes\`) and uppercase (`Capps\Modules\Core\Classes\`) namespaces
- Automatically registers all modules in capps/ and custom/ directories


**CBDatabase** (`src/capps/modules/database/classes/CBDatabase.php`, ~400 lines):
- Standalone PDO wrapper (MySQL), zero dependencies, KISS
- Prepared statements, connection retry (3 attempts), transactions, `generateUuid()`
- Errors: no exceptions after connect, methods return `false`/`[]`/`null` and set `hasError()`/`getLastError()`
- Optional query hook: `CBDatabase::$onQuery = callable(string $query, float $startTime)` (null = no overhead)
- One connection per request: `CBDatabase::shared()`

**CBObject** (`src/capps/modules/database/classes/CBObject.php`, ~1400 lines):
- ORM-like record handler, only depends on CBDatabase (uses `CBDatabase::shared()`)
- XML container columns `data`, `media`, `settings`, `encrypted` → attributes `data_*`, `media_*`, `settings_*`, `encrypted_*`
- `encrypted_*` and `$arrEncryptedFields`: AES-256-CBC with `ENCRYPTION_KEY32` (plaintext if no key)
- Localization via `localize` column and `$lang` parameter
- Auto-timestamps (`date_created`, `date_updated`), soft delete (`deleted_at`)
- Modern API: `create()`, `update()`, `save()`, `delete()`, `softDelete()`, `findAll()`, `first()`, `count()`, `exists()`, `insertBatch()`, `get()`, `set()`
- Legacy API (deprecated, works): `getAttribute()`, `setAttribute()`, `getAllEntries()`, `saveContentNew()`, `saveContentUpdate()`, `deleteEntry()`, `saveObject()`

**CBinitObject** (`src/capps/modules/core/functions.php`):
- Global function for dynamic object instantiation
- Auto-detection: Class name becomes module name (e.g., `CBinitObject("User")` → `user/User`)
- Vendor support: `CBinitObject("custom:User")` for custom modules
- Fallback mechanism: Tries custom vendor first, then capps
- Caching for performance

### Database Conventions

**Table Structure**:
- Primary keys: Either `{table}_id` (auto-increment) or `{table}_uid` (UUID, generated by CBObject::create())
- XML fields: `data`, `media`, `settings`, `encrypted` (CDATA format)
- Localization: `localize` column (XML per language)
- Timestamps: `date_created`, `date_updated`; soft delete: `deleted_at`
- Groups: `addressgroups` (comma-separated, also as `arrAddressgroups`)

**XML Field Access**:
```php
// Database: data column contains XML
// Object attribute: data_description, data_title, etc.
$object->get('data_description');                 // Parsed CDATA content
$object->update(['data_title' => 'New Title']);   // Merged into the stored XML
$object->update(['data_title' => null]);          // null removes the key from the XML
```
Note: `set()` only changes the object in memory, it does not save.

### Routing System

**Page routes** (CMS pages):
- Routes are stored in `capps_route` and cached in `$coreArrRoute` (`structure_id:content_id:address_uid` → route)
- Unknown route: start page with status 404; `index.php?sid=5` (fallback links) shows page 5
- `$_REQUEST['structure_id']` is always the resolved page
- Access: addressgroups of page/element (`checkIntersection`)

**Script routes** (`/views/`, `/controller/`, `/api/`, `/interface/`, `/cron/` + module + option):
- Route parts only `[A-Za-z0-9_-]` (otherwise 404)
- Searched in all vendors (custom > agent > admin > capps)

### Access Control (CBAuth)

Checked by CBCore BEFORE the script is included (direct call via CBroute only):

| In the script | Allowed for |
|---|---|
| nothing (views/, controller/) | group `admin` |
| nothing (api/, interface/, cron/) | everyone |
| `CBAuth('public');` | everyone |
| `CBAuth('');` | logged-in users |
| `CBAuth('xyz');` | group `xyz` (`capps_addressgroup.entity`) |

- Write it as one line at the start of a line with a literal string (CBCore reads it as text)
- No permission: HTML = silent redirect to BASEURL, JSON = 401/403
- Views embedded by the CMS (element templates): `CBAuth` has no effect, page/element addressgroups apply

### User System

User management via Address module:
- User object: `$objPlatformUser` (global)
- Session: `$_SESSION[PLATFORM_IDENTIFIER]["login_verified"]`, `["login_user_identifier"]`, `["last_activity"]` (30 min timeout)
- `PLATFORM_IDENTIFIER` = md5 of the lowercased config path (same session for /Platformer/ and /platformer/)
- Login (`address/controller/doLogin.php`): `hash_equals`, new session id, lockout 5 failed attempts / 15 min per IP + login (files in `cache/login/`)
- Passwords are encrypted (reversible, `ENCRYPTION_KEY32`): admins must be able to see and set them
- Address groups for permissions (`addressgroups`, comma-separated entities)

## Common Development Tasks

### Creating a New Module

1. Create module directory: `src/capps/modules/mymodule/` or `src/custom/modules/mymodule/`
2. Add classes directory: `mymodule/classes/`
3. Create main class: `MyModule.php` with namespace `Capps\Modules\Mymodule\Classes\`
4. Autoloader will automatically register the module
5. Use via: `$obj = CBinitObject("MyModule");`

### Working with Database Objects

```php
use Capps\Modules\Database\Classes\CBObject;

// Create (date_created/date_updated and *_uid are added automatically)
$user = new CBObject(null, 'capps_address', 'address_uid');
$userId = $user->create([
    'login' => 'john@example.com',
    'data_name' => 'John Doe',
]);
if ($userId === false) {
    // log $user->getLastError() (never show it to users)
}

// Load + update
$user = new CBObject($userId, 'capps_address', 'address_uid');
$user->update(['data_name' => 'Jane Doe']);           // current record
$user->update(['active' => '1'], $otherId);           // other record, object stays unchanged

// Load by other column
$user->load('login:john@example.com');

// Save = update if found, else insert (conditions are added on insert)
$id = $user->save(['data_name' => 'John'], ['login' => 'john@example.com']);

// Find
$rows = $user->findAll(
    ['active' => '1', 'addressgroup' => [1, 2], 'deleted_at' => 'NULL'],   // =, IN(), IS NULL
    ['order' => 'date_created|login', 'direction' => 'DESC|ASC', 'limit' => '0,10']
);
$rows = $user->findAll([], ['search' => ['term' => $q, 'fields' => ['login', 'data']]]);
$rows = $user->findAll([], ['rawWhere' => 'date_created > ?', 'rawParams' => [$date]]);
if ($rows === [] && $user->objDatabase->hasError()) {
    // error, not "no rows"
}

$first = $user->first(['login' => 'john@example.com']);   // new object or null
$total = $user->count(['active' => '1']);
$taken = $user->exists(['login' => 'john@example.com']);

// Batch: create() per record inside one transaction, all-or-nothing
$ids = $user->insertBatch([['login' => 'a@test.com'], ['login' => 'b@test.com']]);

// Soft delete
$user->softDelete($userId);
$user->restoreSoftDeleted($userId);
```

Condition values: plain value (`=`), `'NULL'` (IS NULL), `'NOT x'` (NOT LIKE), array (IN).
Ordering by `data_*`/`media_*`/`settings_*` is done in PHP after the query (after LIMIT).

### Database Queries

```php
use Capps\Modules\Database\Classes\CBDatabase;

$db = CBDatabase::shared();                 // default config (DATABASE constant), one per request

$rows = $db->get("SELECT * FROM capps_address WHERE login LIKE ? AND active = ?", ['%@example.com', '1']);
$row  = $db->selectOne("SELECT * FROM capps_address WHERE address_uid = ?", [$uid]);   // null if none

$id = $db->insert('capps_address', ['address_uid' => $db->generateUuid(), 'login' => 'x'], 'address_uid');
$db->update('capps_address', ['login' => 'y'], 'address_uid = ?', [$id]);
$db->delete('capps_address', 'address_uid = ?', [$id]);

// Transactions: methods return false on error, no try/catch needed
$db->beginTransaction();
if ($db->insert('a', ['x' => 1]) === false || $db->insert('b', ['y' => 2]) === false) {
    $db->rollback();
} else {
    $db->commit();
}

if ($db->hasError()) {
    // log $db->getLastError() (contains query + params, never output to users)
}
```

### Database Rules (must follow)

- Errors: return `false`/`[]`/`null` + `lastError`; no try/catch in application code, no `error_log`
- Reading: tell "error" and "no rows" apart with `hasError()`
- Identifiers (table/column/order names): only via `CBDatabase::validNames()`
- Values: always via `?` placeholders and `$params`; never concatenate user input into SQL
- Raw SQL options (`select`, `join`, `rawWhere`, `$selection`): trusted code only, values via `rawParams`/`$selectionParams`
- Never pass options from a request/API body to `findAll()` unfiltered (allow only `order`, `direction`, `limit`, `search`)
- One connection per request: `CBDatabase::shared()` (CBObject does this)
- Never change existing UIDs, stored data formats or performance characteristics
- `findAll()`, `exists()`, `count()` duplicate the WHERE logic on purpose: keep them in sync

### Adding a Controller

Controllers are in `modules/{module}/controller/`:
```php
// src/capps/modules/mymodule/controller/doSomething.php
<?php

CBAuth('');   // optional: logged-in users (default for controllers: group admin)

$obj = CBinitObject("MyModule");
// Process request
header('Content-Type: application/json');
echo json_encode(['success' => true]);
```

### Creating Views

Views are in `modules/{module}/views/`:
- Use master templates from `public/data/template/views/`
- Template types: `mastertemplate.html`, `mastertemplate_admin.html`, `mastertemplate_console.html`
- Access globals: `BASEURL`, `BASEDIR`, `$objPlatformUser`
- Default access: group admin (see Access Control)

## Important Patterns

### Namespace Conventions

Both lowercase and uppercase namespaces are supported:
```php
// Lowercase (legacy)
use capps\modules\core\classes\CBCore;

// Uppercase (modern, preferred)
use Capps\Modules\Core\Classes\CBCore;
```

### Global Functions

Key functions in `core/functions.php`:
- `CBinitObject($class, $value = null)` - Dynamic object instantiation
- `validateEmail($email)` - Email validation
- `checkIntersection($arr1, $arr2)` - Check array intersection for permissions
- `parseCBXML($xmlString)` - Parse CBXML format
- `CBLog($data)` - Debug logging

### Coding Standards

#### KISS Principle - Keep It Simple, Stupid

**CRITICAL: Always follow the KISS principle**

This project has been refactored to follow the KISS (Keep It Simple, Stupid) principle. All code changes must adhere to this philosophy.

**Core Rules:**

- ✅ **Simplicity First**: Write the simplest solution that works
- ✅ **Minimal Changes**: Only make necessary modifications
- ✅ **No Over-Engineering**: Avoid adding features "just in case"
- ✅ **Readable Code**: Code should be self-explanatory for any PHP developer
- ✅ **Few Files**: Keep the framework in as few, clear files as possible
- ✅ **No Unnecessary Splitting**: Don't split a method into sub-methods just by rule; a longer, linear method is fine if it is easier to follow
- ✅ **Duplication Is OK**: Prefer readable duplicated code over abstractions without clear benefit
- ✅ **Zero Dependencies**: No external PHP libraries
- ✅ **Comments**: English, short; use `@deprecated`/`@see` and `SECURITY:` hints (PhpStorm)

**Examples:**

```php
// ✅ GOOD - Simple, direct solution
public function isActive(): bool
{
    return $this->get('active') === '1';
}

// ❌ BAD - Over-engineered
public function isActive(): bool
{
    $statusValidator = new StatusValidator();
    $statusChecker = new ActiveStatusChecker($statusValidator);
    $statusManager = new StatusManager($statusChecker);
    return $statusManager->validateAndCheckActiveStatus($this);
}
```

```php
// ✅ GOOD - Necessary feature
public function save(): int|string
{
    return $this->isEmpty() ? $this->create($this->arrAttributes) : $this->update($this->mixValue, $this->arrAttributes);
}

// ❌ BAD - Unnecessary complexity
public function save(array $options = []): int|string
{
    $strategy = $options['strategy'] ?? 'auto';
    $validator = $options['validator'] ?? null;
    $logger = $options['logger'] ?? null;
    $cache = $options['cache'] ?? null;

    // 50 more lines of unnecessary code...
}
```

**When Adding Features:**

1. **Question**: Is this feature absolutely necessary?
2. **Evaluate**: Can it be solved with existing code?
3. **Simplify**: What's the simplest implementation?
4. **Document**: Why is this feature needed?
5. **Review**: Can it be made simpler?

**When Refactoring:**

- ❌ Don't add abstractions "for future flexibility"
- ❌ Don't create interfaces with only one implementation
- ❌ Don't split code into multiple files unnecessarily
- ✅ Do remove unused code
- ✅ Do simplify complex logic
- ✅ Do improve readability

**Project History:**

The database layer was simplified to two standalone files (CBDatabase.php, CBObject.php) with zero external dependencies (SecurityValidator, PerformanceMonitor, NullLogger, CBObjectFactory removed). Future changes must maintain this simplicity.

#### Language Requirements

**IMPORTANT: All code must be in English**

- ✅ **Code Comments**: MUST be in English
- ✅ **DocBlocks**: MUST be in English
- ✅ **Variable Names**: MUST be in English
- ✅ **Function/Method Names**: MUST be in English
- ✅ **Class Names**: MUST be in English
- ✅ **Constants**: MUST be in English
- ✅ **Database Column Names**: MUST be in English
- ✅ **Git Commit Messages**: SHOULD be in English

**Examples**:

```php
// ✅ GOOD - English code
/**
 * Calculate the user's age based on birthdate
 *
 * @param DateTime $birthdate User's date of birth
 * @return int Age in years
 */
public function calculateAge(DateTime $birthdate): int
{
    $currentDate = new DateTime();
    $age = $currentDate->diff($birthdate)->y;
    return $age;
}

// ❌ BAD - German code
/**
 * Berechnet das Alter des Benutzers
 *
 * @param DateTime $geburtsdatum Geburtsdatum des Benutzers
 * @return int Alter in Jahren
 */
public function berechneAlter(DateTime $geburtsdatum): int
{
    $aktuellesDatum = new DateTime();
    $alter = $aktuellesDatum->diff($geburtsdatum)->y;
    return $alter;
}
```

**Exception: Communication with Claude Code**
- Communication with Claude Code (this AI assistant) CAN be in German
- User-facing strings/translations can be in any language
- Documentation for German-speaking teams can include German sections

**Rationale**:
- International collaboration
- Code readability for global developers
- Industry standard
- Better integration with English-based frameworks and libraries

### Security Considerations

- **SQL Injection**: Prepared statements for all values; identifiers via `validNames()`; `rawWhere` blocks request input embedded in the SQL text (safety net only)
- **XSS**: `sanitizeXmlValue()` strips script/iframe/object/embed/applet, `on*` attributes and `javascript:` URLs from `data_`/`media_`/`settings_` values. This is a safety net only: **always escape on output** (`htmlspecialchars`)
- **XML**: CDATA is split safely (`]]>`), invalid XML keys are skipped
- **Encryption**: `encrypted_*` / `$arrEncryptedFields` use AES-256-CBC (no MAC); without `ENCRYPTION_KEY32` values are stored as plaintext
- **Errors**: `getLastError()` contains query and params: logs only, never output to users
- **Output**: request values in JS via `json_encode($v, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)` (no own quotes), in HTML via `htmlspecialchars`; CSS classes/ids filtered to `[A-Za-z0-9_-]`. CMS content from the DB (HTML) is output without escaping on purpose
- **Files**: deliver only via a DB record (never a path from the request), realpath below `data/`
- **Uploads/renames**: target folders from the request only via realpath below `data/media/`; extensions only from the whitelist (upload AND rename, keep `uploadFile.php`/`updateItem.php` in sync); `public/data/media/.htaccess` blocks scripts
- **Shell**: `escapeshellarg` for all arguments; ffmpeg/ImageMagick/exec may be missing: fallback, never a fatal error
- **Session Security**: httponly, samesite, strict mode; new session id on login and logout
- **CSRF**: token injected into `<head>` and every `<form>`; check it on state-changing POSTs (`CBController::validateCSRF()` or `hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])`); the page cache stores a placeholder
- **Loops over data** (tree, parents): always limited, broken data must not hang the site

### Performance Optimization

- **Connection Reuse**: `CBDatabase::shared()`, one connection per request and config
- **Schema Cache**: `SHOW COLUMNS` once per table and request
- **Load from array**: `$obj->load($row)` avoids a second query when the row is already known
- **Query Profiling**: set `CBDatabase::$onQuery` (e.g. to the debugger); null = no overhead

### Installer (`capps/modules/installer`)

- Admin only; on servers additionally `$arrConf['installer'] = true` in `inc.localconf.php` (localhost always)
- **Export** (`/views/installer/export/`): writes `<vendor>/modules/<module>/install/`
  - `schema.sql` (SHOW CREATE TABLE), `schema.php` (target columns), `cms.php` (page tree + content elements)
- **Import** (`/views/installer/import/`): "Schema ausfuehren" creates missing tables/columns, modifies only checked columns, never deletes; CMS import below a chosen page
- **Compare**: with another instance via `…/views/installer/status/?token=<debug token of the other instance>`
- DB errors are shown in the installer on purpose (admin tool, exception to the rule)

### MCP API (`/controller/mcp/api`)

- Remote control via token (`Authorization: Bearer` or `X-API-Token`), token in `capps_address.mcp_token` (plaintext, visible to admins), modules enabled via `<module>/mcp.json`
- `list` options: only `order`, `direction`, `limit`, `search`
- Hidden fields (never readable, filterable, sortable): `password`, `mcp_token`, `mcp_token_created`, `verification_uid`, `encrypted*`
- Fields with `roles` in `mcp.json` are only visible/writable with that role; only fields listed in `mcp.json` are writable
- Address update: non-admins only their own record
- `ensureTokenColumn()` per call is intended (module adds its columns itself, plug-and-play)

## Debugging

**Debug Mode**: automatic on localhost (`SERVER_NAME` localhost/127.0.0.1): errors displayed. Everywhere else production: errors only in the PHP error log.

**CBDebugger**: `?cbdebug=TOKEN` (token in `CONFIGURATION['debug']['token']`, `''` = off)
- Timings, queries (repeated/slow), memory, page cache status
- `&show_config=1`: configuration output (without DB credentials and keys)

**CBLog($data, 'label')**: always outputs (also on servers), developer tool.

**Debug Helpers**:
```php
// Connection check
echo "Connected to: " . CBDatabase::shared()->getDatabase() . "\n";

// Object state (table, identifier, last error)
print_r($obj->debug());

// Real columns (data_* are XML attributes, not columns)
$obj->hasColumn('data');          // true if the table has the XML column
print_r(array_keys($obj->arrDatabaseColumns));

// Query logging/profiling via hook
CBDatabase::$onQuery = function (string $query, float $start) {
    // collect $query and microtime(true) - $start
};
```

## Testing Database Operations

### Demos

Demos in `src/capps/modules/database/demo/` (built in Oct 2025, may not reflect the latest API):

```bash
# 1. Import test database
mysql -u root -proot platformer < src/capps/modules/database/tests/demo-database.sql

# 2. Configure demo/config.php (see config.example.php), then run
cd src/capps/modules/database/demo
php 01-quickstart.php
php 02-xml-fields.php
php 03-advanced.php
```

### Database Management

```bash
# Adminer (group admin)
http://localhost:8888/Platformer/public/views/tools/adminer/
```


## File Locations Reference

### Core Files
- Configuration: `src/inc.localconf.php`
- Bootstrap: `public/index.php` → `src/capps/modules/core/classes/CBCore.php`
- Core Services: `src/capps/modules/core/classes/`
- Entry Point: `public/index.php`

### Database Layer (Simplified, Production-Ready)
- **CBDatabase**: `src/capps/modules/database/classes/CBDatabase.php` (standalone)
- **CBObject**: `src/capps/modules/database/classes/CBObject.php` (standalone)
- Demos: `src/capps/modules/database/demo/` (may be outdated)
- Test Schema: `src/capps/modules/database/tests/demo-database.sql`

### Templates & Assets
- Templates: `public/data/template/`
- Static Assets: `public/data/template/js/`, `public/data/template/css/`