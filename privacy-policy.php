<?php
$pageTitle = "Privacy Policy - Vdownloader";
$pageDesc = "Privacy Policy for Vdownloader. Learn how we handle your data.";
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($pageTitle) ?>
    </title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, rgba(85, 37, 134, 1) 0%, rgba(181, 137, 214, 1) 100%);
            padding: 100px 0 50px 0;
            color: white;
            text-align: center;
        }
    </style>
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
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-bold" href="#" id="platformDropdown"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false">Platforms</a>
                        <ul class="dropdown-menu" aria-labelledby="platformDropdown">
                            <li><a class="dropdown-item" href="instagram-video-downloader.php">Instagram</a></li>
                            <li><a class="dropdown-item" href="facebook-video-downloader.php">Facebook</a></li>
                            <li><a class="dropdown-item" href="tiktok-video-downloader.php">TikTok</a></li>
                            <li><a class="dropdown-item" href="twitter-video-downloader.php">Twitter / X</a></li>
                            <li><a class="dropdown-item" href="pinterest-video-downloader.php">Pinterest</a></li>
                            <li><a class="dropdown-item" href="youtube-video-downloader.php">YouTube</a></li>
                            <li><a class="dropdown-item" href="snapchat-video-downloader.php">Snapchat</a></li>
                        </ul>
                    </li>
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

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="display-5 fw-bold mb-3">Privacy Policy</h1>
            <p class="lead">Last Updated:
                <?php echo date('F d, Y'); ?>
            </p>
        </div>
    </section>

    <!-- Content -->
    <section class="container mt-5 mb-5 pb-5">
        <div class="row justify-content-center">
            <div class="col-md-10 bg-light rounded-4 p-5 shadow-sm"
                style="background: var(--card-bg) !important; color: var(--text-color);">
                <h4>1. Introduction</h4>
                <p>Welcome to Vdownloader. We are committed to protecting your personal information and your right to
                    privacy. If you have any questions or concerns about our policy, or our practices with regards to
                    your personal information, please contact us.</p>

                <h4 class="mt-4">2. Information We Collect</h4>
                <p>We do not collect any personal data when you use our service to download videos. The only information
                    we may log is your IP address and user-agent string for abuse prevention and statistical analysis
                    purposes.</p>

                <h4 class="mt-4">3. Log Files</h4>
                <p>Vdownloader follows a standard procedure of using log files. These files log visitors when they visit
                    websites. The information collected by log files include internet protocol (IP) addresses, browser
                    type, Internet Service Provider (ISP), date and time stamp, referring/exit pages, and possibly the
                    number of clicks.</p>

                <h4 class="mt-4">4. Cookies and Web Beacons</h4>
                <p>Like any other website, Vdownloader uses 'cookies'. These cookies are used to store information
                    including visitors' preferences, and the pages on the website that the visitor accessed or visited.
                    The information is used to optimize the users' experience by customizing our web page content based
                    on visitors' browser type and/or other information.</p>

                <h4 class="mt-4">5. Third Party Privacy Policies</h4>
                <p>Vdownloader's Privacy Policy does not apply to other advertisers or websites. Thus, we are advising
                    you to consult the respective Privacy Policies of these third-party ad servers for more detailed
                    information.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer text-center mt-5">
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