<?php
require_once __DIR__ . '/config/db.php';

if (($_GET['action'] ?? '') === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'signin') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("
            SELECT u.*, r.role_name 
            FROM users u 
            INNER JOIN roles r ON u.role_id = r.role_id 
            WHERE u.username = :uname AND u.is_active = 1
        ");
        $stmt->execute([':uname' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']   = (int)$user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role_name'] = $user['role_name'];
            recordAuditLog($pdo, (int)$user['user_id'], 'USER_LOGIN', 'users', (int)$user['user_id'], null, 'Login success');
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid username/password or deactivated account.";
        }
    } elseif ($formType === 'signup') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId   = in_array((int)($_POST['role_id'] ?? 1), [1, 2], true) ? (int)$_POST['role_id'] : 1;

        if (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (role_id, username, email, password_hash)
                    VALUES (:rid, :uname, :email, :phash)
                ");
                $stmt->execute([
                    ':rid'   => $roleId,
                    ':uname' => $username,
                    ':email' => $email,
                    ':phash' => $hash
                ]);
                $newId = (int)$pdo->lastInsertId();
                recordAuditLog($pdo, $newId, 'USER_REGISTER', 'users', $newId, null, "Role ID: $roleId");
                $message = "Account created! You can now sign in.";
            } catch (PDOException $e) {
                $error = "Username or Email already exists.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FrameForge — Sign In / Sign Up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm p-4 h-100">
                <h4 class="fw-bold mb-3">Sign In</h4>
                <form method="POST">
                    <input type="hidden" name="form_type" value="signin">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-dark w-100">Confirm Sign In</button>
                </form>
                <p class="text-muted small mt-3 mb-0">
                    Demo Accounts (Password: <code>Password123!</code>):<br>
                    Admin: <code>lincon_admin</code> | Animator: <code>carldon_anim</code> | Viewer: <code>benson_viewer</code>
                </p>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm p-4 h-100">
                <h4 class="fw-bold mb-3">Sign Up</h4>
                <form method="POST">
                    <input type="hidden" name="form_type" value="signup">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Role</label>
                        <select name="role_id" class="form-select">
                            <option value="1">Viewer (Watch Snippets & Unlock Films)</option>
                            <option value="2">Animator (Upload Storyboards & Generate AI Frames)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-warning fw-bold w-100">Confirm Sign Up</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>