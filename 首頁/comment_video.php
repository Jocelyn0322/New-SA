<?php
session_start();
require 'db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit;
}

// 支援 JSON body（fetch 送來的）
$jsonBody = [];
$rawInput = file_get_contents('php://input');
if ($rawInput) {
    $jsonBody = json_decode($rawInput, true) ?? [];
}
$action   = $jsonBody['action']   ?? $_POST['action']   ?? $_GET['action'] ?? '';
$username = $_SESSION['user'];

try {
    if ($action === 'add') {
        $videoId = (int)($_POST['video_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if (!$videoId || empty($content)) {
            echo json_encode(['success' => false, 'message' => '請輸入有效的評論']);
            exit;
        }

        if (strlen($content) < 2) {
            echo json_encode(['success' => false, 'message' => '評論至少需要2個字']);
            exit;
        }

        // 檢查影片是否存在
        $stmt = $pdo->prepare("SELECT id FROM videos WHERE id = ?");
        $stmt->execute([$videoId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => '影片不存在']);
            exit;
        }

        // 新增評論
        $stmt = $pdo->prepare("
            INSERT INTO video_comments (video_id, parent_id, username, content)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$videoId, $parentId, $username, $content]);

        echo json_encode([
            'success' => true,
            'message' => '評論已發佈',
            'comment_id' => $pdo->lastInsertId()
        ]);

    } elseif ($action === 'delete') {
        $commentId = (int)($_POST['comment_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT username FROM video_comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();

        if (!$comment) {
            echo json_encode(['success' => false, 'message' => '評論不存在']);
            exit;
        }

        if ($comment['username'] !== $username && ($_SESSION['role'] ?? '') !== 'admin') {
            echo json_encode(['success' => false, 'message' => '您沒有權限刪除此評論']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM video_comments WHERE id = ?");
        $stmt->execute([$commentId]);

        echo json_encode(['success' => true, 'message' => '評論已刪除']);

    } elseif ($action === 'toggle_like') {
        $commentId = (int)($_POST['comment_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT id FROM video_comments WHERE id = ?");
        $stmt->execute([$commentId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => '評論不存在']);
            exit;
        }

        // 檢查是否已按讚
        $stmt = $pdo->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->execute([$commentId, $username]);
        $existingLike = $stmt->fetch();

        if ($existingLike) {
            // 取消按讚
            $stmt = $pdo->prepare("DELETE FROM comment_likes WHERE id = ?");
            $stmt->execute([$existingLike['id']]);
            $stmt = $pdo->prepare("UPDATE video_comments SET likes = likes - 1 WHERE id = ?");
            $stmt->execute([$commentId]);
        } else {
            // 按讚
            $stmt = $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
            $stmt->execute([$commentId, $username]);
            $stmt = $pdo->prepare("UPDATE video_comments SET likes = likes + 1 WHERE id = ?");
            $stmt->execute([$commentId]);
        }

        // 獲取最新的按讚狀態
        $stmt = $pdo->prepare("SELECT likes FROM video_comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'likes' => $comment['likes'],
            'is_liked' => !$existingLike
        ]);

    } elseif ($action === 'get') {
        $videoId = (int)($_GET['video_id'] ?? 0);

        if (!$videoId) {
            echo json_encode(['success' => false, 'message' => '請提供影片ID']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.video_id,
                c.parent_id,
                c.username,
                c.content,
                c.likes,
                c.created_at,
                CASE WHEN cl.user_id IS NOT NULL THEN 1 ELSE 0 END as is_liked
            FROM video_comments c
            LEFT JOIN comment_likes cl ON c.id = cl.comment_id AND cl.user_id = ?
            WHERE c.video_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$username, $videoId]);
        $comments = $stmt->fetchAll();

        // 整理評論結構（主評論和回覆）
        $mainComments = [];
        $replies = [];

        foreach ($comments as $comment) {
            if ($comment['parent_id'] === null) {
                $mainComments[] = $comment;
            } else {
                if (!isset($replies[$comment['parent_id']])) {
                    $replies[$comment['parent_id']] = [];
                }
                $replies[$comment['parent_id']][] = $comment;
            }
        }

        echo json_encode([
            'success' => true,
            'comments' => $mainComments,
            'replies' => $replies
        ]);

    } elseif ($action === 'report_comment') {
        $commentId  = (int)($jsonBody['comment_id'] ?? 0);
        $reason     = trim($jsonBody['reason']      ?? '');
        $description = trim($jsonBody['description'] ?? '');

        if (!$commentId || !$reason || mb_strlen($description) < 5) {
            echo json_encode(['success' => false, 'message' => '請完整填寫檢舉內容']);
            exit;
        }

        // 建立 comment_reports 表（首次使用時）
        $pdo->exec("CREATE TABLE IF NOT EXISTS comment_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            reported_by VARCHAR(100) NOT NULL,
            reason VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'pending',
            FOREIGN KEY (comment_id) REFERENCES video_comments(id) ON DELETE CASCADE
        )");

        // 防重複：24小時內只能檢舉同一則留言一次
        $dup = $pdo->prepare("SELECT id FROM comment_reports WHERE comment_id = ? AND reported_by = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $dup->execute([$commentId, $username]);
        if ($dup->fetch()) {
            echo json_encode(['success' => false, 'message' => '您已在24小時內檢舉過此留言']);
            exit;
        }

        $pdo->prepare("INSERT INTO comment_reports (comment_id, reported_by, reason, description) VALUES (?, ?, ?, ?)")
            ->execute([$commentId, $username, $reason, $description]);

        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['success' => false, 'message' => '不支援的操作']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '資料庫錯誤：' . $e->getMessage()]);
}
?>
