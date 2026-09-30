# 領収書 OCR 経費申請システム

Gemini Vision API で領収書画像を解析し、経費申請から承認・Google Sheets 連携まで一気通貫で行うシステムです。

## Features

- **AI OCR** — Gemini Vision API で領収書画像から日付・金額・店舗名・税区分を自動抽出
- **申請ワークフロー** — 申請 → 承認/差戻し → 完了の状態管理
- **Google Sheets 連携** — 承認済み経費データを Google Sheets に自動出力
- **Google Drive 連携** — 領収書画像を Google Drive に保存
- **Google OAuth** — Google アカウントでログイン、利用者の承認制
- **管理者機能** — ユーザー承認、全申請の一覧・検索、設定管理、操作ログ
- **Cron リマインダー** — 未処理の申請を定期通知

## Workflow

申請ワークフローは下書き → 申請中 → 承認済み / 差戻しの状態遷移で管理します。承認済みの経費データは Google Sheets に自動出力され、領収書画像は Google Drive に保存されます。OCR 処理は `api/ocr.php` でユーザーが撮影/選択した画像を base64 エンコードし、Gemini Vision API に送信して日付・金額・店舗名・税区分を JSON で取得します。

## Database Schema

| テーブル | 概要 | 主なカラム |
|---------|------|-----------|
| `users` | ユーザー | id, email, username, role (sysadmin/accounting/general), status (pending/active/rejected) |
| `admin_credentials` | 管理者ログイン | id, login_id, password (bcrypt), user_id FK |
| `logs` | 操作ログ | id, operator_id, target_receipt_id, action, before_status, after_status, comment |
| `settings` | システム設定 | key_name PK, value (ocr_model, discord_webhook, gmail_sender 等) |

### 経費データ管理

領収書データは Google Sheets をマスターデータとして使用。DB にはユーザー・権限・ログのみを保持する設計。

## API Endpoints

| Method | Path | 認証 | 概要 |
|--------|------|------|------|
| `POST` | `/api/ocr.php` | Session | 画像を Gemini Vision で OCR 解析 |
| `GET` | `/api/auth/callback.php` | — | Google OAuth コールバック |
| `POST` | `/api/auth/admin_login.php` | — | 管理者 ID/パスワードログイン |
| `GET` | `/api/receipts/list.php` | Session (admin) | 全申請一覧 |
| `GET` | `/api/receipts/mine.php` | Session | 自分の申請一覧 |
| `POST` | `/api/receipts/create.php` | Session | 新規申請 |
| `POST` | `/api/receipts/edit.php` | Session | 申請編集 |
| `POST` | `/api/receipts/status.php` | Session (admin) | 承認/差戻し |
| `GET` | `/api/users/list.php` | Session (admin) | ユーザー一覧 |
| `POST` | `/api/users/approve.php` | Session (admin) | ユーザー承認/却下 |
| `POST` | `/api/settings/save.php` | Session (admin) | 設定保存 |

## Screen Flow

ログイン後のダッシュボードから自分の申請一覧を確認できます。新規申請ではカメラ/画像選択から OCR 解析、金額・店舗確認を経て申請します。管理者メニューでは全申請一覧（検索・フィルター）、承認/差戻し（コメント付き）、ユーザー管理（承認/却下）、操作ログ閲覧、設定（OCR モデル・通知先）が利用できます。

## Security

| 項目 | 実装 |
|------|------|
| 認証 | Google OAuth 2.0 + 管理者 ID/Pass (bcrypt) |
| ユーザー承認制 | 新規登録後、管理者が承認するまでログイン不可 |
| ロール管理 | sysadmin / accounting / general の3段階 |
| CSRF | セッションベーストークン検証 |
| 操作ログ | 全承認/差戻し/設定変更を `logs` テーブルに記録 |
| Cron認証 | `CRON_SECRET` による外部トリガー防止 |

## Tech Stack

| 項目 | 内容 |
|------|------|
| Backend | PHP 8.x |
| Database | MySQL 8.0 |
| AI/OCR | Gemini API (Vision) |
| Auth | Google OAuth 2.0, bcrypt (管理者), セッション認証 |
| External | Google Sheets API, Google Drive API |
| Security | CSRF トークン, 操作ログ |

## Directory Structure

```
ocr/
├── config.php              # 設定（config.example.php を参照）
├── includes/               # bootstrap・DB・認証・Google連携・ヘルパー
├── api/
│   ├── ocr.php             # Gemini Vision OCR API
│   ├── auth/               # Google OAuth 認証フロー
│   ├── receipts/           # 申請 CRUD API
│   ├── users/              # ユーザー承認 API
│   └── settings/           # 設定保存 API
├── admin/                  # 管理画面（申請一覧・ユーザー管理・ログ・設定）
├── assets/                 # CSS・JS
├── cron/                   # リマインダー
└── sql/setup.sql           # テーブル定義
```

## Setup

1. `config.example.php` を `config.php` にコピーし、DB 情報・Google OAuth・Gemini API キーを設定
2. `sql/setup.sql` を MySQL に流す
3. Google Cloud Console で OAuth 認証情報・Service Account を設定
4. Google Sheets・Drive のフォルダID を config に設定
