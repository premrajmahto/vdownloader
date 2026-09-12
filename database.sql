CREATE DATABASE IF NOT EXISTS vdownloader;
USE vdownloader;

CREATE TABLE IF NOT EXISTS downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_url TEXT NOT NULL,
    platform VARCHAR(50) NOT NULL,
    video_title VARCHAR(255),
    file_size VARCHAR(50),
    ip_address VARCHAR(45),
    download_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin: admin@vdownloader.com / admin123 (bcrypt hash for 'admin123' used below)
INSERT INTO users (email, password) VALUES ('admin@vdownloader.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
