<?php
// Debug file to test API routing
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-XSRF-TOKEN');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = [
    'message' => 'Debug endpoint - .htaccess is working',
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'request_uri' => $_SERVER['REQUEST_URI'],
    'script_name' => $_SERVER['SCRIPT_NAME'],
    'document_root' => $_SERVER['DOCUMENT_ROOT'],
    'http_host' => $_SERVER['HTTP_HOST'],
    'http_origin' => $_SERVER['HTTP_ORIGIN'] ?? 'not set',
    'http_referer' => $_SERVER['HTTP_REFERER'] ?? 'not set',
    'headers' => getallheaders(),
    'post_data' => $_POST,
    'files' => $_FILES,
];

echo json_encode($data, JSON_PRETTY_PRINT);
