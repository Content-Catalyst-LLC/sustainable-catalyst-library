<?php
$secret = getenv('SC_LIBRARY_WEBHOOK_SECRET') ?: '';
$timestamp = $_SERVER['HTTP_X_SC_TIMESTAMP'] ?? '';
$provided = $_SERVER['HTTP_X_SC_SIGNATURE'] ?? '';
$body = file_get_contents('php://input') ?: '';

if ($secret === '' || $timestamp === '' || abs(time() - (int) $timestamp) > 300) {
    http_response_code(401);
    exit('Invalid timestamp');
}

$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
if (!hash_equals($expected, $provided)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
http_response_code(204);
