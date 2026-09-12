<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';

if (!$pdo) {
    die("Database Connection Error: " . htmlspecialchars($dbError ?? 'MySQL server is offline.'));
}

// Action handler for delete
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM downloads WHERE id = ?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: dashboard.php?msg=deleted");
    exit;
}

// Fetch stats
$totalDownloads = $pdo->query("SELECT COUNT(*) FROM downloads")->fetchColumn();
$todayDownloads = $pdo->query("SELECT COUNT(*) FROM downloads WHERE DATE(download_date) = CURDATE()")->fetchColumn();
$recentDownloads = $pdo->query("SELECT * FROM downloads ORDER BY download_date DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

$platformStats = $pdo->query("SELECT platform, COUNT(*) as count FROM downloads GROUP BY platform ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Vdownloader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-card { border-radius: 10px; padding: 20px; color: white; }
        .bg-primary-grad { background: linear-gradient(135deg, #6a11cb, #2575fc); }
        .bg-success-grad { background: linear-gradient(135deg, #11998e, #38ef7d); }
    </style>
</head>
<body class="bg-light">
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Vdownloader Admin</a>
            <div class="d-flex">
                <a href="../index.php" class="btn btn-outline-light me-2 btn-sm" target="_blank">View Site</a>
                <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success">Record deleted successfully.</div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="stat-card bg-primary-grad shadow">
                    <h3><?= $totalDownloads ?></h3>
                    <p class="mb-0">Total Downloads</p>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="stat-card bg-success-grad shadow">
                    <h3><?= $todayDownloads ?></h3>
                    <p class="mb-0">Downloads Today</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold">Recent Downloads</div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Platform</th>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentDownloads as $dl): ?>
                                    <tr>
                                        <td><?= $dl['id'] ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($dl['platform']) ?></span></td>
                                        <td class="text-truncate" style="max-width: 200px;"><?= htmlspecialchars($dl['video_title']) ?></td>
                                        <td><?= $dl['download_date'] ?></td>
                                        <td>
                                            <a href="dashboard.php?delete_id=<?= $dl['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentDownloads)): ?>
                                    <tr><td colspan="5" class="text-center py-3">No downloads yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Platform Stats</div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($platformStats as $ps): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= htmlspecialchars($ps['platform'] ?: 'Unknown') ?>
                                    <span class="badge bg-primary rounded-pill"><?= $ps['count'] ?></span>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($platformStats)): ?>
                                <li class="list-group-item text-center">No data available.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
