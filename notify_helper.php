<?php
/**
 * In-app notification helper.
 * Creates/migrates the notifications table and exposes insertNotification().
 */

function ensureNotificationsTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id          SERIAL PRIMARY KEY,
        recipient   VARCHAR(100) NOT NULL,
        actor       VARCHAR(100) NOT NULL DEFAULT '系統',
        type        VARCHAR(50)  DEFAULT 'new_video',
        message     TEXT,
        video_id    INT,
        video_title VARCHAR(255),
        is_read     BOOLEAN      DEFAULT FALSE,
        created_at  TIMESTAMP    DEFAULT NOW()
    )");
    // Add message column to existing tables that were created before this column existed
    try {
        $pdo->exec("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS message TEXT");
    } catch (Exception $e) { /* ignore if column already exists or DB doesn't support IF NOT EXISTS */ }
}

function insertNotification(
    PDO    $pdo,
    string $recipient,
    string $type,
    string $message,
    string $actor    = '系統',
    ?int   $videoId  = null,
    ?string $videoTitle = null
): void {
    try {
        ensureNotificationsTable($pdo);
        $pdo->prepare(
            "INSERT INTO notifications (recipient, actor, type, message, video_id, video_title)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$recipient, $actor, $type, $message, $videoId, $videoTitle]);
    } catch (Exception $e) {
        error_log('insertNotification 失敗：' . $e->getMessage());
    }
}
