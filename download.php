<?php
// Disable execution time limit for streaming large files
set_time_limit(0);
if (function_exists('ini_set')) {
    @ini_set('max_execution_time', '0');
}

if (isset($_GET['url']) && isset($_GET['name'])) {
    $fileUrl = filter_var($_GET['url'], FILTER_VALIDATE_URL);
    $fileName = preg_replace('/[^a-zA-Z0-9_\-\s]/', '_', $_GET['name']);
    $format = $_GET['format'] ?? 'mp4';

    if (!$fileUrl) {
        die("Invalid URL.");
    }

    $extension = ($format === 'mp3') ? 'mp3' : 'mp4';
    $downloadName = trim($fileName) . '.' . $extension;

    // Clear output buffer before sending headers
    if (ob_get_level()) {
        @ob_end_clean();
    }

    // Force download headers
    header('Content-Description: File Transfer');
    header('Content-Type: video/mp4');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // Stream file using cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fileUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Output directly
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: */*',
        'Accept-Language: en-US,en;q=0.9',
        'Sec-Fetch-Mode: navigate'
    ]);

    // Execute streaming
    curl_exec($ch);
    curl_close($ch);
    exit;
} else {
    echo "Invalid request.";
    exit;
}
?>