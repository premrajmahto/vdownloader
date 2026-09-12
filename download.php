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
    if (strpos($host, 'youtube') !== false || strpos($host, 'googlevideo') !== false) {
        $referer = 'https://www.youtube.com/';
    } elseif (strpos($host, 'ssscdn') !== false || strpos($host, 'getmyfb') !== false) {
        $referer = 'https://getmyfb.com/';
    } elseif (strpos($host, 'facebook') !== false || strpos($host, 'fbcdn') !== false) {
        $referer = 'https://www.facebook.com/';
    } elseif (strpos($host, 'instagram') !== false || strpos($host, 'cdninstagram') !== false) {
        $referer = 'https://www.instagram.com/';
    }

    $headersSent = false;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fileUrl);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0); // No timeout for download streaming
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
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
            if ($httpCode >= 200 && $httpCode < 300) {
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
                    header('Content-Length: ' . (int)$contentLength);
                }

                $headersSent = true;
            } else {
                return 0; // Abort cURL transfer if HTTP status is non-2xx (e.g. 403, 404, 204)
            }
        }

        echo $data;
        flush();
        return $len;
    });

    @curl_exec($ch);
    curl_close($ch);

    // If headers were not sent (e.g. HTTP 403, 204, or cURL failure), fallback to direct browser redirect
    if (!$headersSent) {
        header("Location: " . $fileUrl);
        exit;
    }
    exit;
} else {
    echo "Invalid request.";
    exit;
}
?>