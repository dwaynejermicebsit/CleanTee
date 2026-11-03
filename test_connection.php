<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// Phase 2 diagnostic only. Remove or disable before deployment.
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Local access only.']);
    exit;
}

try {
    require_once __DIR__ . '/database.php';
    $db = cleantee_db();
    $expected = ['users', 'services', 'bookings', 'reports', 'report_photos', 'staff', 'booking_staff', 'checkouts', 'checkout_bookings', 'password_resets'];
    $stmt = $db->query('SHOW TABLES');
    $found = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $missing = array_values(array_diff($expected, $found));
    $ready = count($missing) === 0;
    http_response_code($ready ? 200 : 503);
    echo json_encode([
        'ok' => $ready,
        'database_connected' => true,
        'schema_ready' => $ready,
        'tables_found' => count(array_intersect($expected, $found)),
        'tables_expected' => count($expected),
        'missing_tables' => $missing,
        'message' => $ready ? 'CLEANTEE database connection successful.' : 'Database connected, but required tables are missing.'
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    error_log('CLEANTEE Phase 2 connection test: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'database_connected' => false,
        'message' => 'Database connection failed. Check XAMPP MySQL and backend/config.php.'
    ], JSON_PRETTY_PRINT);
}
