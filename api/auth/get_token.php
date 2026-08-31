<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

// コードをトークンに交換
if (!empty($_GET['code'])) {
    $resp = http_post('https://oauth2.googleapis.com/token', [
        'code'          => $_GET['code'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => APP_URL . '/api/auth/get_token.php',
        'grant_type'    => 'authorization_code',
    ]);
    $data = json_decode($resp, true);
    $data['expires_at'] = time() + ($data['expires_in'] ?? 3600);
    
    // DBに保存
    $json = json_encode($data);
    db_execute(
        "INSERT INTO settings (key_name, value) VALUES ('gmail_tokens', ?)
         ON DUPLICATE KEY UPDATE value = ?",
        [$json, $json]
    );
    echo '<h2>✅ 認証完了！</h2>';
    echo '<p>リフレッシュトークンを取得しました。このページを閉じてください。</p>';
    echo '<pre>' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>';
    exit;
}

// 認証URLにリダイレクト
$scopes = implode(' ', [
    'https://www.googleapis.com/auth/spreadsheets',
    'https://www.googleapis.com/auth/drive',
    'https://www.googleapis.com/auth/gmail.send',
]);

$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => APP_URL . '/api/auth/get_token.php',
    'response_type' => 'code',
    'scope'         => $scopes,
    'access_type'   => 'offline',
    'prompt'        => 'consent',
]);

header('Location: ' . $url);
exit;