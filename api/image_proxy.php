<?php
require_once __DIR__ . '/../includes/bootstrap.php';
session_start_safe();
require_api_auth();

$file_id = $_GET['id'] ?? '';
if (!$file_id || !preg_match('/^[a-zA-Z0-9_-]+$/', $file_id)) {
    http_response_code(400);
    exit('Invalid file ID');
}

$token = get_access_token();
$url   = "https://www.googleapis.com/drive/v3/files/{$file_id}?alt=media";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}"],
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
]);
$data    = curl_exec($ch);
$info    = curl_getinfo($ch);
curl_close($ch);

if ($info['http_code'] !== 200) {
    http_response_code(404);
    exit('Image not found');
}

$mime = $info['content_type'] ?: 'image/jpeg';
header("Content-Type: {$mime}");
header("Cache-Control: private, max-age=3600");
echo $data;