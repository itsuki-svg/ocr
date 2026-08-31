<?php
require_once __DIR__ . '/../includes/bootstrap.php';
session_start_safe();
$user = require_api_auth();
csrf_check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    json_error('ファイルがありません');
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) json_error('アップロードエラー');
if ($file['size'] > 10 * 1024 * 1024) json_error('ファイルサイズは10MB以下にしてください');

// 画像を読み込みbase64変換
$image_data = base64_encode(file_get_contents($file['tmp_name']));
$mime = $file['type'] ?: 'image/jpeg';

// モデル名を設定から取得
$model = get_setting('ocr_model') ?: 'gemini-1.5-flash';

$prompt = <<<PROMPT
この画像は日本の領収書です。以下の項目を抽出し、JSONのみで返してください。
余計な説明やマークダウンは出力しないでください。

{
  "date": "YYYY-MM-DD",
  "vendor": "店舗名",
  "amount": 数値,
  "tax_rate": "8%" または "10%" または "非課税",
  "category": "会議費" または "交通費" または "消耗品" または "接待交際費" または "その他",
  "payment_method": "現金" または "法人カード" または "個人立替"
}

不明な項目は null としてください。金額は数値型で返してください。
PROMPT;

$payload = json_encode([
    'contents' => [[
        'parts' => [
            ['text' => $prompt],
            ['inline_data' => ['mime_type' => $mime, 'data' => $image_data]],
        ]
    ]],
    'generationConfig' => ['temperature' => 0],
]);

$url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . GEMINI_API_KEY;
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
]);
$resp = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    json_error('OCR APIエラー', 500);
}

$data = json_decode($resp, true);
$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

// JSON部分を抽出
if (!preg_match('/\{[\s\S]*\}/', $text, $m)) {
    json_error('OCR結果のパースに失敗しました', 500);
}

$result = json_decode($m[0], true);
if (!$result) {
    json_error('OCR結果のパースに失敗しました', 500);
}

json_response($result);
