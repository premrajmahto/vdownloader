<?php
$title = $pageTitle ?? "YouTube Video & Shorts Downloader - Vdownloader";
$desc = $pageDesc ?? "Free YouTube Video and Shorts Downloader. Fast, secure, and in HD quality.";
$heroHeader = $heroTitle ?? "Download YouTube Videos & Shorts Instantly";
$heroSub = $heroSubtitle ?? 'Free <span class="badge bg-white text-dark rounded-circle p-1"><i class="fab fa-youtube" style="color: #FF0000;"></i></span> YouTube Video and Shorts Downloader online in high quality.';
$placeholder = $inputPlaceholder ?? "Paste YouTube video or Shorts URL here";
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($desc) ?>">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-transparent position-absolute w-100" style="z-index: 10;">
        <div class="container">
            <a class="navbar-brand text-white fw-bold fs-3" href="index.php"><i class="fas fa-download"></i>
                Vdownloader</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item"><a class="nav-link text-white fw-bold" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link text-white fw-bold" href="youtube-video-downloader.php">YouTube Downloader</a></li>
                    <li class="nav-item"><a class="nav-link text-white fw-bold" href="video-to-audio.php">Convert Video
                            to Audio</a></li>
                    <li class="nav-item"><a class="nav-link text-white fw-bold" href="contact.php">Contact</a></li>
                    <li class="nav-item ms-3">
                        <button id="theme-toggle" class="btn btn-light rounded-circle"><i
                                class="fas fa-moon"></i></button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container mt-5 pt-5">
            <h1 class="display-4 fw-bold mb-3"><?= htmlspecialchars($heroHeader) ?></h1>
            <p class="lead mb-5"><?= $heroSub ?></p>

            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="glass-card">
                        <form id="downloadForm">
                            <div class="input-group hero-input-group mb-3">
                                <input type="url" id="videoUrl" class="form-control hero-input"
                                    placeholder="<?= htmlspecialchars($placeholder) ?>" required>
                                <button class="btn btn-download" type="submit">Download <i
                                        class="fas fa-arrow-right"></i></button>
                            </div>
                        </form>

                        <div id="loader">
                            <div class="spinner"></div>
                            <p class="mt-2 mb-0 fw-bold">Processing URL...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Result Box -->
    <div class="container result-card" id="resultBox">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-5">
                                <img id="resThumbnail" src="" alt="Video Thumbnail" class="result-img">
                            </div>
                            <div class="col-md-7 mt-3 mt-md-0">
                                <h4 id="resTitle" class="fw-bold text-truncate">Video Title</h4>
                                <p class="text-muted mb-1"><i class="fas fa-clock"></i> Duration: <span
                                        id="resDuration">0:00</span></p>
                                <p class="text-muted mb-1"><i class="fas fa-hdd"></i> Size: <span id="resSize">--</span>
                                </p>
                                <p class="text-muted mb-3"><i class="fas fa-globe"></i> Platform: <span id="resPlatform"
                                        class="badge bg-primary">--</span></p>

                                <div id="downloadLinks">
                                    <!-- Dynamic Download Links -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Supported Platforms -->
    <section class="container mt-5 text-center">
        <h3 class="fw-bold mb-4">Supported Platform</h3>
        <div class="platform-icons">
            <i class="fab fa-youtube text-danger fs-1" title="YouTube Videos & Shorts"></i>
        </div>
    </section>

    <!-- How It Works -->
    <section class="container mt-5">
        <div class="row text-center mb-5">
            <div class="col-12">
                <h3 class="fw-bold">How It Works</h3>
            </div>
        </div>
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="mb-3"><i class="fas fa-copy fs-1 text-primary"></i></div>
                    <h5>Step 1</h5>
                    <p class="text-muted">Copy the video link from your favorite social media app.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="mb-3"><i class="fas fa-paste fs-1 text-primary"></i></div>
                    <h5>Step 2</h5>
                    <p class="text-muted">Paste the link into the downloader input field above.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="mb-3"><i class="fas fa-download fs-1 text-primary"></i></div>
                    <h5>Step 3</h5>
                    <p class="text-muted">Click download and choose your preferred quality/format.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits -->
    <section class="container mt-5 mb-5">
        <div class="row align-items-center bg-light rounded-4 p-5 shadow-sm"
            style="background: var(--card-bg) !important;">
            <div class="col-md-6 mb-4 mb-md-0">
                <h3 class="fw-bold mb-4">Why Choose Us?</h3>
                <ul class="list-unstyled">
                    <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> Fast and unlimited downloads
                    </li>
                    <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> No login or registration
                        required</li>
                    <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> 100% Free forever</li>
                    <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> HD quality MP4 & High-quality
                        MP3</li>
                    <li><i class="fas fa-check-circle text-success me-2"></i> Safe and secure</li>
                </ul>
            </div>
            <div class="col-md-6 text-center">
                <i class="fas fa-shield-alt text-primary" style="font-size: 10rem; opacity: 0.8;"></i>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer text-center">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3 mb-md-0 text-md-start">
                    <h5 class="fw-bold"><i class="fas fa-download"></i> Vdownloader</h5>
                    <p class="text-muted small">The best tool to download your favorite social media videos online for
                        free.</p>
                </div>
                <div class="col-md-8 text-md-end">
                    <a href="privacy-policy.php" class="text-decoration-none text-muted me-3">Privacy Policy</a>
                    <a href="terms-of-service.php" class="text-decoration-none text-muted me-3">Terms of Service</a>
                    <a href="contact.php" class="text-decoration-none text-muted me-3">Contact</a>
                    <a href="about.php" class="text-decoration-none text-muted">About</a>
                </div>
            </div>
            <div class="mt-4 pt-3 border-top border-secondary text-muted small d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div>&copy; <?php echo date('Y'); ?> Vdownloader. All Rights Reserved.</div>
                <div>Design &amp; Developed by <span class="fw-semibold">Aventiq Web Solution</span></div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>

</html>