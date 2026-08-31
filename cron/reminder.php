<?php
/**
 * cron/reminder.php — 毎朝9時 JST に実行される Cron スクリプト
 *
 * さくらCron設定:
 * 0 9 * * * /usr/local/bin/php /path/to/ocr/cron/reminder.php 1> /dev/null
 *
 * 実行権限付与:
 * chmod 755 /path/to/ocr/cron/reminder.php
 */

define('CRON_MODE', true);
require_once __DIR__ . '/../includes/bootstrap.php';

// Sheetsから審査待ち件数を取得
$receipts = get_all_receipts();
$pending  = array_filter($receipts, fn($r) =>
    $r['status'] === '審査待ち' || $r['status'] === '再審査待ち'
);
$count = count($pending);

discord_notify_pending($count);

$ts = date('Y-m-d H:i:s');
echo "[{$ts}] Cron reminder: {$count}件の審査待ち\n";
