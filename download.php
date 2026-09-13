<?php
// Disable execution time limit for streaming large files
set_time_limit(0);
if (function_exists('ini_set')) {
    @ini_set('max_execution_time', '0');
}

if (isset($_GET['url']) && isset($_GET['name'])) {
    $fileUrl = filter_var($_GET['url'], FILTER_VALIDATE_URL);
    $fileName = preg_replace('/[^a-zA-Z0-9_\-\s]/', '_', $_GET['name']);
    $format = strtolower($_GET['format'] ?? 'mp4');

    if (!$fileUrl) {
        die("Invalid URL.");
    }

    $extension = ($format === 'mp3') ? 'mp3' : 'mp4';
    $mimeType = ($extension === 'mp3') ? 'audio/mpeg' : 'video/mp4';
    $downloadName = trim($fileName) . '.' . $extension;

    // Detect platform/domain to set matching Referer
    $referer = 'https://www.google.com/';
    $host = strtolower(parse_url($fileUrl, PHP_URL_HOST) ?? '');
    if (strpos($host, 'youtube') !== false || strpos($host, 'googlevideo') !== false || strpos($host, 'savenow.to') !== false || strpos($host, 'affadaffa') !== false) {
        $referer = 'https://loader.to/';
    } elseif (strpos($host, 'ssscdn') !== false || strpos($host, 'snapsave') !== false || strpos($host, 'getmyfb') !== false) {
        $referer = 'https://snapsave.app/';
    } elseif (strpos($host, 'facebook') !== false || strpos($host, 'fbcdn') !== false) {
        $referer = 'https://www.facebook.com/';
    } elseif (strpos($host, 'instagram') !== false || strpos($host, 'cdninstagram') !== false) {
        $referer = 'https://www.instagram.com/';
    } elseif (strpos($host, 'tikwm') !== false || strpos($host, 'tiktok') !== false) {
        $referer = 'https://www.tikwm.com/';
    }

    $headersSent = false;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fileUrl);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0); // No timeout for download streaming
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: */*',
        'Accept-Language: en-US,en;q=0.9',
        'Referer: ' . $referer,
        'Sec-Fetch-Mode: navigate'
    ]);

    // Stream body bytes as they arrive
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$headersSent, $downloadName, $mimeType) {
        $len = strlen($data);
        if (!$headersSent) {
            $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $contentType = strtolower(curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?? '');

            // Abort if HTTP status is non-2xx or if response is HTML/JSON text instead of binary video
            if ($httpCode < 200 || $httpCode >= 300) {
                return 0;
            }
            if (strpos($contentType, 'text/html') !== false || strpos($contentType, 'application/json') !== false || strpos($contentType, 'text/xml') !== false) {
                return 0;
            }

            if (ob_get_level()) {
                @ob_end_clean();
            }
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');

            $contentLength = curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            if ($contentLength > 0) {
                header('Content-Length: ' . sprintf('%.0f', $contentLength));
            }

            $headersSent = true;
        }

        echo $data;
        flush();
        return $len;
    });

    @curl_exec($ch);
    curl_close($ch);

    // If headers were not sent (e.g. HTTP 403, 404, or cURL failure), present clean error page
    if (!$headersSent) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>Download Notice</title>';
        echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">';
        echo '</head><body class="bg-light text-center py-5">';
        echo '<div class="container" style="max-width: 520px;">';
        echo '<div class="card shadow p-4 rounded-4">';
        echo '<h4 class="text-danger mb-3">Download Link Restricted</h4>';
        echo '<p class="text-secondary mb-4">The media link could not be streamed directly because it has expired or access was restricted by the platform.</p>';
        echo '<a href="' . htmlspecialchars($fileUrl) . '" target="_blank" class="btn btn-primary w-100 mb-2">Open Direct Link in Browser</a>';
        echo '<a href="index.php" class="btn btn-outline-secondary w-100">Go Back to Downloader</a>';
        echo '</div></div></body></html>';
        exit;
    }
    exit;
} else {
    echo "Invalid request.";
    exit;
}
?>