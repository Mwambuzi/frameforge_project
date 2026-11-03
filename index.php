<?php
require_once __DIR__ . '/config/db.php';

$search  = trim($_GET['q'] ?? '');
$genreId = (int)($_GET['genre_id'] ?? 0);

$genres = $pdo->query("SELECT * FROM genres ORDER BY name")->fetchAll();

$sql = "
    SELECT p.*, g.name AS genre_name, u.username AS animator_name,
           c.video_url AS snippet_url, c.duration_seconds
    FROM projects p
    INNER JOIN genres g ON p.genre_id = g.genre_id
    INNER JOIN users u ON p.creator_id = u.user_id
    LEFT JOIN content_items c ON p.project_id = c.project_id AND c.content_type = 'snippet'
    WHERE p.status = 'published'
";
$params = [];

if ($search !== '') {
    $sql .= " AND (p.title LIKE :search OR p.synopsis LIKE :search OR u.username LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($genreId > 0) {
    $sql .= " AND p.genre_id = :gid";
    $params[':gid'] = $genreId;
}
$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FrameForge — Discover Independent Animations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="container">
    <div class="row mb-4 align-items-center">
        <div class="col-md-7">
            <h2 class="fw-bold mb-1">Explore Free Animation Snippets</h2>
            <p class="text-muted mb-0">Watch free teasers from independent animators and unlock full episodes via M-Pesa.</p>
        </div>
        <div class="col-md-5">
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="q" class="form-control" placeholder="Search title, synopsis, or animator..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-dark" type="submit">Search</button>
            </form>
        </div>
    </div>

    <div class="mb-4 d-flex flex-wrap gap-2">
        <a href="index.php" class="btn btn-sm <?= $genreId === 0 ? 'btn-dark' : 'btn-outline-dark' ?>">All Genres</a>
        <?php foreach ($genres as $g): ?>
            <a href="index.php?genre_id=<?= $g['genre_id'] ?>" 
               class="btn btn-sm <?= $genreId === (int)$g['genre_id'] ? 'btn-dark' : 'btn-outline-dark' ?>">
                <?= htmlspecialchars($g['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <?php foreach ($projects as $proj): ?>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <span class="badge bg-secondary align-self-start mb-2"><?= htmlspecialchars($proj['genre_name']) ?></span>
                        <h5 class="card-title fw-bold"><?= htmlspecialchars($proj['title']) ?></h5>
                        <p class="text-muted small mb-2">By @<?= htmlspecialchars($proj['animator_name']) ?></p>
                        <p class="card-text flex-grow-1"><?= htmlspecialchars($proj['synopsis']) ?></p>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                            <span class="fw-bold text-success">Full Unlock: KES <?= number_format((float)$proj['price'], 2) ?></span>
                            <a href="watch.php?project_id=<?= $proj['project_id'] ?>" class="btn btn-warning btn-sm fw-semibold">
                                Watch Free Snippet
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<footer class="text-center text-muted py-4 mt-5 border-top">
    FrameForge Studio &copy; 2026 | Help & FAQ: Select a snippet to preview for free or unlock full content with M-Pesa.
</footer>
</body>
</html>