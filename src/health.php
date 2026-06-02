<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

try {
    $db = getDB();
    // Run simple quick query to ensure database is responsive
    $stmt = $db->query("SELECT 1");
    $stmt->execute();
    
    http_response_code(200);
    echo json_encode([
        'status' => 'healthy',
        'database' => 'connected',
        'timestamp' => date('c'),
        'environment' => getenv('ENVIRONMENT') ?: 'production'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'unhealthy',
        'database' => 'disconnected',
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
