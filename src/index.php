<?php
// Set content type to JSON
header('Content-Type: application/json');

// Allow cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Parse the URL path
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', $uri);

// Route the request
require_once 'api.php';
$api = new Api();

// Simple routing
// if ($uri[1] == 'health') {
//         // Bypass all database operations for health checks
//         header('Content-Type: application/json');
//         echo json_encode(['status' => 'healthy']);
//         exit;
// }else
if ($uri[1] == 'api') {

    if ($uri[2] == 'hello') {
        echo $api->getHello();
    } elseif ($uri[2] == 'users' && isset($uri[3])) {
        echo $api->getUser($uri[3]);
    } elseif ($uri[2] == 'status') {
        echo $api->getStatus();
    } elseif ($uri[2] == 'insert' ) {
        echo $api->insertUser($uri[3] ?? '', $uri[4] ?? '');
    } elseif ($uri[2] == 'cache-status' ) {
        echo $api->getCacheStatus();
    }else {
        // Default response
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid endpoint'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'API running',
        'version' => '1.0',
        'endpoints' => ['/api/hello', '/api/users/{id}', '/api/status', '/api/insert/{name}/{email}']
    ]);
}
?>