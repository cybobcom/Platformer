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
- User: root / Password: root (as per inc.localconf.php)
- Port: Default (3306)

**Running the Application**:
```bash
# Access via MAMP at:
# http://localhost/Platformer/
# or configured virtual host
```

## Core Architecture

### Bootstrap Sequence

1. **Entry Point**: `public/index.php`
   - Sets up security headers
   - Configures session management
   - Establishes base paths
   - Loads core functions and configuration

2. **Configuration**: `src/capps/inc.localconf.php`
   - Defines all system constants (BASEURL, BASEDIR, etc.)
   - Database credentials
   - Module vendor configuration
   - Platform identifiers

3. **Core Bootstrap**: `src/capps/modules/core/core.php`
   - Initializes CBAutoloader
   - Registers module namespaces (both `capps\` and `Capps\`)
   - Sets up core services via CoreBootstrap
   - Initializes user and structure objects

4. **Application Execution**: `src/capps/modules/core/classes/CoreBootstrap.php`
   - Service container initialization
   - Request routing and security validation
   - Route execution (script, agent, tool, page types)
   - Template rendering

### Module Structure

Modules are located in either `src/capps/modules/` (core) or `src/custom/modules/` (customizations):

```
modules/
├── core/           # Core system functionality
│   ├── classes/    # CoreBootstrap, CBAutoloader, Services
│   ├── core.php    # Core initialization
│   └── functions.php # Global functions
├── database/       # Database layer
│   ├── classes/    # CBDatabase.php, CBObject.php (standalone)
│   ├── demo/       # Complete demo suite (5 interactive examples)
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

**CBDatabase** (`src/capps/modules/database/classes/CBDatabase.php`):
- **Simplified, production-ready PDO wrapper** following KISS principle
- Standalone class with zero external dependencies
- Prepared statements for all queries (SQL injection protection)
- Transaction support with auto-rollback on errors
- Connection retry logic (3 attempts)
- UUID generation support (`generateUuid()`)
- Query logging for debugging
- **Security**: Credentials never stored in memory after connection

**CBObject** (`src/capps/modules/database/classes/CBObject.php`):
- **Simplified ORM-like database handler** following KISS principle
- Standalone class - only depends on CBDatabase
- XML field support with XSS protection (data_, media_, settings_ prefixes)
- Auto-timestamps (date_created, date_updated)
- Supports empty objects (for creation) and loaded objects
- Modern API: `get()`, `set()`, `create()`, `update()`, `delete()`, `save()`
- Query methods: `findAll()`, `first()`, `count()`, `exists()`
- Bulk operations: `insertBatch()` for high performance
- Soft delete support: `softDelete()`, `restoreSoftDeleted()`
- Column helpers: `hasColumn()`, `getColumns()`
- Legacy API: `getAttribute()`, `setAttribute()`, `getAllEntries()` (deprecated but functional)

**CBinitObject** (`src/capps/modules/core/functions.php`):
- Global function for dynamic object instantiation
- Auto-detection: Class name becomes module name (e.g., `CBinitObject("User")` → `user/User`)
- Vendor support: `CBinitObject("custom:User")` for custom modules
- Fallback mechanism: Tries custom vendor first, then capps
- Caching for performance

### Database Conventions

**Table Structure**:
- Primary keys: Either `{table}_id` (auto-increment) or `{table}_uid` (UUID)
- XML fields: `data`, `media`, `settings` (CDATA format)
- Timestamps: `date_created`, `date_updated`
- Groups: `addressgroups` (comma-separated)

**XML Field Access**:
```php
// Database: data column contains XML
// Object attribute: data_description, data_title, etc.
$object->get('data_description'); // Returns parsed CDATA content
$object->set('data_title', 'New Title'); // Will be saved as XML
```

### Routing System

Routes are stored in `capps_route` table and cached in `$coreArrRoute`:
- Format: `structure_id:content_id:address_uid` → `route_string`
- Special routes: Manual links excluded from auto-routing
- Structure-based: Uses `$coreArrSortedStructure` for navigation hierarchy

### User System

User management via Address module:
- User object: `$objPlattformUser` (global)
- Session key: `$_SESSION[PLATTFORM_IDENTIFIER]["login_user_identifier"]`
- Email validation with fallback logic
- Address groups for permissions

## Common Development Tasks

### Creating a New Module

1. Create module directory: `src/capps/modules/mymodule/` or `src/custom/modules/mymodule/`
2. Add classes directory: `mymodule/classes/`
3. Create main class: `MyModule.php` with namespace `Capps\Modules\Mymodule\Classes\`
4. Autoloader will automatically register the module
5. Use via: `$obj = CBinitObject("MyModule");`

### Working with Database Objects

```php
// Modern API (recommended)
use Capps\Modules\Database\Classes\CBObject;

// Create new record (auto-timestamps)
$user = new CBObject(null, 'capps_address', 'address_uid');
$userId = $user->create([
    'login' => 'john@example.com',
    'data_name' => 'John Doe'
    // date_created automatically added
]);

// Load and update existing
$user = new CBObject($userId, 'capps_address', 'address_uid');
$user->set('data_name', 'Jane Doe');
$user->save(); // date_updated automatically added

// Smart save (auto-detects create vs update)
$user = new CBObject(null, 'capps_address', 'address_uid');
$user->set('login', 'new@example.com');
$user->set('data_name', 'New User');
$newId = $user->save(); // Creates if no ID, updates if ID exists

// Query methods
$user = new CBObject(null, 'capps_address', 'address_uid');

// Find all with conditions
$results = $user->findAll(
    ['data_status' => 'active'],
    ['order' => 'date_created', 'direction' => 'DESC', 'limit' => 10]
);

// Find first matching record
$firstUser = $user->first(['role' => 'admin']);

// Count records
$totalUsers = $user->count();
$activeUsers = $user->count(['active' => '1']);

// Check existence
if ($user->exists(['email' => 'test@example.com'])) {
    echo "Email already exists";
}

// Bulk insert (high performance)
$ids = $user->insertBatch([
    ['login' => 'user1@test.com', 'data_name' => 'User 1'],
    ['login' => 'user2@test.com', 'data_name' => 'User 2'],
    ['login' => 'user3@test.com', 'data_name' => 'User 3']
]);

// Soft delete (sets deleted_at timestamp)
$user->softDelete($userId);
$user->restoreSoftDeleted($userId);

// Column helpers
if ($user->hasColumn('data_bio')) {
    echo "Bio column exists";
}
$columns = $user->getColumns(); // Returns all column names
```

### Database Queries

```php
use Capps\Modules\Database\Classes\CBDatabase;

$db = new CBDatabase(); // Uses DATABASE constant from config

// Select with prepared statements (SQL injection safe)
$users = $db->select(
    "SELECT * FROM capps_address WHERE login LIKE ? AND active = ?",
    ['%@example.com', '1']
);

// Insert with auto-generated UUID
$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com'
];
// If table has {table}_uid column, UUID is auto-generated
$userId = $db->insert('capps_address', $data);

// Update
$db->update('capps_address', [
    'address_uid' => $userId,
    'name' => 'Jane Doe'
]);

// Delete
$db->delete('capps_address', 'address_uid', $userId);

// Transactions with auto-rollback
try {
    $db->beginTransaction();

    $userId = $db->insert('capps_address', ['name' => 'Test User']);
    $db->insert('capps_profile', ['address_uid' => $userId, 'bio' => 'Test bio']);

    $db->commit();
} catch (Exception $e) {
    $db->rollback(); // Auto-rollback on error
    throw $e;
}

// Connection retry (automatically retries 3 times)
$db = new CBDatabase(); // Retries connection if first attempt fails

// UUID generation
$uuid = $db->generateUuid(); // For custom use cases
```

### Adding a Controller

Controllers are in `modules/{module}/controller/`:
```php
// src/capps/modules/mymodule/controller/doSomething.php
<?php
use Capps\Modules\Database\Classes\CBObject;

$obj = CBinitObject("MyModule");
// Process request
header('Content-Type: application/json');
echo json_encode(['success' => true]);
```

### Creating Views

Views are in `modules/{module}/views/`:
- Use master templates from `public/data/template/views/`
- Template types: `mastertemplate.html`, `mastertemplate_admin.html`, `mastertemplate_console.html`
- Access globals: `$BASEURL`, `$BASEDIR`, `$objPlattformUser`

## Important Patterns

### Namespace Conventions

Both lowercase and uppercase namespaces are supported:
```php
// Lowercase (legacy)
use capps\modules\core\classes\CoreBootstrap;

// Uppercase (modern, preferred)
use Capps\Modules\Core\Classes\CoreBootstrap;
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
- ✅ **Single Responsibility**: Each class/method does one thing well
- ✅ **Readable Code**: Code should be self-explanatory
- ✅ **Minimal Dependencies**: Avoid external libraries when possible
- ✅ **Small Classes**: Keep classes under 500 lines when possible
- ✅ **Short Methods**: Keep methods under 50 lines when possible

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

This codebase was simplified from 2000+ lines with multiple dependencies to just ~900 lines with zero external dependencies. Future changes must maintain this simplicity.

**Before (Complex):**
- SecurityValidator.php
- PerformanceMonitor.php
- NullLogger.php
- CBObjectFactory.php
- Multiple helper classes

**After (Simple):**
- CBDatabase.php (~400 lines)
- CBObject.php (~500 lines)
- Zero external dependencies

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

- **SQL Injection Protection**: All queries use prepared statements
- **XSS Protection**: XML fields automatically escaped with htmlspecialchars()
- **Credential Security**: Database passwords never stored in memory after connection
- **Session Security**: Configured with httponly, samesite, and secure flags
- **Input Sanitization**: Automatic escaping for XML CDATA fields
- **Connection Retry**: Failed connections retry 3 times before throwing exception

### Performance Optimization

- **Bulk Operations**: `insertBatch()` for high-performance inserts (1000+ records)
- **Smart Save**: Auto-detects create vs update to minimize queries
- **Auto-Timestamps**: date_created/date_updated managed automatically
- **Connection Reuse**: Single connection instance shared across operations
- **Query Logging**: Optional logging for performance analysis (debug mode)

## Debugging

**Debug Mode**:
```php
// Enable in public/index.php
define('DEBUG_MODE', true);
define('ENABLE_PROFILING', true);
```

**Debug Helpers**:
```php
// Database connection check
$db = new CBDatabase();
echo "Connected to: " . $db->getDatabase() . "\n";

// Check if column exists
$obj = new CBObject(null, 'capps_address', 'address_uid');
if ($obj->hasColumn('data_bio')) {
    echo "Bio column exists\n";
}

// Get all columns
$columns = $obj->getColumns();
print_r($columns);

// Count records for debugging
$total = $obj->count();
echo "Total records: {$total}\n";

// Query logging (enable in config)
define('DB_LOG_QUERIES', true);
$db->select("SELECT * FROM capps_address LIMIT 1");
// Queries will be logged to error_log
```

**Debug Endpoints** (when DEBUG_MODE enabled):
- `?debug=phpinfo` - PHP configuration
- `?debug=session` - Session data
- `?debug=constants` - Defined constants

## Testing Database Operations

### Interactive Demos

A complete demo suite is available at `src/capps/modules/database/demo/`:

```bash
# Browser interface (recommended)
http://localhost/Platformer/src/capps/modules/database/demo/

# CLI demos
cd src/capps/modules/database/demo
php 01-basic-usage.php      # Basic operations (5 min)
php 02-xml-fields.php        # XML fields (10 min)
php 03-crud-operations.php   # Full CRUD (15 min)
php 04-legacy-compat.php     # Legacy API (10 min)
php 05-custom-class.php      # Custom classes (15 min)
```

**Demo Setup**:
```bash
# 1. Import test database
mysql -u root -proot platformer < src/capps/modules/database/tests/demo-database.sql

# 2. Run demos
cd src/capps/modules/database/demo
php 01-basic-usage.php
```

The demo suite includes:
- 5 complete interactive examples
- Test database with sample data
- CLI and browser interfaces
- Code examples for all features
- Performance comparisons
- Migration guides

### Database Management

```bash
# Access adminer for database management
http://localhost/Platformer/public/data/adminer-4.8.1-de.php
```

## File Locations Reference

### Core Files
- Configuration: `src/capps/inc.localconf.php`
- Bootstrap: `src/capps/modules/core/core.php`
- Core Services: `src/capps/modules/core/classes/`
- Entry Point: `public/index.php`

### Database Layer (Simplified, Production-Ready)
- **CBDatabase**: `src/capps/modules/database/classes/CBDatabase.php` (standalone)
- **CBObject**: `src/capps/modules/database/classes/CBObject.php` (standalone)
- Demo Suite: `src/capps/modules/database/demo/` (5 interactive examples)
- Test Schema: `src/capps/modules/database/tests/demo-database.sql`

### Templates & Assets
- Templates: `public/data/template/`
- Static Assets: `public/data/template/js/`, `public/data/template/css/`

## Key Differences: Simplified Architecture

### Removed Dependencies
The database layer has been **completely simplified** and now follows the KISS principle:

**Removed files** (no longer needed):
- ❌ `SecurityValidator.php` - Validation now built into CBDatabase/CBObject
- ❌ `PerformanceMonitor.php` - Removed complexity, simple logging instead
- ❌ `NullLogger.php` - PHP's error_log() used instead
- ❌ `CBObjectFactory.php` - Object creation simplified

**Result**: Just 2 standalone files:
- ✅ `CBDatabase.php` - ~400 lines, zero dependencies
- ✅ `CBObject.php` - ~500 lines, only depends on CBDatabase

### New Features in CBObject

**Auto-Timestamps**:
```php
$user = new CBObject(null, 'users', 'user_id');
$user->create(['name' => 'John']); // date_created auto-added
$user->update($id, ['name' => 'Jane']); // date_updated auto-added
```

**Query Helpers**:
```php
$user->count();                           // Total count
$user->count(['active' => '1']);          // Filtered count
$user->exists(['email' => 'test@test']);  // Check existence
$firstAdmin = $user->first(['role' => 'admin']); // First match
```

**Bulk Operations**:
```php
// High performance batch insert
$ids = $user->insertBatch([
    ['name' => 'User 1', 'email' => 'user1@test.com'],
    ['name' => 'User 2', 'email' => 'user2@test.com'],
    // ... 1000+ records
]);
```

**Soft Deletes**:
```php
$user->softDelete($id);           // Sets deleted_at timestamp
$user->restoreSoftDeleted($id);   // Clears deleted_at
```

**Smart Save**:
```php
$user = new CBObject(null, 'users', 'user_id');
$user->set('name', 'John');
$id = $user->save(); // Auto-detects create vs update
```

**Column Helpers**:
```php
if ($user->hasColumn('data_bio')) {
    // Column exists
}
$columns = $user->getColumns(); // Get all column names
```

### New Features in CBDatabase

**Transaction Support**:
```php
$db->beginTransaction();
try {
    $db->insert('users', ['name' => 'John']);
    $db->insert('profiles', ['user_id' => $id]);
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
}
```

**Connection Retry**:
```php
// Automatically retries failed connections 3 times
$db = new CBDatabase(); // Resilient connection
```

**UUID Generation**:
```php
$uuid = $db->generateUuid(); // For custom use cases
// Auto-generated for {table}_uid primary keys
```