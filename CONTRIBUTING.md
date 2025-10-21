# Contributing to Platformer

Thank you for considering contributing to Platformer! This document provides guidelines and instructions for contributing.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [How to Contribute](#how-to-contribute)
- [Coding Standards](#coding-standards)
- [Pull Request Process](#pull-request-process)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Features](#suggesting-features)

## Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inspiring community for all.

### Expected Behavior

- Be respectful and inclusive
- Welcome newcomers
- Be collaborative
- Be patient and considerate

### Unacceptable Behavior

- Harassment or discrimination
- Trolling or insulting comments
- Publishing private information
- Other unprofessional conduct

## Getting Started

### Prerequisites

- PHP >= 8.0
- MySQL/MariaDB >= 5.7/10.2
- Git
- Basic understanding of PHP and OOP
- Familiarity with MVC architecture

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork:
   ```bash
   git clone https://github.com/YOUR-USERNAME/platformer.git
   cd platformer
   ```
3. Add upstream remote:
   ```bash
   git remote add upstream https://github.com/ORIGINAL-OWNER/platformer.git
   ```

## Development Setup

### 1. Install Dependencies

```bash
# Install Composer dependencies (if any)
composer install

# Copy configuration
cp src/capps/inc.localconf.example.php src/capps/inc.localconf.php
```

### 2. Configure Database

```bash
# Create database
mysql -u root -p
CREATE DATABASE platformer_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;

# Import demo database (optional)
mysql -u root -p platformer_dev < src/capps/modules/database/tests/demo-database.sql
```

### 3. Update Configuration

Edit `src/capps/inc.localconf.php`:
```php
$arrDatabaseConfiguration['DB_DATABASE'] = "platformer_dev";
$arrDatabaseConfiguration['DB_USER'] = "your_user";
$arrDatabaseConfiguration['DB_PASSWORD'] = "your_password";
```

### 4. Run Tests

```bash
# Run demo suite to verify setup
cd src/capps/modules/database/demo
php 01-basic-usage.php
```

## How to Contribute

### Types of Contributions

We welcome:
- Bug fixes
- New features
- Documentation improvements
- Performance optimizations
- Security enhancements
- Test coverage improvements

### Before You Start

1. **Check existing issues**: See if someone is already working on it
2. **Open an issue**: Discuss your idea before writing code
3. **Get feedback**: Ensure your approach aligns with project goals

### Making Changes

1. **Create a branch**:
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/bug-description
   ```

2. **Make your changes**:
   - Write clear, documented code
   - Follow coding standards (see below)
   - Add tests if applicable
   - Update documentation

3. **Commit your changes**:
   ```bash
   git add .
   git commit -m "feat: add amazing feature"
   ```

4. **Keep your fork updated**:
   ```bash
   git fetch upstream
   git rebase upstream/main
   ```

5. **Push to your fork**:
   ```bash
   git push origin feature/your-feature-name
   ```

## Coding Standards

### PHP Style Guide

We follow **PSR-12** coding standard.

#### Basic Rules

```php
<?php

declare(strict_types=1);

namespace Capps\Modules\Example\Classes;

/**
 * Example class description
 */
class ExampleClass
{
    /**
     * Property description
     */
    private string $property;

    /**
     * Method description
     *
     * @param string $param Parameter description
     * @return bool Return description
     */
    public function exampleMethod(string $param): bool
    {
        // Method implementation
        return true;
    }
}
```

#### Naming Conventions

- **Classes**: PascalCase (`MyClass`)
- **Methods**: camelCase (`myMethod`)
- **Variables**: camelCase (`$myVariable`)
- **Constants**: UPPER_SNAKE_CASE (`MY_CONSTANT`)
- **Database tables**: snake_case (`user_profiles`)
- **Database columns**: snake_case (`user_id`)

#### Documentation

All classes and methods must have PHPDoc comments:

```php
/**
 * Calculate user age
 *
 * Calculates the age of a user based on their birthdate.
 * Returns null if birthdate is not set.
 *
 * @param DateTime $birthdate User's birthdate
 * @return int|null Age in years or null
 * @throws InvalidArgumentException If birthdate is in the future
 */
public function calculateAge(DateTime $birthdate): ?int
{
    if ($birthdate > new DateTime()) {
        throw new InvalidArgumentException("Birthdate cannot be in the future");
    }

    return $birthdate->diff(new DateTime())->y;
}
```

#### Type Hints

Always use type hints (PHP 8.0+):

```php
// ✅ GOOD
public function process(string $input, int $count = 1): array
{
    return [];
}

// ❌ BAD
public function process($input, $count = 1)
{
    return [];
}
```

#### Error Handling

Use exceptions for error handling:

```php
// ✅ GOOD
if (!$user->exists(['email' => $email])) {
    throw new UserNotFoundException("User not found: {$email}");
}

// ❌ BAD
if (!$user->exists(['email' => $email])) {
    return false;
}
```

### Database Code

#### Use CBObject/CBDatabase

```php
// ✅ GOOD: Use ORM
$user = new CBObject(null, 'users', 'user_id');
$results = $user->findAll(['active' => '1']);

// ❌ BAD: Raw SQL
$results = mysqli_query($conn, "SELECT * FROM users WHERE active = 1");
```

#### Prepared Statements

```php
// ✅ GOOD: Prepared statements (automatic)
$db->select("SELECT * FROM users WHERE email = ?", [$email]);

// ❌ BAD: String concatenation
$db->query("SELECT * FROM users WHERE email = '$email'");
```

### Security

#### Input Validation

```php
// ✅ GOOD
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    throw new InvalidArgumentException("Invalid email");
}

// ❌ BAD
$email = $_POST['email'];
```

#### Output Encoding

```php
// ✅ GOOD
echo htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

// ❌ BAD
echo $data;
```

### Testing

#### Write Tests

```php
// Example test structure
class UserTest extends TestCase
{
    public function testUserCreation(): void
    {
        $user = new CBObject(null, 'users', 'user_id');
        $id = $user->create(['name' => 'Test User']);

        $this->assertNotNull($id);
        $this->assertIsInt($id);
    }
}
```

## Pull Request Process

### Before Submitting

1. **Test your changes**:
   ```bash
   # Run all demos
   cd src/capps/modules/database/demo
   for file in *.php; do php "$file"; done

   # Test manually in browser
   # Check error logs
   ```

2. **Update documentation**:
   - Update README.md if needed
   - Update CLAUDE.md if architecture changed
   - Add comments to complex code

3. **Commit message format**:
   ```
   type(scope): subject

   body

   footer
   ```

   Types:
   - `feat`: New feature
   - `fix`: Bug fix
   - `docs`: Documentation
   - `style`: Formatting
   - `refactor`: Code restructuring
   - `test`: Tests
   - `chore`: Maintenance

   Example:
   ```
   feat(database): add soft delete support

   - Add softDelete() method to CBObject
   - Add restoreSoftDeleted() method
   - Update documentation
   - Add tests

   Closes #123
   ```

### Creating Pull Request

1. **Push to your fork**:
   ```bash
   git push origin feature/your-feature-name
   ```

2. **Open PR on GitHub**:
   - Clear title describing the change
   - Detailed description of what and why
   - Link to related issues
   - Screenshots if UI changes

3. **PR Template**:
   ```markdown
   ## Description
   Brief description of changes

   ## Type of Change
   - [ ] Bug fix
   - [ ] New feature
   - [ ] Breaking change
   - [ ] Documentation update

   ## Testing
   How to test the changes

   ## Checklist
   - [ ] Code follows project style
   - [ ] Self-review completed
   - [ ] Comments added for complex code
   - [ ] Documentation updated
   - [ ] Tests added/updated
   - [ ] No new warnings
   ```

### Review Process

1. **Automated checks**: CI/CD pipeline runs
2. **Code review**: Maintainers review code
3. **Feedback**: Address review comments
4. **Approval**: At least one maintainer approval required
5. **Merge**: Maintainers merge approved PRs

### After Merge

1. **Delete branch**:
   ```bash
   git branch -d feature/your-feature-name
   git push origin --delete feature/your-feature-name
   ```

2. **Update fork**:
   ```bash
   git checkout main
   git pull upstream main
   git push origin main
   ```

## Reporting Bugs

### Before Reporting

1. Check if bug already reported
2. Test with latest version
3. Verify it's not a configuration issue

### Bug Report Template

```markdown
**Describe the bug**
Clear description of the bug

**To Reproduce**
Steps to reproduce:
1. Go to '...'
2. Click on '...'
3. See error

**Expected behavior**
What you expected to happen

**Screenshots**
If applicable

**Environment**
- PHP version:
- MySQL version:
- OS:
- Browser (if relevant):

**Additional context**
Any other relevant information
```

## Suggesting Features

### Feature Request Template

```markdown
**Is your feature request related to a problem?**
Clear description of the problem

**Describe the solution you'd like**
What you want to happen

**Describe alternatives you've considered**
Other solutions you've thought about

**Additional context**
Mockups, examples, etc.
```

## Development Guidelines

### Module Development

When creating a new module:

1. **Structure**:
   ```
   modules/mymodule/
   ├── classes/
   │   └── MyModule.php
   ├── controller/
   │   └── doSomething.php
   ├── views/
   │   └── list.html
   └── README.md
   ```

2. **Naming**:
   - Directory: lowercase (`mymodule`)
   - Class: PascalCase (`MyModule`)
   - Namespace: `Capps\Modules\Mymodule\Classes`

3. **Documentation**:
   - README.md in module directory
   - PHPDoc for all public methods
   - Usage examples

### Database Changes

When modifying database:

1. **Migration script**:
   ```sql
   -- migrations/001_add_feature.sql
   ALTER TABLE users ADD COLUMN feature_enabled TINYINT(1) DEFAULT 0;
   ```

2. **Rollback script**:
   ```sql
   -- migrations/001_add_feature_rollback.sql
   ALTER TABLE users DROP COLUMN feature_enabled;
   ```

3. **Update schema documentation**

## Questions?

- **General questions**: Open a discussion on GitHub
- **Specific issues**: Open an issue
- **Security**: See SECURITY.md

## Recognition

Contributors are recognized in:
- GitHub contributors page
- CHANGELOG.md for significant contributions
- README.md for major features

Thank you for contributing! 🎉

---

**Happy Coding!**