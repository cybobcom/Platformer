# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 3.1.x   | :white_check_mark: |
| 3.0.x   | :white_check_mark: |
| < 3.0   | :x:                |

## Reporting a Vulnerability

We take the security of Platformer seriously. If you discover a security vulnerability, please follow these steps:

### 1. Do Not Disclose Publicly

Please **do not** report security vulnerabilities through public GitHub issues.

### 2. Report via Email

Send an email to: **[your-security-email@example.com]**

Include:
- Description of the vulnerability
- Steps to reproduce
- Affected versions
- Potential impact
- Suggested fix (if available)

### 3. Response Timeline

- **Initial Response**: Within 48 hours
- **Assessment**: Within 1 week
- **Fix & Disclosure**: Coordinated with reporter

### 4. Disclosure Policy

We follow **responsible disclosure**:
- Work with reporter to understand and fix the issue
- Credit security researchers (with permission)
- Publish security advisory after fix is released

## Security Best Practices

### Before Deployment

#### 1. Configuration Security

**Critical - Do NOT skip these steps:**

```bash
# 1. Copy configuration template
cp src/capps/inc.localconf.example.php src/capps/inc.localconf.php

# 2. Generate secure credentials
# Use a password manager or generator for:
# - Admin password
# - Database password
# - Encryption key (32 random characters)
```

Edit `src/capps/inc.localconf.php`:

```php
// ❌ NEVER USE DEFAULT CREDENTIALS IN PRODUCTION
$arrConf['plattform_login'] = "admin123secure"; // Change this!
$arrConf['plattform_password'] = "Str0ng_P@ssw0rd!2024"; // Strong password!

// ❌ NEVER USE EXAMPLE ENCRYPTION KEY
define("ENCRYPTION_KEY32", "abcdef0123456789abcdef0123456789"); // Random 32 chars!

// Database
$arrDatabaseConfiguration['DB_PASSWORD'] = "secure_db_password"; // Strong password!
```

Generate secure keys:
```bash
# Random 32-character encryption key
openssl rand -hex 16

# Or using PHP
php -r "echo bin2hex(random_bytes(16));"
```

#### 2. File Permissions

```bash
# Web server readable, not writable
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Writable directories
chmod -R 775 public/data/
chmod -R 775 websecure/

# Configuration should NOT be web-readable
chmod 600 src/capps/inc.localconf.php

# Set correct ownership
chown -R www-data:www-data .
```

#### 3. Database Security

```sql
-- Create dedicated database user with limited privileges
CREATE USER 'platformer_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON platformer.* TO 'platformer_user'@'localhost';
FLUSH PRIVILEGES;

-- Never use root in production!
```

#### 4. Web Server Configuration

**Apache** (.htaccess):
```apache
# Prevent access to sensitive files
<FilesMatch "^(inc\.localconf\.php|\.git.*|composer\.json)$">
    Require all denied
</FilesMatch>

# Disable directory listing
Options -Indexes

# Security headers
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

**Nginx**:
```nginx
# Prevent access to sensitive files
location ~ /(inc\.localconf\.php|\.git|composer\.json) {
    deny all;
    return 404;
}

# Security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

#### 5. PHP Configuration

Edit `php.ini` for production:

```ini
# Disable error display
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

# Enable error logging
log_errors = On
error_log = /var/log/php/error.log

# Security settings
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
enable_dl = Off

# Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_only_cookies = 1
session.cookie_samesite = "Strict"

# Upload limits
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 30
```

#### 6. HTTPS/SSL

**Always use HTTPS in production!**

```bash
# Get free SSL certificate from Let's Encrypt
certbot --apache -d your-domain.com
# or
certbot --nginx -d your-domain.com
```

Force HTTPS in Apache:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

#### 7. Firewall Configuration

```bash
# UFW (Ubuntu)
ufw allow 22/tcp   # SSH
ufw allow 80/tcp   # HTTP
ufw allow 443/tcp  # HTTPS
ufw enable

# Fail2ban (brute-force protection)
apt install fail2ban
systemctl enable fail2ban
```

### Regular Maintenance

#### Daily/Weekly

- [ ] Review error logs
- [ ] Monitor unusual activity
- [ ] Check disk space
- [ ] Verify backups

#### Monthly

- [ ] Update PHP version
- [ ] Update database server
- [ ] Review access logs
- [ ] Test backup restoration
- [ ] Security scan

#### Quarterly

- [ ] Change admin passwords
- [ ] Review user permissions
- [ ] Security audit
- [ ] Penetration testing (recommended)

### Security Features

#### Built-in Protections

1. **SQL Injection Protection**
   - All queries use prepared statements
   - Automatic parameter binding
   - Type-safe queries

2. **XSS Protection**
   - Automatic HTML escaping for XML fields
   - Output encoding
   - Content Security Policy (CSP) headers

3. **CSRF Protection**
   - Session-based tokens (implement in your controllers)
   - SameSite cookie attribute

4. **Credential Security**
   - Database passwords never stored in memory after connection
   - Encryption keys for sensitive data
   - Secure password hashing (use password_hash())

5. **Connection Security**
   - Retry logic prevents DoS vulnerabilities
   - Connection pooling limits
   - Automatic cleanup

### Secure Coding Guidelines

#### Input Validation

```php
// ✅ GOOD: Validate and sanitize
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    throw new InvalidArgumentException("Invalid email");
}

// ❌ BAD: Direct use
$email = $_POST['email'];
```

#### Database Queries

```php
// ✅ GOOD: Prepared statements (automatic in CBDatabase)
$db->select("SELECT * FROM users WHERE email = ?", [$email]);

// ❌ BAD: String concatenation
$db->query("SELECT * FROM users WHERE email = '$email'");
```

#### Output Encoding

```php
// ✅ GOOD: Escape output
echo htmlspecialchars($user->get('name'), ENT_QUOTES, 'UTF-8');

// ❌ BAD: Raw output
echo $user->get('name');
```

#### File Uploads

```php
// ✅ GOOD: Validate and sanitize
$allowedTypes = ['image/jpeg', 'image/png'];
$maxSize = 5 * 1024 * 1024; // 5MB

if (!in_array($_FILES['file']['type'], $allowedTypes)) {
    throw new Exception("Invalid file type");
}

if ($_FILES['file']['size'] > $maxSize) {
    throw new Exception("File too large");
}

// Generate random filename
$filename = bin2hex(random_bytes(16)) . '.jpg';
```

#### Session Management

```php
// ✅ GOOD: Regenerate session ID after login
session_start();
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;

// Set secure session parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
```

### Common Vulnerabilities

| Vulnerability | Status | Protection |
|--------------|--------|------------|
| SQL Injection | ✅ Protected | Prepared statements |
| XSS | ✅ Protected | Automatic escaping |
| CSRF | ⚠️ Implement | Token-based protection |
| Session Hijacking | ✅ Protected | Secure session config |
| Directory Traversal | ✅ Protected | Path validation |
| File Upload | ⚠️ Implement | Type/size validation |
| Brute Force | ⚠️ Implement | Rate limiting |
| Information Disclosure | ✅ Protected | Error handling |

### Security Checklist

Before going live:

#### Configuration
- [ ] Changed admin credentials from defaults
- [ ] Generated new 32-character encryption key
- [ ] Updated database credentials (not using root)
- [ ] Set `display_errors = Off`
- [ ] Configured mail server
- [ ] Updated debug email

#### Files & Permissions
- [ ] Created `inc.localconf.php` from template
- [ ] Verified `.gitignore` excludes sensitive files
- [ ] Set correct file permissions (644/755)
- [ ] Made configuration read-only (600)
- [ ] Set proper ownership (www-data)

#### Server
- [ ] Installed SSL certificate
- [ ] Forced HTTPS redirect
- [ ] Enabled security headers
- [ ] Disabled directory listing
- [ ] Blocked access to sensitive files
- [ ] Configured firewall

#### Database
- [ ] Created dedicated database user
- [ ] Granted minimum required privileges
- [ ] Changed default database password
- [ ] Enabled binary logging for backups
- [ ] Set up automated backups

#### PHP
- [ ] Updated to latest stable version
- [ ] Disabled error display
- [ ] Enabled error logging
- [ ] Configured session security
- [ ] Set upload limits

#### Testing
- [ ] Tested backup restoration
- [ ] Verified HTTPS works
- [ ] Checked error logs
- [ ] Tested all critical paths
- [ ] Security scan completed

### Incident Response

If you discover a security breach:

1. **Immediate Actions**:
   - Take affected systems offline
   - Change all passwords
   - Revoke compromised credentials
   - Preserve logs for analysis

2. **Investigation**:
   - Identify entry point
   - Assess damage
   - Check for backdoors
   - Review access logs

3. **Remediation**:
   - Patch vulnerabilities
   - Restore from clean backup
   - Update security measures
   - Monitor for reinfection

4. **Communication**:
   - Notify affected users
   - Report to authorities (if required)
   - Document incident
   - Update security policy

### Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Guide](https://www.php.net/manual/en/security.php)
- [MySQL Security Best Practices](https://dev.mysql.com/doc/refman/8.0/en/security-guidelines.html)
- [Mozilla Web Security Guidelines](https://infosec.mozilla.org/guidelines/web_security)

### Questions?

For security-related questions, contact: **[your-security-email@example.com]**

---

**Last Updated**: 2025-01-21
**Version**: 3.1