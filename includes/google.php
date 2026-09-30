<?php
// includes/google.php — Google OAuth2 / Sheets / Drive ヘルパー

// ---- OAuth ----

function google_auth_url(): string {
    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'offline',
        'prompt'        => 'select_account',
        'state'         => csrf_token(),
    ]);
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
}

function google_exchange_code(string $code): array {
    $resp = http_post('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);
    return json_decode($resp, true);
}

function google_get_userinfo(string $access_token): array {
    $resp = http_get('https://www.googleapis.com/oauth2/v3/userinfo', $access_token);
    return json_decode($resp, true);
}

// ---- アクセストークン取得（OAuth2リフレッシュ） ----

function get_access_token(): string {
    $row = db_one("SELECT value FROM settings WHERE key_name='gmail_tokens'");
    if (!$row) throw new RuntimeException('OAuth2未認証。/api/auth/get_token.phpにアクセスしてください。');

    $tok = json_decode($row['value'], true);

    // 期限切れなら更新
    if (time() > ($tok['expires_at'] ?? 0) - 60) {
        $resp = http_post('https://oauth2.googleapis.com/token', [
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'refresh_token' => $tok['refresh_token'],
            'grant_type'    => 'refresh_token',
        ]);
        $new = json_decode($resp, true);
        if (!empty($new['access_token'])) {
            $tok['access_token'] = $new['access_token'];
            $tok['expires_at']   = time() + ($new['expires_in'] ?? 3600);
            $json = json_encode($tok);
            db_execute(
                "INSERT INTO settings (key_name, value) VALUES ('gmail_tokens', ?)
                 ON DUPLICATE KEY UPDATE value = ?",
                [$json, $json]
            );
        }
    }
    return $tok['access_token'];
}

// ---- Sheets ----

function sheets_append(string $sheet_id, string $range, array $values): void {
    $token = get_access_token();
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values/{$range}:append"
         . "?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS";
    http_post_json($url, ['values' => [$values]], $token);
}

function sheets_get(string $sheet_id, string $range): array {
    $token = get_access_token();
    $url   = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values/{$range}";
    $resp  = http_get($url, $token);
    $data  = json_decode($resp, true);
    return $data['values'] ?? [];
}

function sheets_batch_update(string $sheet_id, array $data): void {
    $token = get_access_token();
    $url   = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values:batchUpdate";
    http_post_json($url, [
        'valueInputOption' => 'USER_ENTERED',
        'data'             => $data,
    ], $token);
}

function sheets_find_row(string $sheet_id, string $receipt_id): int {
    $rows = sheets_get($sheet_id, 'receipts!A:A');
    foreach ($rows as $i => $row) {
        if (($row[0] ?? '') === $receipt_id) return $i + 1;
    }
    return -1;
}

// ---- Drive ----

function drive_get_or_create_folder(string $parent_id, string $name): string {
    $token = get_access_token();
    $q     = urlencode("name='{$name}' and '{$parent_id}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false");
    $resp  = http_get("https://www.googleapis.com/drive/v3/files?q={$q}&fields=files(id)", $token);
    $data  = json_decode($resp, true);
    if (!empty($data['files'][0]['id'])) return $data['files'][0]['id'];

    $resp = http_post_json('https://www.googleapis.com/drive/v3/files?fields=id', [
        'name'     => $name,
        'mimeType' => 'application/vnd.google-apps.folder',
        'parents'  => [$parent_id],
    ], $token);
    $data = json_decode($resp, true);
    return $data['id'];
}

function drive_upload(string $parent_id, string $name, string $content, string $mime): string {
    $token    = get_access_token();
    $metadata = json_encode(['name' => $name, 'parents' => [$parent_id]]);
    $boundary = 'RECEIPT_APP_' . uniqid();
    $body     = "--{$boundary}\r\n"
              . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
              . $metadata . "\r\n"
              . "--{$boundary}\r\n"
              . "Content-Type: {$mime}\r\n\r\n"
              . $content . "\r\n"
              . "--{$boundary}--";

    $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$token}",
            "Content-Type: multipart/related; boundary={$boundary}",
        ],
    ]);
    $resp    = curl_exec($ch);
    curl_close($ch);
    $data    = json_decode($resp, true);
    $file_id = $data['id'];

    http_post_json(
        "https://www.googleapis.com/drive/v3/files/{$file_id}/permissions",
        ['role' => 'reader', 'type' => 'anyone'],
        $token
    );
    return "https://drive.google.com/file/d/{$file_id}/view";
}

// ---- HTTP ヘルパー ----

function http_get(string $url, string $token = ''): string {
    $ch      = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($token) $headers[] = "Authorization: Bearer {$token}";
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp;
}

function http_post(string $url, array $data): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp;
}

function http_post_json(string $url, array $data, string $token = ''): string {
    $ch      = curl_init($url);
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer {$token}";
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp;
}