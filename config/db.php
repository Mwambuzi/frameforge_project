<?php
// config/db.php — Central Database Connection & Security Helpers
session_start();

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'frameforge_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // Set your MySQL password if applicable

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Database Connection Failed: " . htmlspecialchars($e->getMessage()));
}

function recordAuditLog(PDO $pdo, ?int $userId, string $action, string $tableName, int $recordId, ?string $oldValue, ?string $newValue): void {
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value)
        VALUES (:uid, :action, :tname, :rid, :oldv, :newv)
    ");
    $stmt->execute([
        ':uid'    => $userId,
        ':action' => $action,
        ':tname'  => $tableName,
        ':rid'    => $recordId,
        ':oldv'   => $oldValue,
        ':newv'   => $newValue
    ]);
}

function hasUnlockedProject(PDO $pdo, ?int $userId, int $projectId): bool {
    if (!$userId) return false;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM orders o
        INNER JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.user_id = :uid 
          AND oi.project_id = :pid 
          AND o.status = 'paid'
    ");
    $stmt->execute([':uid' => $userId, ':pid' => $projectId]);
    return (int)$stmt->fetchColumn() > 0;
}

function requireRole(array $allowedRoles): void {
    if (empty($_SESSION['user_id']) || !in_array($_SESSION['role_name'], $allowedRoles, true)) {
        header("Location: /frameforge/account.php?error=unauthorized");
        exit;
    }
}
?>