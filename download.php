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
    } elseif (strpos($host, 'facebook') !== false || strpos($host, 'fbcdn') !== false || strpos($host, 'ssscdn') !== false) {
        $referer = 'https://www.facebook.com/';
    } elseif (strpos($host, 'instagram') !== false || strpos($host, 'cdninstagram') !== false) {
        $referer = 'https://www.instagram.com/';
    }

    $headersSent = false;
    $httpCode = 0;
    $cancelStream = false;

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

    // Inspect HTTP headers before streaming output
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $headerLine) use (&$headersSent, &$httpCode, &$cancelStream, $downloadName, $mimeType) {
        $len = strlen($headerLine);
        $trimmed = trim($headerLine);

        if (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d+)/i', $trimmed, $m)) {
            $httpCode = (int)$m[1];
        }

        // Empty line signals end of HTTP response headers
        if ($trimmed === '' && $httpCode > 0) {
            if ($httpCode >= 200 && $httpCode < 300) {
                if (!$headersSent) {
                    if (ob_get_level()) {
                        @ob_end_clean();
                    }
                    header('Content-Description: File Transfer');
                    header('Content-Type: ' . $mimeType);
                    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    $headersSent = true;
                }
            } else {
                $cancelStream = true;
            }
        }
        return $len;
    });

    // Stream body bytes as they arrive
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$headersSent, &$cancelStream) {
        if ($cancelStream) {
            return 0; // Abort cURL transfer if HTTP status is non-2xx (e.g. 403, 404, 204)
        }
        if ($headersSent) {
            echo $data;
            flush();
        }
        return strlen($data);
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