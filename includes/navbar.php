<?php
// includes/navbar.php
$currentRole = $_SESSION['role_name'] ?? 'guest';
$currentUser = $_SESSION['username'] ?? null;
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold text-warning" href="/frameforge/index.php">
            FrameForge Studio
        </a>
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-outline-light btn-sm" href="/frameforge/index.php">Browse Snippets</a>
            <?php if (in_array($currentRole, ['animator', 'admin', 'super_admin'], true)): ?>
                <a class="btn btn-warning btn-sm fw-semibold" href="/frameforge/studio.php">+ Animator AI Studio</a>
            <?php endif; ?>
            <?php if (in_array($currentRole, ['admin', 'super_admin'], true)): ?>
                <a class="btn btn-info btn-sm fw-semibold" href="/frameforge/admin/index.php">Admin Panel</a>
            <?php endif; ?>
            <?php if ($currentUser): ?>
                <span class="badge bg-secondary ms-2"><?= htmlspecialchars($currentUser) ?> (<?= htmlspecialchars($currentRole) ?>)</span>
                <a class="btn btn-danger btn-sm" href="/frameforge/account.php?action=logout">Logout</a>
            <?php else: ?>
                <a class="btn btn-primary btn-sm" href="/frameforge/account.php">Sign In / Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</nav>