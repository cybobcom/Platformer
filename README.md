# Platformer

A PHP-based modular CMS/application framework with a custom MVC-like architecture. Features a module-based structure with XML data storage, custom routing, and a simplified database abstraction layer.

## Features

- 🎯 **Modular Architecture**: Organize code in reusable modules
- 🔒 **Secure by Design**: Prepared statements, XSS protection, credential security
- ⚡ **High Performance**: Bulk operations, smart caching, optimized queries
- 🗄️ **Flexible Storage**: XML fields for structured data
- 🔄 **Transaction Support**: ACID-compliant database operations
- 📦 **Standalone Components**: Minimal dependencies, KISS principle
- 🛠️ **Developer-Friendly**: Interactive demos, comprehensive documentation

## Quick Start

### Prerequisites

- **PHP**: >= 8.0
- **MySQL/MariaDB**: >= 5.7 / 10.2
- **Apache/Nginx**: Web server

### Installation

1. **Get the code** (git or Composer):
   ```bash
   git clone https://github.com/cybobcom/Platformer.git
   cd Platformer
   ```
   or (optional, no dependencies are installed):
   ```bash
   composer create-project cybobcom/platformer Platformer dev-main
   cd Platformer
   ```
   Updates are not done via `composer update`: replace `src/capps` (see CLAUDE.md).

2. **Create database**:
   ```bash
   mysql -u root -p
   CREATE DATABASE platformer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   EXIT;
   ```

3. **Import database schema** (if available):
   ```bash
   mysql -u root -p platformer < database/schema.sql
   ```

4. **Configure the application** (optional for local MAMP):
   Without `src/inc.localconf.php` the defaults from `src/inc.localconf.example.php` are used (root/root, DB `platformer`).
   For your own setup (and always on servers):
   ```bash
   cp src/inc.localconf.example.php src/inc.localconf.php   # git-ignored
   nano src/inc.localconf.php
   ```

5. **Update configuration values** (servers: required):
   - Database credentials (host, user, password, database)
   - Encryption key `ENCRYPTION_KEY32` (32 characters, the default key is public)
   - Debug token (`''` = off)
   - Mail server settings
   - Debug email

6. **Set file permissions**:
   ```bash
   # Make data directories writable
   chmod -R 775 public/data/
   chmod -R 775 websecure/

   # Make sure web server can write to these directories
   chown -R www-data:www-data public/data/
   ```

7. **Configure web server**:

   **Apache** (.htaccess included):
   ```apache
   <VirtualHost *:80>
       ServerName platformer.local
       DocumentRoot /path/to/platformer/public

       <Directory /path/to/platformer/public>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

   **Nginx**:
   ```nginx
   server {
       listen 80;
       server_name platformer.local;
       root /path/to/platformer/public;
       index index.php index.html;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
           fastcgi_index index.php;
           include fastcgi_params;
       }
   }
   ```

8. **Access the application**:
   ```
   http://platformer.local/
   ```

## Configuration

### Main Configuration

Edit `src/inc.localconf.php`:

```php
// Database
$arrDatabaseConfiguration['DB_HOST'] = "localhost";
$arrDatabaseConfiguration['DB_USER'] = "your_user";
$arrDatabaseConfiguration['DB_PASSWORD'] = "your_password";
$arrDatabaseConfiguration['DB_DATABASE'] = "platformer";

// Admin credentials
$arrConf['plattform_login'] = "admin";
$arrConf['plattform_password'] = "secure_password";

// Encryption key (32 characters)
define("ENCRYPTION_KEY32", "your_random_32_character_key_here");
```

### Security Checklist

Before deploying to production:

- [ ] Change default admin credentials
- [ ] Generate new encryption key
- [ ] Update database credentials
- [ ] Set `display_errors` to `'off'`
- [ ] Configure mail server
- [ ] Enable HTTPS
- [ ] Review CORS settings
- [ ] Set appropriate file permissions
- [ ] Configure firewall rules
- [ ] Enable security headers

## Development

### Project Structure

```
platformer/
├── public/                  # Public web root
│   ├── index.php           # Application entry point
│   └── data/               # Public assets and templates
├── src/
│   ├── capps/              # Core modules
│   │   ├── inc.localconf.php      # Configuration (git-ignored)
│   │   └── modules/
│   │       ├── core/       # Core system
│   │       ├── database/   # Database layer
│   │       ├── address/    # User management
│   │       ├── content/    # Content management
│   │       └── structure/  # Navigation
│   └── custom/             # Custom modules
├── websecure/              # Secure storage (outside web root)
├── CLAUDE.md               # AI assistant documentation
└── README.md               # This file
```

### Database Layer

The simplified database layer consists of just 2 standalone files:

- **CBDatabase.php**: PDO wrapper with transaction support
- **CBObject.php**: ORM-like object handler

#### Basic Usage

```php
use Capps\Modules\Database\Classes\CBObject;

// Create
$user = new CBObject(null, 'users', 'user_id');
$userId = $user->create([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Read
$user = new CBObject($userId, 'users', 'user_id');
echo $user->get('name');

// Update
$user->set('name', 'Jane Doe');
$user->save();

// Delete
$user->delete();

// Query
$results = $user->findAll(
    ['active' => '1'],
    ['order' => 'created', 'limit' => 10]
);
```

### Interactive Demos

Explore the database layer with 3 demos (may not reflect the latest API):

```bash
# Import demo database
mysql -u root -p platformer < src/capps/modules/database/tests/demo-database.sql

# Run demos
cd src/capps/modules/database/demo
cp config.example.php config.php   # adjust DB settings
php 01-quickstart.php
php 02-xml-fields.php
php 03-advanced.php

# Or access via browser
http://platformer.local/src/capps/modules/database/demo/
```

### Creating a Module

1. Create module directory:
   ```bash
   mkdir -p src/capps/modules/mymodule/classes
   ```

2. Create main class:
   ```php
   <?php
   namespace Capps\Modules\Mymodule\Classes;

   class MyModule {
       public function __construct() {
           // Your code
       }
   }
   ```

3. Use via CBinitObject:
   ```php
   $obj = CBinitObject("MyModule");
   ```

## Documentation

- **CLAUDE.md**: Comprehensive documentation for AI assistants and developers
- **Demo Suite**: Interactive examples in `src/capps/modules/database/demo/`
- **Inline Documentation**: PHPDoc comments throughout codebase

## API Reference

### CBDatabase

```php
$db = new CBDatabase();

// Basic operations
$results = $db->select($sql, $params);
$id = $db->insert($table, $data);
$db->update($table, $data);
$db->delete($table, $pkColumn, $pkValue);

// Transactions
$db->beginTransaction();
$db->commit();
$db->rollback();

// Utilities
$uuid = $db->generateUuid();
```

### CBObject

```php
$obj = new CBObject($id, $table, $primaryKey);

// CRUD
$id = $obj->create($data);
$obj->update($id, $data);
$obj->delete($id);
$id = $obj->save(); // Smart create/update

// Queries
$results = $obj->findAll($conditions, $options);
$first = $obj->first($conditions);
$count = $obj->count($conditions);
$exists = $obj->exists($conditions);

// Bulk operations
$ids = $obj->insertBatch($dataArray);

// Soft deletes
$obj->softDelete($id);
$obj->restoreSoftDeleted($id);

// Helpers
$obj->hasColumn($column);
$columns = $obj->getColumns();
```

## Testing

### Manual Testing

```bash
# Test database connection
php -r "require 'src/inc.localconf.php';
        require 'src/capps/modules/database/classes/CBDatabase.php';
        use Capps\Modules\Database\Classes\CBDatabase;
        \$db = new CBDatabase();
        echo 'Connection OK';"
```

## Troubleshooting

### Common Issues

**Database connection failed**:
- Check credentials in `inc.localconf.php`
- Verify MySQL is running
- Check firewall rules

**Permission denied**:
```bash
chmod -R 775 public/data/
chown -R www-data:www-data public/data/
```

**Module not found**:
- Check module naming convention (lowercase directory, PascalCase class)
- Verify autoloader registration
- Clear opcode cache if using PHP-FPM

**White screen / 500 error**:
- Check PHP error log
- Enable display_errors in development
- Verify file permissions

## Performance

### Optimization Tips

1. **Use bulk operations** for large datasets:
   ```php
   $obj->insertBatch($largeArray); // Much faster than loop
   ```

2. **Enable opcode cache** (OPcache):
   ```ini
   opcache.enable=1
   opcache.memory_consumption=128
   ```

3. **Database indexing**:
   ```sql
   CREATE INDEX idx_active ON users(active);
   ```

4. **Use transactions** for related operations:
   ```php
   $db->beginTransaction();
   // Multiple operations
   $db->commit();
   ```

## Security

### Best Practices

- Keep `inc.localconf.php` out of version control
- Use prepared statements (automatic in CBDatabase)
- Enable XSS protection for XML fields (automatic in CBObject)
- Regular security updates
- Strong passwords and encryption keys
- HTTPS in production
- Secure file permissions
- Input validation
- Output escaping

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Changes: see [CHANGELOG.md](CHANGELOG.md) (current version in `src/capps/VERSION`).

## License

MIT, see [LICENSE](LICENSE). Bundled third-party libraries (Bootstrap, jQuery, Vue.js, Adminer, ...): see [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

## Support

- **Documentation**: CLAUDE.md, SECURITY.md
- **Issues**: [GitHub Issues](https://github.com/cybobcom/Platformer/issues)
- **Email**: info@cybob.com

---

**Made with ❤️ by cybob**