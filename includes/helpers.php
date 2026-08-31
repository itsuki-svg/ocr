<?php
// includes/helpers.php — メール・Discord・ログ・ユーティリティ

// ---- ロガー ----

function write_log(array $log): void {
    // MySQL に INSERT
    db_execute(
        'INSERT INTO logs
           (operator_id, operator_name, target_receipt_id, action, before_status, after_status, comment)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            $log['operator_id'],
            $log['operator_name'],
            $log['target_receipt_id'] ?? null,
            $log['action'],
            $log['before_status']    ?? null,
            $log['after_status']     ?? null,
            $log['comment']          ?? null,
        ]
    );

    // Google Sheets にも追記
    try {
        sheets_append(LOG_SHEET_ID, 'logs!A:I', [
    		'',
   		 $log['operator_name'],
   		 date('Y-m-d H:i:s'),
   		 $log['target_receipt_id'] ?? '',
   		 $log['action'],
  		 $log['before_status']    ?? '',
   		 $log['after_status']     ?? '',
   		 $log['comment']          ?? '',
   		 $_SERVER['REMOTE_ADDR']  ?? '',
	]);
    } catch (Throwable $e) {
        error_log('Sheets log error: ' . $e->getMessage());
    }
}

// ---- Discord ----

function discord_notify(string $content): void {
    $webhook = db_one("SELECT value FROM settings WHERE key_name='discord_webhook'")['value'] ?? '';
    if (!$webhook) return;
    http_post_json($webhook, ['content' => $content]);
}

function discord_notify_new_user(string $username, string $email): void {
    discord_notify(implode("\n", [
        '👤 **【新規ユーザー登録】**',
        "ユーザーネーム: **{$username}**",
        "メール: {$email}",
        '承認をお願いいたします。',
        APP_URL . '/admin/users.php',
    ]));
}

function discord_notify_pending(int $count): void {
    if ($count <= 0) return;
    discord_notify(implode("\n", [
        '📋 **【領収書審査のお知らせ】**',
        "本日9:00時点で審査待ちの申請が **{$count}件** あります。",
        'ご確認をお願いいたします。',
        APP_URL . '/admin/receipts.php',
    ]));
}

// ---- Gmail 送信 ----

function send_gmail(string $to, string $subject, string $html_body,
                    string $sender_name, string $sender_email): bool {
    // Gmail API OAuth2トークンを取得
    $tokens = db_one("SELECT value FROM settings WHERE key_name='gmail_tokens'");
    if (!$tokens) {
        error_log('Gmail未認証');
        return false;
    }
    $tok = json_decode($tokens['value'], true);

    // アクセストークンのリフレッシュ
    if (time() > ($tok['expires_at'] ?? 0)) {
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
            db_execute(
                "INSERT INTO settings (key_name, value) VALUES ('gmail_tokens', ?)
                 ON DUPLICATE KEY UPDATE value = ?",
                [json_encode($tok), json_encode($tok)]
            );
        }
    }

    // RFC 2822 メッセージ作成
    $subject_encoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $from = "=?UTF-8?B?" . base64_encode($sender_name) . "?= <{$sender_email}>";
    $message = implode("\n", [
        "From: {$from}",
        "To: {$to}",
        "Subject: {$subject_encoded}",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "",
        $html_body,
    ]);
    $encoded = rtrim(strtr(base64_encode($message), '+/', '-_'), '=');

    $resp = http_post_json(
        'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
        ['raw' => $encoded],
        $tok['access_token']
    );
    $data = json_decode($resp, true);
    return !empty($data['id']);
}

// ---- メールテンプレート ----

function mail_template(string $title, string $body): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><style>
body{font-family:sans-serif;background:#f5f5f5;margin:0;padding:20px}
.wrap{max-width:560px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;border:1px solid #e0e0e0}
.hdr{background:#2563eb;color:#fff;padding:18px 24px}.hdr h1{margin:0;font-size:17px}
.bd{padding:24px;color:#333;line-height:1.7}.ft{padding:14px 24px;font-size:12px;color:#999;border-top:1px solid #f0f0f0}
.btn{display:inline-block;background:#2563eb;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:14px;margin-top:14px}
table{font-size:14px;border-collapse:collapse;width:100%}td{padding:5px 0}
.label{color:#666;width:130px}.cmt{background:#f8f8f8;border-left:3px solid #ccc;padding:10px 14px;margin:12px 0;font-size:14px}
</style></head>
<body><div class="wrap">
<div class="hdr"><h1>領収書整理アプリ</h1></div>
<div class="bd"><h2 style="font-size:16px;margin-top:0">{$title}</h2>{$body}
<a href="APP_URL_PLACEHOLDER" class="btn">アプリを開く</a></div>
<div class="ft">このメールは領収書整理アプリから自動送信されています。</div>
</div></body></html>
HTML;
}

function build_status_mail(string $status, array $receipt, string $comment, string $sender_name): array {
    $titles = [
        '受理'     => '申請が受理されました',
        '修正待ち' => '修正依頼のお知らせ',
        '差し戻し' => '申請が差し戻されました',
    ];
    $title   = $titles[$status] ?? "ステータスが「{$status}」に変更されました";
    $subject = "【領収書申請】{$title}";
    $amount  = number_format($receipt['amount']);
    $cmt_html = $comment
        ? "<div class='cmt'><strong>担当者コメント:</strong><br>" . htmlspecialchars($comment) . "</div>"
        : '';
    $warn = in_array($status, ['修正待ち', '差し戻し'], true)
        ? "<p style='color:#c00'>アプリにログインして内容を確認・修正してください。</p>"
        : '';
    $body = <<<HTML
<p>{$sender_name} が申請を確認しました。</p>
<table>
<tr><td class='label'>店舗名</td><td><strong>{$receipt['vendor']}</strong></td></tr>
<tr><td class='label'>金額</td><td><strong>¥{$amount}</strong></td></tr>
<tr><td class='label'>利用日</td><td>{$receipt['date']}</td></tr>
<tr><td class='label'>新ステータス</td><td><strong>{$status}</strong></td></tr>
</table>{$cmt_html}{$warn}
HTML;
    $html = str_replace('APP_URL_PLACEHOLDER', APP_URL, mail_template($title, $body));
    return compact('subject', 'html');
}

function build_approval_mail(bool $approved, string $username): array {
    $title   = $approved ? 'アカウントが承認されました' : 'アカウントが承認されませんでした';
    $subject = "【領収書整理アプリ】{$title}";
    $body    = $approved
        ? "<p>{$username} さん、アカウントが承認されました。<br>以下のリンクからログインしてご利用ください。</p>"
        : "<p>{$username} さん、アカウントの利用が承認されませんでした。<br>ご不明な点はシステム管理者にお問い合わせください。</p>";
    $html = str_replace('APP_URL_PLACEHOLDER', APP_URL, mail_template($title, $body));
    return compact('subject', 'html');
}

function build_new_user_mail(string $username, string $email): array {
    $title   = '新規ユーザーが登録しました';
    $subject = "【領収書整理アプリ】{$title}";
    $body    = "<p>新規ユーザーが登録申請しました。承認をお願いします。</p>
<table>
<tr><td class='label'>ユーザーネーム</td><td><strong>{$username}</strong></td></tr>
<tr><td class='label'>メール</td><td>{$email}</td></tr>
</table>";
    $html = str_replace('APP_URL_PLACEHOLDER', APP_URL . '/admin/users.php', mail_template($title, $body));
    return compact('subject', 'html');
}

// ---- ユーティリティ ----

function generate_uuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function row_to_receipt(array $row): array {
    return [
        'id'                => $row[0]  ?? '',
        'created_at'        => $row[1]  ?? '',
        'email'             => $row[2]  ?? '',
        'username'          => $row[3]  ?? '',
        'date'              => $row[4]  ?? '',
        'vendor'            => $row[5]  ?? '',
        'amount'            => (int)($row[6] ?? 0),
        'tax_rate'          => $row[7]  ?? '',
        'category'          => $row[8]  ?? '',
        'payment_method'    => $row[9]  ?? '',
        'note'              => $row[10] ?? '',
        'image_url'         => $row[11] ?? '',
        'input_method'      => $row[12] ?? 'manual',
        'status'            => $row[13] ?? '審査待ち',
        'last_comment'      => $row[14] ?? '',
        'status_updated_at' => $row[15] ?? '',
        'status_updated_by' => $row[16] ?? '',
    ];
}

function get_all_receipts(): array {
    $rows = sheets_get(SHEET_ID, 'receipts!A2:Q');
    return array_map('row_to_receipt', $rows);
}

function get_receipt_by_id(string $id): ?array {
    $rows = get_all_receipts();
    foreach ($rows as $r) {
        if ($r['id'] === $id) return $r;
    }
    return null;
}

function update_receipt_status(string $id, string $status, string $comment, string $updated_by): void {
    $row_num = sheets_find_row(SHEET_ID, $id);
    if ($row_num < 0) throw new RuntimeException('Receipt not found');
    $now = date('Y-m-d H:i:s');
    sheets_batch_update(SHEET_ID, [
        ['range' => "receipts!N{$row_num}", 'values' => [[$status]]],
        ['range' => "receipts!O{$row_num}", 'values' => [[$comment]]],
        ['range' => "receipts!P{$row_num}", 'values' => [[$now]]],
        ['range' => "receipts!Q{$row_num}", 'values' => [[$updated_by]]],
    ]);
}

function get_setting(string $key): string {
    $row = db_one("SELECT value FROM settings WHERE key_name=?", [$key]);
    return $row['value'] ?? '';
}
