<?php
/**
 * Sidebar Dispatcher
 * Loads the appropriate sidebar based on user role
 */

if (!isset($user) || !isset($user['role'])) {
    // If user is not set, we can't determine which sidebar to show
    return;
}

$is_admin = strtolower(trim($user['role'])) === 'admin';

if ($is_admin) {
    require __DIR__ . '/sidebar_admin.php';
} else {
    require __DIR__ . '/sidebar_user.php';
}
?>