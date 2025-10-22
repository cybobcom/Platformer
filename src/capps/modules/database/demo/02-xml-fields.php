<?php
/**
 * Demo 02: XML Fields - Structured Data Storage
 *
 * This demo covers:
 * - data_* fields: Generic structured data (CDATA)
 * - media_* fields: Image/file metadata
 * - settings_* fields: Configuration data
 * - Sorting by XML fields
 */

declare(strict_types=1);

// Load configuration
require_once __DIR__ . '/config.example.php';
require_once __DIR__ . '/../classes/CBDatabase.php';
require_once __DIR__ . '/../classes/CBObject.php';

use Capps\Modules\Database\Classes\CBObject;

echo "=== XML FIELDS DEMO ===\n\n";

// Initialize user object
$user = new CBObject(null, 'demo_users', 'user_id');

// === DATA_* FIELDS - Generic structured data ===
echo "1. DATA_* FIELDS - Storing structured data\n";
$userId = $user->create([
    'name' => 'Charlie Davis',
    'email' => 'charlie@example.com',
    'active' => '1',
    'data_profile' => json_encode([
        'bio' => 'Software developer',
        'location' => 'New York',
        'website' => 'https://charlie.dev'
    ]),
    'data_preferences' => json_encode([
        'theme' => 'dark',
        'language' => 'en',
        'notifications' => true
    ])
]);

if ($userId === false) {
    echo "ERROR: " . $user->getLastError() . "\n\n";
} else {
    $user->load($userId);
    $profile = json_decode($user->get('data_profile'), true);
    $preferences = json_decode($user->get('data_preferences'), true);

    echo "Profile:\n";
    echo "  Bio: {$profile['bio']}\n";
    echo "  Location: {$profile['location']}\n";
    echo "  Website: {$profile['website']}\n";
    echo "Preferences:\n";
    echo "  Theme: {$preferences['theme']}\n";
    echo "  Language: {$preferences['language']}\n\n";
}

// === MEDIA_* FIELDS - Image and file metadata ===
echo "2. MEDIA_* FIELDS - Storing media metadata\n";
if ($userId) {
    if ($user->update([
        'media_avatar' => json_encode([
            'filename' => 'charlie-avatar.jpg',
            'path' => '/uploads/avatars/',
            'size' => 153600,
            'mime' => 'image/jpeg',
            'width' => 400,
            'height' => 400
        ]),
        'media_cover' => json_encode([
            'filename' => 'cover-photo.jpg',
            'path' => '/uploads/covers/',
            'size' => 512000,
            'mime' => 'image/jpeg'
        ])
    ], $userId)) {
        $user->load($userId);
        $avatar = json_decode($user->get('media_avatar'), true);
        $cover = json_decode($user->get('media_cover'), true);

        echo "Avatar:\n";
        echo "  File: {$avatar['path']}{$avatar['filename']}\n";
        echo "  Size: " . round($avatar['size'] / 1024, 2) . " KB\n";
        echo "  Dimensions: {$avatar['width']}x{$avatar['height']}\n";
        echo "Cover Photo:\n";
        echo "  File: {$cover['path']}{$cover['filename']}\n";
        echo "  Size: " . round($cover['size'] / 1024, 2) . " KB\n\n";
    } else {
        echo "ERROR: " . $user->getLastError() . "\n\n";
    }
} else {
    echo "SKIPPED: No user created\n\n";
}

// === SETTINGS_* FIELDS - Configuration data ===
echo "3. SETTINGS_* FIELDS - Storing configuration\n";
if ($userId) {
    if ($user->update([
        'settings_privacy' => json_encode([
            'profile_visible' => true,
            'email_visible' => false,
            'show_online' => true
        ]),
        'settings_notifications' => json_encode([
            'email_updates' => true,
            'push_enabled' => false,
            'frequency' => 'daily'
        ])
    ], $userId)) {
        $user->load($userId);
        $privacy = json_decode($user->get('settings_privacy'), true);
        $notifications = json_decode($user->get('settings_notifications'), true);

        echo "Privacy Settings:\n";
        echo "  Profile visible: " . ($privacy['profile_visible'] ? 'Yes' : 'No') . "\n";
        echo "  Email visible: " . ($privacy['email_visible'] ? 'Yes' : 'No') . "\n";
        echo "Notification Settings:\n";
        echo "  Email updates: " . ($notifications['email_updates'] ? 'Yes' : 'No') . "\n";
        echo "  Frequency: {$notifications['frequency']}\n\n";
    } else {
        echo "ERROR: " . $user->getLastError() . "\n\n";
    }
} else {
    echo "SKIPPED: No user created\n\n";
}

// === SORTING BY XML FIELDS ===
echo "4. SORTING - Ordering by XML field content\n";

// Create additional test users
$user2Id = $user->create([
    'name' => 'Alice Anderson',
    'email' => 'alice@example.com',
    'active' => '1',
    'data_profile' => json_encode(['location' => 'Boston'])
]);

if ($user2Id === false) {
    echo "ERROR creating user 2: " . $user->getLastError() . "\n";
}

$user3Id = $user->create([
    'name' => 'Bob Brown',
    'email' => 'bob@example.com',
    'active' => '1',
    'data_profile' => json_encode(['location' => 'Seattle'])
]);

if ($user3Id === false) {
    echo "ERROR creating user 3: " . $user->getLastError() . "\n";
}

// Note: Sorting by XML field content requires extracting values
// This is a simplified example - for production, consider indexing or views
$allUsers = $user->findAll(['active' => '1']);
echo "All active users:\n";
foreach ($allUsers as $row) {
    $profile = json_decode($row['data_profile'] ?? '{}', true);
    $location = $profile['location'] ?? 'Unknown';
    echo "  - {$row['name']} (Location: {$location})\n";
}
echo "\n";

// === UPDATING XML FIELDS ===
echo "5. UPDATING - Modifying XML field data\n";
if ($userId && $user->load($userId)) {
    $profile = json_decode($user->get('data_profile'), true);
    $profile['bio'] = 'Senior Software Developer';
    $profile['years_experience'] = 8;

    if ($user->update([
        'data_profile' => json_encode($profile)
    ], $userId)) {
        $user->load($userId);
        $updatedProfile = json_decode($user->get('data_profile'), true);
        echo "Updated profile:\n";
        echo "  Bio: {$updatedProfile['bio']}\n";
        echo "  Experience: {$updatedProfile['years_experience']} years\n\n";
    } else {
        echo "ERROR: " . $user->getLastError() . "\n\n";
    }
} else {
    echo "SKIPPED: Could not load user\n\n";
}

// Cleanup
echo "Cleaning up test data...\n";
if ($userId && $user->delete($userId)) {
    echo "Deleted user ID: {$userId}\n";
}
if ($user2Id && $user->delete($user2Id)) {
    echo "Deleted user ID: {$user2Id}\n";
}
if ($user3Id && $user->delete($user3Id)) {
    echo "Deleted user ID: {$user3Id}\n";
}
echo "\n";

echo "=== DEMO COMPLETED ===\n";