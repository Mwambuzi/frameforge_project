<?php
require_once __DIR__ . '/../config/db.php';
requireRole(['admin', 'super_admin']);

$adminId = (int)$_SESSION['user_id'];
$msg     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve_project') {
        $pid      = (int)$_POST['project_id'];
        $newPrice = (float)$_POST['price'];
        $newState = $_POST['status'] === 'published' ? 'published' : 'archived';

        $old = $pdo->prepare("SELECT status, price FROM projects WHERE project_id = :pid");
        $old->execute([':pid' => $pid]);
        $oldRow = $old->fetch();

        $stmt = $pdo->prepare("UPDATE projects SET status = :st, price = :pr WHERE project_id = :pid");
        $stmt->execute([':st' => $newState, ':pr' => $newPrice, ':pid' => $pid]);

        recordAuditLog(
            $pdo,
            $adminId,
            'ADMIN_PROJECT_MODERATION',
            'projects',
            $pid,
            "status={$oldRow['status']}, price={$oldRow['price']}",
            "status=$newState, price=$newPrice"
        );
        $msg = "Project #$pid updated to '$newState' (KES $newPrice) and logged in audit trail.";
    } elseif ($action === 'toggle_user') {
        $targetUid = (int)$_POST['user_id'];
        $newActive = (int)$_POST['is_active'];
        $pdo->prepare("UPDATE users SET is_active = :act WHERE user_id = :uid")
            ->execute([':act' => $newActive, ':uid' => $targetUid]);
        recordAuditLog($pdo, $adminId, 'USER_STATUS_CHANGE', 'users', $targetUid, null, "is_active=$newActive");
        $msg = "User #$targetUid status updated.";
    }
}

$projects = $pdo->query("
    SELECT p.*, u.username AS creator_name, g.name AS genre_name
    FROM projects p
    INNER JOIN users u ON p.creator_id = u.user_id
    INNER JOIN genres g ON p.genre_id = g.genre_id
    ORDER BY p.created_at DESC
")->fetchAll();

$users = $pdo->query("
    SELECT u.*, r.role_name 
    FROM users u 
    INNER JOIN roles r ON u.role_id = r.role_id 
    ORDER BY u.user_id ASC
")->fetchAll();

$orders = $pdo->query("
    SELECT o.order_id, o.order_date, o.total_amount, o.status,
           u.username, p.title AS project_title, pay.transaction_ref, pay.payment_method
    FROM orders o
    INNER JOIN users u ON o.user_id = u.user_id
    INNER JOIN order_items oi ON o.order_id = oi.order_id
    INNER JOIN projects p ON oi.project_id = p.project_id
    LEFT JOIN payments pay ON o.order_id = pay.order_id
    ORDER BY o.order_date DESC
")->fetchAll();

$logs = $pdo->query("
    SELECT a.*, u.username
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.user_id
    ORDER BY a.created_at DESC LIMIT 15
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel — FrameForge Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="fw-bold mb-0">FrameForge Admin Panel & Reports</h3>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm">Print Financial & Audit Report</button>
    </div>
    <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="card shadow-sm p-3 mb-4">
        <h5 class="fw-bold">1. Project Approvals & Paywall Pricing</h5>
        <table class="table table-sm align-middle">
            <thead><tr><th>ID</th><th>Title</th><th>Animator</th><th>Genre</th><th>Status</th><th>Set Price (KES)</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($projects as $p): ?>
                <tr>
                    <td>#<?= $p['project_id'] ?></td>
                    <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                    <td>@<?= htmlspecialchars($p['creator_name']) ?></td>
                    <td><?= htmlspecialchars($p['genre_name']) ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($p['status']) ?></span></td>
                    <td>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="action" value="approve_project">
                            <input type="hidden" name="project_id" value="<?= $p['project_id'] ?>">
                            <input type="number" step="0.01" name="price" value="<?= $p['price'] ?>" class="form-control form-control-sm" style="width:100px;">
                            <select name="status" class="form-select form-select-sm" style="width:130px;">
                                <option value="published" <?= $p['status']==='published'?'selected':'' ?>>Publish</option>
                                <option value="archived" <?= $p['status']==='archived'?'selected':'' ?>>Unpublish</option>
                            </select>
                            <button class="btn btn-success btn-sm">Save</button>
                        </form>
                    </td>
                    <td><a href="../watch.php?project_id=<?= $p['project_id'] ?>" class="btn btn-outline-dark btn-sm">Preview</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card shadow-sm p-3 mb-4">
        <h5 class="fw-bold">2. Paywall Orders & M-Pesa Revenue Report (Generated: <?= date('Y-m-d H:i') ?>)</h5>
        <table class="table table-sm">
            <thead><tr><th>Order ID</th><th>Viewer</th><th>Unlocked Project</th><th>Method</th><th>M-Pesa Receipt</th><th>Amount</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?= $o['order_id'] ?></td>
                    <td>@<?= htmlspecialchars($o['username']) ?></td>
                    <td><?= htmlspecialchars($o['project_title']) ?></td>
                    <td><?= htmlspecialchars($o['payment_method'] ?? 'M-Pesa') ?></td>
                    <td><code><?= htmlspecialchars($o['transaction_ref'] ?? 'N/A') ?></code></td>
                    <td class="fw-bold text-success">KES <?= number_format((float)$o['total_amount'], 2) ?></td>
                    <td><?= $o['order_date'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card shadow-sm p-3 mb-4">
        <h5 class="fw-bold">3. User Management</h5>
        <table class="table table-sm align-middle">
            <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Active</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['user_id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge bg-dark"><?= htmlspecialchars($u['role_name']) ?></span></td>
                    <td><?= $u['is_active'] ? 'Active' : 'Deactivated' ?></td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="toggle_user">
                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                            <input type="hidden" name="is_active" value="<?= $u['is_active'] ? 0 : 1 ?>">
                            <button class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card shadow-sm p-3">
        <h5 class="fw-bold">4. Security Audit Trail (`audit_logs`)</h5>
        <table class="table table-sm small">
            <thead><tr><th>Log ID</th><th>Actor</th><th>Action</th><th>Table</th><th>Record ID</th><th>Old Value</th><th>New Value</th><th>Timestamp</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td><?= $l['log_id'] ?></td>
                    <td><?= htmlspecialchars($l['username'] ?? 'System') ?></td>
                    <td><code><?= htmlspecialchars($l['action']) ?></code></td>
                    <td><?= htmlspecialchars($l['table_name']) ?></td>
                    <td>#<?= $l['record_id'] ?></td>
                    <td class="text-muted"><?= htmlspecialchars($l['old_value'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($l['new_value'] ?? '-') ?></td>
                    <td><?= $l['created_at'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>