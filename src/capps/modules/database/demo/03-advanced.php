<?php
/**
 * Demo 03: Advanced Features
 *
 * This demo covers:
 * - Transactions (ACID-compliant)
 * - Soft delete functionality
 * - Bulk operations (insertBatch)
 * - Auto-timestamps (date_created, date_updated)
 */

declare(strict_types=1);

// Load configuration
require_once __DIR__ . '/config.example.php';
require_once __DIR__ . '/../classes/CBDatabase.php';
require_once __DIR__ . '/../classes/CBObject.php';

use Capps\Modules\Database\Classes\CBDatabase;
use Capps\Modules\Database\Classes\CBObject;

echo "=== ADVANCED FEATURES DEMO ===\n\n";

// === TRANSACTIONS ===
echo "1. TRANSACTIONS - ACID-compliant operations\n";

$db = new CBDatabase();
$user = new CBObject(null, 'demo_users', 'user_id');

try {
    $db->beginTransaction();

    // Create multiple users in a transaction
    $user1Id = $user->create([
        'name' => 'Transaction User 1',
        'email' => 'trans1@example.com',
        'active' => '1'
    ]);

    if ($user1Id === false) {
        throw new Exception("Failed to create user 1: " . $user->getLastError());
    }

    $user2Id = $user->create([
        'name' => 'Transaction User 2',
        'email' => 'trans2@example.com',
        'active' => '1'
    ]);

    if ($user2Id === false) {
        throw new Exception("Failed to create user 2: " . $user->getLastError());
    }

    $db->commit();
    echo "Transaction committed successfully\n";
    echo "Created users with IDs: {$user1Id}, {$user2Id}\n\n";

    // Cleanup
    $user->delete($user1Id);
    $user->delete($user2Id);

} catch (Exception $e) {
    $db->rollback();
    echo "Transaction rolled back: {$e->getMessage()}\n\n";
}

// === TRANSACTION ROLLBACK EXAMPLE ===
echo "2. TRANSACTION ROLLBACK - Error handling\n";

try {
    $db->beginTransaction();

    $userId = $user->create([
        'name' => 'Rollback Test',
        'email' => 'rollback@example.com',
        'active' => '1'
    ]);

    if ($userId === false) {
        throw new Exception("Failed to create user: " . $user->getLastError());
    }

    echo "Created user with ID: {$userId}\n";

    // Simulate error
    throw new Exception("Simulated error - transaction will be rolled back");

    $db->commit();

} catch (Exception $e) {
    $db->rollback();
    echo "Transaction rolled back: {$e->getMessage()}\n";
    echo "User was NOT saved to database\n\n";
}

// === SOFT DELETE ===
echo "3. SOFT DELETE - Mark records as deleted without removing them\n";

$userId = $user->create([
    'name' => 'Soft Delete User',
    'email' => 'softdelete@example.com',
    'active' => '1'
]);

if ($userId === false) {
    echo "ERROR: " . $user->getLastError() . "\n\n";
} else {
    echo "Created user with ID: {$userId}\n";

    // Soft delete (sets deleted_at timestamp)
    if ($user->softDelete($userId)) {
        echo "User soft deleted (deleted_at set)\n";

        // Load to verify
        if ($user->load($userId)) {
            echo "Deleted at: {$user->get('deleted_at')}\n";

            // Restore soft deleted user
            if ($user->restoreSoftDeleted($userId)) {
                echo "User restored (deleted_at cleared)\n";

                // Verify restoration
                $user->load($userId);
                echo "Deleted at after restore: " . ($user->get('deleted_at') ?: 'NULL') . "\n\n";
            } else {
                echo "ERROR: Could not restore user\n\n";
            }
        } else {
            echo "ERROR: Could not load user after soft delete\n\n";
        }
    } else {
        echo "ERROR: Could not soft delete user\n\n";
    }

    // Cleanup
    $user->delete($userId);
}

// === BULK OPERATIONS (insertBatch) ===
echo "4. INSERTBATCH - High-performance bulk insert\n";

$startTime = microtime(true);

$bulkData = [];
for ($i = 1; $i <= 100; $i++) {
    $bulkData[] = [
        'name' => "Bulk User {$i}",
        'email' => "bulk{$i}@example.com",
        'active' => '1'
    ];
}

try {
    $insertedIds = $user->insertBatch($bulkData);
    $endTime = microtime(true);

    echo "Inserted " . count($insertedIds) . " records\n";
    echo "Time taken: " . round(($endTime - $startTime) * 1000, 2) . " ms\n";
    echo "First ID: {$insertedIds[0]}\n";
    echo "Last ID: {$insertedIds[count($insertedIds) - 1]}\n\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
    $insertedIds = [];
}

// === PERFORMANCE COMPARISON: Loop vs Batch ===
echo "5. PERFORMANCE - Loop vs insertBatch comparison\n";

// Method 1: Loop (slower)
$loopStart = microtime(true);
$loopIds = [];
for ($i = 1; $i <= 50; $i++) {
    $id = $user->create([
        'name' => "Loop User {$i}",
        'email' => "loop{$i}@example.com",
        'active' => '1'
    ]);
    if ($id !== false) {
        $loopIds[] = $id;
    }
}
$loopEnd = microtime(true);
$loopTime = ($loopEnd - $loopStart) * 1000;

// Method 2: Batch (faster)
$batchData = [];
for ($i = 1; $i <= 50; $i++) {
    $batchData[] = [
        'name' => "Batch User {$i}",
        'email' => "batch{$i}@example.com",
        'active' => '1'
    ];
}
$batchStart = microtime(true);
try {
    $batchIds = $user->insertBatch($batchData);
    $batchEnd = microtime(true);
    $batchTime = ($batchEnd - $batchStart) * 1000;

    echo "Loop method (50 records): " . round($loopTime, 2) . " ms\n";
    echo "Batch method (50 records): " . round($batchTime, 2) . " ms\n";
    echo "Speed improvement: " . round($loopTime / $batchTime, 2) . "x faster\n\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
    $batchIds = [];
}

// === AUTO-TIMESTAMPS ===
echo "6. AUTO-TIMESTAMPS - Automatic date_created and date_updated\n";

$userId = $user->create([
    'name' => 'Timestamp User',
    'email' => 'timestamp@example.com',
    'active' => '1'
]);

if ($userId === false) {
    echo "ERROR: " . $user->getLastError() . "\n\n";
} else {
    $user->load($userId);
    echo "Created user with ID: {$userId}\n";
    echo "date_created: {$user->get('date_created')}\n";
    echo "date_updated: {$user->get('date_updated')}\n\n";

    // Wait 1 second and update
    sleep(1);
    if ($user->update(['name' => 'Timestamp User Updated'], $userId)) {
        $user->load($userId);
        echo "After update:\n";
        echo "date_created: {$user->get('date_created')} (unchanged)\n";
        echo "date_updated: {$user->get('date_updated')} (updated)\n\n";
    } else {
        echo "ERROR: " . $user->getLastError() . "\n\n";
    }

    // Cleanup
    $user->delete($userId);
}

// === CLEANUP ALL TEST DATA ===
echo "7. CLEANUP - Removing all test data\n";

$deletedCount = 0;

// Delete bulk inserted users
if (!empty($insertedIds)) {
    foreach ($insertedIds as $id) {
        if ($user->delete($id)) {
            $deletedCount++;
        }
    }
}

// Delete loop users
if (!empty($loopIds)) {
    foreach ($loopIds as $id) {
        if ($user->delete($id)) {
            $deletedCount++;
        }
    }
}

// Delete batch users
if (!empty($batchIds)) {
    foreach ($batchIds as $id) {
        if ($user->delete($id)) {
            $deletedCount++;
        }
    }
}

echo "Cleaned up {$deletedCount} test records\n\n";

echo "=== DEMO COMPLETED ===\n";