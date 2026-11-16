<?php
// includes/navbar.php

// Work out the project's URL prefix automatically (e.g. "/frameforge", or "" when
// the project folder IS the web root, as with `php -S`), so links never depend
// on what the folder is called. To force a value, define('BASE_URL', '/yourfolder')
// in config/db.php before this file is included.
if (!defined('BASE_URL')) {
    $rawDocRoot  = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $docRoot     = $rawDocRoot !== '' ? str_replace('\\', '/', (string)realpath($rawDocRoot)) : '';
    $projectRoot = str_replace('\\', '/', (string)realpath(__DIR__ . '/..'));
    $base        = '';
    if ($docRoot !== '' && stripos($projectRoot . '/', $docRoot . '/') === 0) {
        $base = substr($projectRoot, strlen($docRoot));
    }
    define('BASE_URL', rtrim($base, '/'));
}

$currentRole = strtolower((string)($_SESSION['role_name'] ?? 'guest'));
$currentUser = $_SESSION['username'] ?? null;
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold text-warning" href="<?= BASE_URL ?>/index.php">
            FrameForge Studio
        </a>
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-outline-light btn-sm" href="<?= BASE_URL ?>/index.php">Browse Snippets</a>
            <?php if (in_array($currentRole, ['animator', 'admin', 'super_admin'], true)): ?>
                <a class="btn btn-warning btn-sm fw-semibold" href="<?= BASE_URL ?>/studio.php">+ Animator AI Studio</a>
            <?php endif; ?>
            <?php if (in_array($currentRole, ['admin', 'super_admin'], true)): ?>
                <a class="btn btn-info btn-sm fw-semibold" href="<?= BASE_URL ?>/admin/index.php">Admin Panel</a>
            <?php endif; ?>
            <?php if ($currentUser): ?>
                <span class="badge bg-secondary ms-2"><?= htmlspecialchars($currentUser) ?> (<?= htmlspecialchars($currentRole) ?>)</span>
                <a class="btn btn-danger btn-sm" href="<?= BASE_URL ?>/account.php?action=logout">Logout</a>
            <?php else: ?>
                <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/account.php">Sign In / Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</nav>