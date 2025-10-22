<?php
/**
 * Demo 01: Quickstart - Basic CRUD Operations
 *
 * This demo covers:
 * - Basic CRUD: create(), update(), delete()
 * - Modern API: findAll(), save(), first()
 * - Using both _id (auto-increment) and _uid (UUID)
 */

declare(strict_types=1);

// Load configuration
require_once __DIR__ . '/config.example.php';
require_once __DIR__ . '/../classes/CBDatabase.php';
require_once __DIR__ . '/../classes/CBObject.php';

use Capps\Modules\Database\Classes\CBObject;

echo "=== QUICKSTART DEMO ===\n\n";

// Initialize user object
$user = new CBObject(null, 'demo_users', 'user_id');

// === BASIC CREATE ===
echo "1. CREATE - Adding a new user\n";
$userId = $user->create([
    'name' => 'Alice Johnson',
    'email' => 'alice@example.com',
    'active' => '1'
]);

if ($userId === false) {
    echo "ERROR: " . $user->getLastError() . "\n\n";
} else {
    echo "Created user with ID: {$userId}\n";
    echo "Auto-generated UUID: {$user->get('user_uid')}\n\n";
}

// === LOAD BY ID ===
echo "2. LOAD - Reading user by ID\n";
if ($userId && $user->load($userId)) {
    echo "Loaded: {$user->get('name')} ({$user->get('email')})\n";
    echo "Created at: {$user->get('date_created')}\n\n";
} else {
    echo "ERROR: Could not load user\n\n";
}

// === LOAD BY UUID ===
echo "3. LOAD BY UUID - Reading user by UUID\n";
$userUuid = $user->get('user_uid');
if ($userUuid) {
    $userByUuid = new CBObject(null, 'demo_users', 'user_id');
    if ($userByUuid->load('user_uid:' . $userUuid)) {
        echo "Loaded via UUID: {$userByUuid->get('name')}\n\n";
    } else {
        echo "ERROR: Could not load user by UUID\n\n";
    }
} else {
    echo "SKIPPED: No UUID available\n\n";
}

// === UPDATE ===
echo "4. UPDATE - Modifying user data\n";
if ($userId && $user->update([
        'name' => 'Alice Smith',
        'email' => 'alice.smith@example.com'
    ], $userId)) {
    $user->load($userId);
    echo "Updated: {$user->get('name')}\n";
    echo "Updated at: {$user->get('date_updated')}\n\n";
} else {
    echo "ERROR: " . ($user->getLastError() ?: "Could not update user") . "\n\n";
}

// === SMART SAVE (auto-detects create vs update) ===
echo "5. SAVE - Smart create/update\n";
$newUser = new CBObject(null, 'demo_users', 'user_id');
$newUser->set('name', 'Bob Wilson');
$newUser->set('email', 'bob@example.com');
$newUser->set('active', '1');
$newId = $newUser->save($newUser->getAttributes()); // Detects it's a new record

if ($newId === false) {
    echo "ERROR: " . $newUser->getLastError() . "\n\n";
} else {
    echo "Saved new user with ID: {$newId}\n";

    $newUser->set('name', 'Robert Wilson');
    if ($newUser->save($newUser->getAttributes())) {
        echo "Updated user name to: {$newUser->get('name')}\n\n";
    } else {
        echo "ERROR: Could not update user name\n\n";
    }
}

// === FIND ALL ===
echo "6. FINDALL - Query multiple records\n";
$results = $user->findAll(
    ['active' => '1'],
    ['order' => 'date_created DESC', 'limit' => 5]
);
echo "Found " . count($results) . " active users:\n";
foreach ($results as $row) {
    echo "  - {$row['name']} ({$row['email']})\n";
}
echo "\n";

// === FIRST ===
echo "7. FIRST - Get first matching record\n";
$firstUser = $user->first(['active' => '1']);
if ($firstUser) {
    echo "First active user: {$firstUser->get('name')}\n\n";
}

// === COUNT ===
echo "8. COUNT - Count records\n";
$total = $user->count();
$active = $user->count(['active' => '1']);
echo "Total users: {$total}\n";
echo "Active users: {$active}\n\n";

// === EXISTS ===
echo "9. EXISTS - Check if record exists\n";
$exists = $user->exists(['email' => 'alice.smith@example.com']);
echo "Email 'alice.smith@example.com' exists: " . ($exists ? 'Yes' : 'No') . "\n\n";

// === DELETE ===
echo "10. DELETE - Remove records\n";
if ($userId && $user->delete($userId)) {
    echo "Deleted user ID: {$userId}\n";
} else {
    echo "ERROR: Could not delete user ID: {$userId}\n";
}

if ($newId && $user->delete($newId)) {
    echo "Deleted user ID: {$newId}\n\n";
} else {
    echo "ERROR: Could not delete user ID: {$newId}\n\n";
}

echo "=== DEMO COMPLETED ===\n";