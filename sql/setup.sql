-- ============================================================
-- 領収書整理アプリ データベース初期化SQL
-- MySQL 用
-- 実行: mysql -u ユーザー名 -p データベース名 < sql/setup.sql
-- ============================================================

SET NAMES utf8mb4;

-- ユーザーテーブル
CREATE TABLE IF NOT EXISTS users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(255) UNIQUE NOT NULL,
  username   VARCHAR(30)  NOT NULL,
  role       ENUM('sysadmin','accounting','general') NOT NULL DEFAULT 'general',
  status     ENUM('pending','active','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 管理者ID/PASSログイン用テーブル
CREATE TABLE IF NOT EXISTS admin_credentials (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  login_id   VARCHAR(100) UNIQUE NOT NULL,
  password   VARCHAR(255) NOT NULL COMMENT 'bcryptハッシュ',
  user_id    INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 操作ログテーブル（追記専用）
CREATE TABLE IF NOT EXISTS logs (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  operator_id       INT          NOT NULL,
  operator_name     VARCHAR(30)  NOT NULL,
  target_receipt_id VARCHAR(100) DEFAULT NULL,
  action            VARCHAR(50)  NOT NULL,
  before_status     VARCHAR(20)  DEFAULT NULL,
  after_status      VARCHAR(20)  DEFAULT NULL,
  comment           TEXT         DEFAULT NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 設定テーブル
CREATE TABLE IF NOT EXISTS settings (
  key_name   VARCHAR(100) PRIMARY KEY,
  value      TEXT         NOT NULL,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- デフォルト設定値
INSERT INTO settings (key_name, value) VALUES
  ('ocr_model',       'gemini-1.5-flash'),
  ('discord_webhook', ''),
  ('gmail_sender',    ''),
  ('admin_email',     '')
ON DUPLICATE KEY UPDATE key_name = key_name;

-- ============================================================
-- 初期システム管理者の登録
-- ※ email を実際の管理者Gmailアドレスに変更してください
-- ============================================================
INSERT INTO users (email, username, role, status) VALUES
  ('admin@example.com', 'システム管理者', 'sysadmin', 'active')
ON DUPLICATE KEY UPDATE role='sysadmin', status='active';

-- 管理者ID/PASS登録
-- パスワード VrK@nsa! のbcryptハッシュ（PHP: password_hash('VrK@nsa!', PASSWORD_BCRYPT)）
INSERT INTO admin_credentials (login_id, password, user_id)
SELECT 'Kansai@Kanri',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- ← setup.phpで上書きされます
       id
FROM users WHERE role='sysadmin' LIMIT 1
ON DUPLICATE KEY UPDATE login_id=login_id;
