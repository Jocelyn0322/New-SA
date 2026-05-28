<?php
/**
 * PDO session handler for Railway multi-instance deployment.
 * Auto-prepended via php.ini (auto_prepend_file) — runs before every PHP request.
 * Stores sessions in Supabase PostgreSQL so all Railway instances share the same session state.
 */

if (defined('SESSION_HANDLER_REGISTERED') || session_status() !== PHP_SESSION_NONE) {
    return;
}
define('SESSION_HANDLER_REGISTERED', true);

try {
    $_sessionPdo = new PDO(
        'pgsql:host=aws-1-ap-southeast-1.pooler.supabase.com;port=5432;dbname=postgres;sslmode=disable',
        'postgres.gykwxrymhgywarpyqxcr',
        '2hq5hnoEYPU2qp38',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $_sessionPdo->exec("SET search_path TO public");
    $_sessionPdo->exec("
        CREATE TABLE IF NOT EXISTS php_sessions (
            id VARCHAR(128) PRIMARY KEY,
            data TEXT NOT NULL DEFAULT '',
            last_activity INTEGER NOT NULL
        )
    ");

    class PdoSessionHandler implements SessionHandlerInterface {
        private PDO $pdo;
        public function __construct(PDO $pdo) { $this->pdo = $pdo; }
        public function open(string $path, string $name): bool { return true; }
        public function close(): bool { return true; }
        public function read(string $id): string|false {
            $lifetime = (int)ini_get('session.gc_maxlifetime') ?: 1440;
            $stmt = $this->pdo->prepare(
                "SELECT data FROM php_sessions WHERE id = ? AND last_activity > ?"
            );
            $stmt->execute([$id, time() - $lifetime]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['data'] : '';
        }
        public function write(string $id, string $data): bool {
            $stmt = $this->pdo->prepare("
                INSERT INTO php_sessions (id, data, last_activity) VALUES (?, ?, ?)
                ON CONFLICT (id) DO UPDATE
                    SET data = EXCLUDED.data, last_activity = EXCLUDED.last_activity
            ");
            return $stmt->execute([$id, $data, time()]);
        }
        public function destroy(string $id): bool {
            $stmt = $this->pdo->prepare("DELETE FROM php_sessions WHERE id = ?");
            return $stmt->execute([$id]);
        }
        public function gc(int $max_lifetime): int|false {
            $stmt = $this->pdo->prepare("DELETE FROM php_sessions WHERE last_activity < ?");
            $stmt->execute([time() - $max_lifetime]);
            return $stmt->rowCount();
        }
    }

    session_set_save_handler(new PdoSessionHandler($_sessionPdo), true);

} catch (Throwable $_sessionErr) {
    // DB unavailable — fall back to default file-based sessions silently
}
