<?php
require_once __DIR__ . '/config/db.php';
requireRole(['animator', 'admin', 'super_admin']);

$userId = (int)$_SESSION['user_id'];
$msg    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_project') {
        $stmt = $pdo->prepare("
            INSERT INTO projects (creator_id, genre_id, title, synopsis, status, price)
            VALUES (:cid, :gid, :title, :syn, 'in_production', :price)
        ");
        $stmt->execute([
            ':cid'   => $userId,
            ':gid'   => (int)$_POST['genre_id'],
            ':title' => trim($_POST['title']),
            ':syn'   => trim($_POST['synopsis']),
            ':price' => (float)$_POST['price']
        ]);
        $pid = (int)$pdo->lastInsertId();
        recordAuditLog($pdo, $userId, 'CREATE_PROJECT', 'projects', $pid, null, $_POST['title']);
        $msg = "Project created! Add storyboard panels below.";
    } elseif ($action === 'generate_ai_frames') {
        $projectId = (int)$_POST['project_id'];
        $seqNo     = (int)$_POST['sequence_no'];
        $sceneDesc = trim($_POST['scene_description']);
        $prompt    = trim($_POST['prompt']);

        $pdo->beginTransaction();
        $stmtPanel = $pdo->prepare("
            INSERT INTO storyboard_panels (project_id, sequence_no, scene_description, sketch_url)
            VALUES (:pid, :seq, :desc, 'uploads/sketches/default_sketch.png')
        ");
        $stmtPanel->execute([':pid' => $projectId, ':seq' => $seqNo, ':desc' => $sceneDesc]);
        $panelId = (int)$pdo->lastInsertId();

        $stmtJob = $pdo->prepare("
            INSERT INTO ai_jobs (panel_id, requested_by, prompt, status, completed_at)
            VALUES (:panel_id, :uid, :prompt, 'completed', NOW())
        ");
        $stmtJob->execute([':panel_id' => $panelId, ':uid' => $userId, ':prompt' => $prompt]);
        $jobId = (int)$pdo->lastInsertId();

        $stmtFrame = $pdo->prepare("INSERT INTO frames (job_id, image_url, is_approved) VALUES (:jid, :url, 0)");
        $stmtFrame->execute([':jid' => $jobId, ':url' => "uploads/frames/job{$jobId}_frame_A.png"]);
        $stmtFrame->execute([':jid' => $jobId, ':url' => "uploads/frames/job{$jobId}_frame_B.png"]);

        recordAuditLog($pdo, $userId, 'AI_FRAME_GENERATION', 'ai_jobs', $jobId, 'status=queued', 'status=completed (2 frames)');
        $pdo->commit();
        $msg = "AI Job #$jobId completed! Generated 2 interpolated frames from your storyboard panel.";
    } elseif ($action === 'approve_frame') {
        $frameId = (int)$_POST['frame_id'];
        $pdo->prepare("UPDATE frames SET is_approved = 1 WHERE frame_id = :fid")->execute([':fid' => $frameId]);
        $msg = "Frame #$frameId marked as Approved.";
    } elseif ($action === 'submit_review') {
        $projectId = (int)$_POST['project_id'];
        $pdo->prepare("UPDATE projects SET status = 'pending_review' WHERE project_id = :pid AND creator_id = :uid")
            ->execute([':pid' => $projectId, ':uid' => $userId]);
        $msg = "Project #$projectId submitted to Admin for publishing approval!";
    }
}

$genres   = $pdo->query("SELECT * FROM genres")->fetchAll();
$projects = $pdo->prepare("SELECT * FROM projects WHERE creator_id = :uid ORDER BY created_at DESC");
$projects->execute([':uid' => $userId]);
$myProjects = $projects->fetchAll();

$stmtFrames = $pdo->prepare("
    SELECT f.*, j.prompt, sp.scene_description, p.title AS project_title
    FROM frames f
    INNER JOIN ai_jobs j ON f.job_id = j.job_id
    INNER JOIN storyboard_panels sp ON j.panel_id = sp.panel_id
    INNER JOIN projects p ON sp.project_id = p.project_id
    WHERE j.requested_by = :uid
    ORDER BY f.created_at DESC
");
$stmtFrames->execute([':uid' => $userId]);
$generatedFrames = $stmtFrames->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Animator AI Studio — FrameForge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <h3 class="fw-bold mb-3">Animator Studio: Storyboard-to-Frames AI Workspace</h3>
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card shadow-sm p-3 mb-4">
                <h5 class="fw-bold">1. Create Animation Project</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="create_project">
                    <div class="mb-2">
                        <label class="form-label small">Project Title</label>
                        <input type="text" name="title" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Genre</label>
                        <select name="genre_id" class="form-select form-select-sm">
                            <?php foreach ($genres as $g): ?>
                                <option value="<?= $g['genre_id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Proposed Paywall Price (KES)</label>
                        <input type="number" step="0.01" name="price" class="form-control form-control-sm" value="150.00" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Synopsis</label>
                        <textarea name="synopsis" class="form-control form-control-sm" rows="2" required></textarea>
                    </div>
                    <button class="btn btn-dark btn-sm w-100">Create Project</button>
                </form>
            </div>

            <div class="card shadow-sm p-3">
                <h5 class="fw-bold">2. Upload Storyboard Panel & Generate AI Frames</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="generate_ai_frames">
                    <div class="mb-2">
                        <label class="form-label small">Select Project</label>
                        <select name="project_id" class="form-select form-select-sm" required>
                            <?php foreach ($myProjects as $mp): ?>
                                <option value="<?= $mp['project_id'] ?>"><?= htmlspecialchars($mp['title']) ?> (<?= $mp['status'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Panel Sequence #</label>
                        <input type="number" name="sequence_no" class="form-control form-control-sm" value="1" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Scene Description</label>
                        <input type="text" name="scene_description" class="form-control form-control-sm" placeholder="e.g. Hero leaps across rooftop" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">AI Motion & Style Prompt</label>
                        <input type="text" name="prompt" class="form-control form-control-sm" placeholder="e.g. 24fps anime interpolation, dramatic lighting" required>
                    </div>
                    <button class="btn btn-warning btn-sm fw-bold w-100">Generate Frames with AI</button>
                </form>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm p-3 mb-4">
                <h5 class="fw-bold">3. My Projects & Review Submission</h5>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Title</th><th>Status</th><th>Price</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($myProjects as $mp): ?>
                        <tr>
                            <td><?= htmlspecialchars($mp['title']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($mp['status']) ?></span></td>
                            <td>KES <?= number_format((float)$mp['price'], 2) ?></td>
                            <td>
                                <?php if ($mp['status'] !== 'published'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="submit_review">
                                        <input type="hidden" name="project_id" value="<?= $mp['project_id'] ?>">
                                        <button class="btn btn-outline-primary btn-sm">Submit for Admin Review</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card shadow-sm p-3">
                <h5 class="fw-bold">4. AI Generated Frames (Approve / Regenerate)</h5>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Project / Scene</th><th>AI Prompt</th><th>Frame Output</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($generatedFrames as $gf): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($gf['project_title']) ?></strong><br><small><?= htmlspecialchars($gf['scene_description']) ?></small></td>
                            <td class="small"><?= htmlspecialchars($gf['prompt']) ?></td>
                            <td><code><?= htmlspecialchars($gf['image_url']) ?></code></td>
                            <td><?= $gf['is_approved'] ? '<span class="badge bg-success">Approved</span>' : '<span class="badge bg-warning text-dark">Pending</span>' ?></td>
                            <td>
                                <?php if (!$gf['is_approved']): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="approve_frame">
                                        <input type="hidden" name="frame_id" value="<?= $gf['frame_id'] ?>">
                                        <button class="btn btn-success btn-sm">Approve</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>