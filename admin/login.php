<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$pdo) {
        $error = "Database connection failed. Please ensure MySQL is running.";
    } elseif (!empty($email) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_email'] = $user['email'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Please enter both email and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Login - Vdownloader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #1e0533, #110a1f);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            position: relative;
        }

        /* 3D Glowing Background Orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            animation: float 10s infinite ease-in-out alternate;
        }
        
        .orb-1 { width: 400px; height: 400px; background: #8b5cf6; top: -100px; left: -100px; animation-delay: 0s; }
        .orb-2 { width: 500px; height: 500px; background: #ec4899; bottom: -150px; right: -100px; animation-delay: -5s; }
        .orb-3 { width: 300px; height: 300px; background: #06b6d4; bottom: 20%; left: 20%; animation-delay: -2s; }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(50px, 50px) scale(1.1); }
        }

        /* 3D Glassmorphism Login Card */
        .login-card {
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3), inset 0 2px 2px rgba(255, 255, 255, 0.2);
            width: 90%;
            max-width: 420px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.05); /* highly transparent glass */
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 10;
            color: white;
            /* Default 3D perspective slant */
            transform: perspective(1000px) rotateX(1deg) rotateY(-1deg);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }

        .login-card:hover {
            transform: perspective(1000px) rotateX(0deg) rotateY(0deg);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4), inset 0 2px 2px rgba(255, 255, 255, 0.3);
        }

        .login-card h3 { color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        .login-card label { color: rgba(255,255,255,0.8); }

        .login-card .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            backdrop-filter: blur(10px);
        }
        
        .login-card .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #8b5cf6;
            box-shadow: 0 0 0 0.25rem rgba(139, 92, 246, 0.25);
            color: white;
        }
        
        .login-card .btn-primary {
            background: linear-gradient(135deg, #a855f7, #6366f1);
            border: none;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .login-card .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.6);
        }
        
        .login-card a.text-primary { color: #c4b5fd !important; transition: color 0.2s; }
        .login-card a.text-primary:hover { color: #ddd6fe !important; }
        .login-card a.text-muted { color: #d1d5db !important; transition: color 0.2s; }
        .login-card a.text-muted:hover { color: white !important; }
        .login-card .border-top { border-color: rgba(255,255,255,0.1) !important; }
    </style>
</head>

<body>
    <!-- 3D Background Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="login-card">
        <h3 class="text-center fw-bold mb-4">Admin Login</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" required value="admin@vdownloader.com">
            </div>
            <div class="mb-2">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required value="admin123">
            </div>
            <div class="mb-4 text-end">
                <a href="forgot-password.php" class="text-decoration-none small text-primary fw-bold">Forgot
                    Password?</a>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mb-3">Login</button>
        </form>
        <div class="text-center mt-2 border-top pt-3">
            <a href="../index.php" class="text-decoration-none text-muted small"><i class="fas fa-arrow-left me-1"></i>
                Back to Website</a>
        </div>
    </div>
</body>

</html>