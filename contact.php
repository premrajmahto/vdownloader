<?php
$pageTitle = "Contact Us - Vdownloader";
$pageDesc = "Get in touch with the Vdownloader team for support, business inquiries, or feedback.";
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

        .contact-form .form-control {
            background: transparent;
            color: var(--text-color);
            border: 1px solid rgba(128, 128, 128, 0.4);
            border-radius: 10px;
            padding: 15px;
        }

        .contact-form .form-control:focus {
            box-shadow: none;
            border-color: #8b5cf6;
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
                    <li class="nav-item"><a class="nav-link text-white fw-bold" href="video-to-audio.php">Convert Video to Audio</a></li>
                    <li class="nav-item"><a class="nav-link text-white fw-bold active" href="contact.php">Contact</a>
                    </li>
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
            <h1 class="display-5 fw-bold mb-3">Contact Us</h1>
            <p class="lead">We'd love to hear from you. Send us a message.</p>
        </div>
    </section>

    <!-- Content -->
    <section class="container mt-5 mb-5 pb-5">
        <div class="row justify-content-center">
            <div class="col-md-8 bg-light rounded-4 p-5 shadow-sm"
                style="background: var(--card-bg) !important; color: var(--text-color);">
                <form class="contact-form">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Your Name</label>
                        <input type="text" class="form-control" placeholder="Rohan Sharma" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" class="form-control" placeholder="rohan.sharma@example.com" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Message</label>
                        <textarea class="form-control" rows="5" placeholder="How can we help you?" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold"
                        style="background: linear-gradient(135deg, #a855f7 0%, #8b5cf6 100%);">Send Message <i
                            class="fas fa-paper-plane ms-2"></i></button>
                </form>
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
    <script>
        document.querySelector('.contact-form').addEventListener('submit', function  (e) {
            e.preventDefault();
            alert("Thank you! Your message has been sent successfully.");
            this.reset();
        });
    </script>
</body>

</html>