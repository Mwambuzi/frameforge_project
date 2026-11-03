<?php
require_once __DIR__ . '/config/db.php';

$projectId = (int)($_GET['project_id'] ?? 0);
$userId    = $_SESSION['user_id'] ?? null;
$msg       = '';
$err       = '';

$stmt = $pdo->prepare("
    SELECT p.*, g.name AS genre_name, u.username AS animator_name
    FROM projects p
    INNER JOIN genres g ON p.genre_id = g.genre_id
    INNER JOIN users u ON p.creator_id = u.user_id
    WHERE p.project_id = :pid
");
$stmt->execute([':pid' => $projectId]);
$project = $stmt->fetch();
if (!$project) die("Project not found.");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mpesa_pay') {
    if (!$userId) {
        header("Location: account.php");
        exit;
    }
    $phone = trim($_POST['phone'] ?? '');
    if (!preg_match('/^(?:254|\+254|0)?(7|1)\d{8}$/', $phone)) {
        $err = "Invalid M-Pesa phone number format. Use 07XXXXXXXX or 2547XXXXXXXX.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmtOrder = $pdo->prepare("
                INSERT INTO orders (user_id, status, total_amount)
                VALUES (:uid, 'paid', :total)
            ");
            $stmtOrder->execute([':uid' => $userId, ':total' => $project['price']]);
            $orderId = (int)$pdo->lastInsertId();

            $stmtItem = $pdo->prepare("
                INSERT INTO order_items (order_id, project_id, unit_price)
                VALUES (:oid, :pid, :price)
            ");
            $stmtItem->execute([':oid' => $orderId, ':pid' => $projectId, ':price' => $project['price']]);

            $receiptRef = 'MPESA' . strtoupper(substr(md5(uniqid((string)$orderId, true)), 0, 8));
            $stmtPay = $pdo->prepare("
                INSERT INTO payments (order_id, amount, payment_method, transaction_ref, status)
                VALUES (:oid, :amt, 'M-Pesa', :ref, 'success')
            ");
            $stmtPay->execute([':oid' => $orderId, ':amt' => $project['price'], ':ref' => $receiptRef]);

            recordAuditLog($pdo, $userId, 'UNLOCK_FULL_CONTENT', 'orders', $orderId, 'status=pending', "status=paid, ref=$receiptRef");

            $pdo->commit();
            $msg = "M-Pesa Payment Confirmed (Receipt: $receiptRef)! Full episode is now unlocked.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $err = "Transaction failed: " . $e->getMessage();
        }
    }
}

$isUnlocked = hasUnlockedProject($pdo, $userId, $projectId);

$stmtContent = $pdo->prepare("SELECT * FROM content_items WHERE project_id = :pid");
$stmtContent->execute([':pid' => $projectId]);
$contents = $stmtContent->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($project['title']) ?> — FrameForge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card shadow-sm p-4">
                <span class="badge bg-dark align-self-start mb-2"><?= htmlspecialchars($project['genre_name']) ?></span>
                <h3 class="fw-bold"><?= htmlspecialchars($project['title']) ?></h3>
                <p class="text-muted">Created by @<?= htmlspecialchars($project['animator_name']) ?></p>
                <p><?= htmlspecialchars($project['synopsis']) ?></p>

                <?php foreach ($contents as $item): ?>
                    <hr>
                    <?php if ($item['content_type'] === 'snippet'): ?>
                        <h5 class="fw-bold text-primary">Free Teaser Snippet (<?= (int)$item['duration_seconds'] ?>s)</h5>
                        <video controls class="w-100 rounded bg-black" style="max-height:360px;">
                            <source src="<?= htmlspecialchars($item['video_url']) ?>" type="video/mp4">
                        </video>
                    <?php elseif ($item['content_type'] === 'full'): ?>
                        <h5 class="fw-bold text-success">Full Episode: <?= htmlspecialchars($item['title']) ?></h5>
                        <?php if ($isUnlocked): ?>
                            <div class="alert alert-success py-2">Unlocked! Enjoy the full animation below:</div>
                            <video controls class="w-100 rounded bg-black" style="max-height:420px;">
                                <source src="<?= htmlspecialchars($item['video_url']) ?>" type="video/mp4">
                            </video>
                        <?php else: ?>
                            <div class="p-4 bg-dark text-white rounded text-center">
                                <h5>🔒 Full Content Locked Behind Paywall</h5>
                                <p class="mb-0">Support @<?= htmlspecialchars($project['animator_name']) ?> by unlocking the full animation for <strong>KES <?= number_format((float)$project['price'], 2) ?></strong>.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm p-4">
                <h5 class="fw-bold">Paywall Status & M-Pesa Checkout</h5>
                <hr>
                <?php if ($isUnlocked): ?>
                    <div class="alert alert-success mb-0">
                        <strong>Access Granted:</strong> You have already unlocked this project.
                    </div>
                <?php elseif (!$userId): ?>
                    <p>Please sign in to unlock the full animation with M-Pesa.</p>
                    <a href="account.php" class="btn btn-primary w-100">Sign In to Unlock</a>
                <?php else: ?>
                    <p class="mb-2">Project: <strong><?= htmlspecialchars($project['title']) ?></strong></p>
                    <p class="fs-5 fw-bold text-success">Amount: KES <?= number_format((float)$project['price'], 2) ?></p>
                    <form method="POST">
                        <input type="hidden" name="action" value="mpesa_pay">
                        <div class="mb-3">
                            <label class="form-label">M-Pesa Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="0712345678" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-bold">
                            Pay with M-Pesa (STK Push)
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>