<?php
// Catch fatal errors and exceptions to return JSON instead of 500
error_reporting(E_ALL);
ini_set('display_errors', 0);
@set_time_limit(60);
if (function_exists('ini_set')) {
    @ini_set('max_execution_time', '60');
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fatal Error: ' . $error['message'] . ' on line ' . $error['line']]);
        exit;
    }
});

header('Content-Type: application/json');

try {
    require_once dirname(__DIR__) . '/config/database.php';
    require_once 'platform_detector.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Init Error: ' . $e->getMessage()]);
    exit;
}

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    $rawInput = @file_get_contents('php://stdin');
}
$input = json_decode($rawInput, true);
$url = isset($input['url']) ? trim($input['url']) : '';

if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid URL provided.']);
    exit;
}

$platform = detectPlatform($url);

if ($platform === 'Unknown') {
    echo json_encode(['success' => false, 'message' => 'Platform not supported.']);
    exit;
}

$realData = null;

if (!function_exists('run_cmd')) {
    function run_cmd($cmd) {
        if (function_exists('shell_exec') && is_callable('shell_exec')) {
            $res = @shell_exec($cmd);
            if ($res) return $res;
        }
        if (function_exists('exec') && is_callable('exec')) {
            @exec($cmd, $out);
            if (!empty($out)) return is_array($out) ? implode("\n", $out) : $out;
        }
        if (function_exists('passthru') && is_callable('passthru')) {
            ob_start();
            @passthru($cmd);
            $res = ob_get_clean();
            if ($res) return $res;
        }
        if (function_exists('proc_open') && is_callable('proc_open')) {
            $desc = [1 => ["pipe", "w"], 2 => ["pipe", "w"]];
            $proc = @proc_open($cmd, $desc, $pipes);
            if (is_resource($proc)) {
                $res = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                if ($res) return $res;
            }
        }
        return false;
    }
}

if (!function_exists('curl_request')) {
    function curl_request($targetUrl, $method = 'GET', $postData = null, $customHeaders = [], $timeout = 7) {
        if (!function_exists('curl_init')) return false;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if (defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        
        $headers = array_merge([
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
        ], $customHeaders);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($postData !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : $postData);
            }
        }

        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
}

// Attempt 1: Try local yt-dlp if available
$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$ytFilename = $isWin ? 'yt-dlp.exe' : 'yt-dlp';
$ytDlpExe = __DIR__ . '/' . $ytFilename;

if (file_exists($ytDlpExe)) {
    if (!$isWin && !is_executable($ytDlpExe)) {
        @chmod($ytDlpExe, 0755);
    }
    
    $extraArgs = ($platform === 'YouTube') ? ' --extractor-args "youtube:player_client=android"' : '';
    $cmd = escapeshellarg($ytDlpExe) . " --no-playlist --no-warnings --socket-timeout 5 --retries 1{$extraArgs} --dump-json " . escapeshellarg($url);
    $output = run_cmd($cmd);
    
    if ($output) {
        $lines = explode("\n", trim($output));
        $data = null;
        foreach ($lines as $line) {
            $decoded = json_decode(trim($line), true);
            if ($decoded && isset($decoded['title'])) {
                $data = $decoded;
                break;
            }
        }

        if ($data && isset($data['title'])) {
            $title = $data['title'];
            $thumbnail = $data['thumbnail'] ?? 'assets/images/placeholder.jpg';
            $durationFormat = isset($data['duration']) ? gmdate("H:i:s", (int)$data['duration']) : '--';
            
            $sizeFormat = '--';
            if (isset($data['filesize']) && is_numeric($data['filesize'])) {
                $sizeFormat = round($data['filesize'] / 1048576, 2) . ' MB';
            } elseif (isset($data['filesize_approx']) && is_numeric($data['filesize_approx'])) {
                $sizeFormat = round($data['filesize_approx'] / 1048576, 2) . ' MB';
            }
                          
            $links = [];
            if (!empty($data['formats'])) {
                foreach ($data['formats'] as $f) {
                    if (empty($f['url']) || strpos($f['url'], 'http') !== 0) continue;
                    $vcodec = $f['vcodec'] ?? 'none';
                    $acodec = $f['acodec'] ?? 'none';
                    $ext = $f['ext'] ?? 'mp4';
                    
                    if ($vcodec !== 'none' && $acodec !== 'none') {
                        $quality = isset($f['height']) && $f['height'] > 0 ? $f['height'] . 'p' : 'Video';
                        $links[] = [
                            'url' => $f['url'],
                            'format' => $ext,
                            'label' => "Download ($quality)"
                        ];
                    }
                }
            }
            if (empty($links) && !empty($data['formats'])) {
                foreach ($data['formats'] as $f) {
                    if (empty($f['url']) || strpos($f['url'], 'http') !== 0) continue;
                    $ext = $f['ext'] ?? 'mp4';
                    $quality = isset($f['height']) && $f['height'] > 0 ? $f['height'] . 'p' : ($f['format_note'] ?? 'Media');
                    $links[] = [
                        'url' => $f['url'],
                        'format' => $ext,
                        'label' => "Download ($quality)"
                    ];
                }
            }
            if (empty($links) && !empty($data['url'])) {
                $links[] = [
                    'url' => $data['url'],
                    'format' => $data['ext'] ?? 'mp4',
                    'label' => 'Download Media'
                ];
            }
            
            if (!empty($links)) {
                $realData = [
                    'title' => $title,
                    'thumbnail' => $thumbnail,
                    'duration' => $durationFormat,
                    'size' => $sizeFormat,
                    'platform' => $platform,
                    'links' => array_slice($links, 0, 10)
                ];
            }
        }
    }
}

// Attempt 2: REST APIs & Scrapers Fallback for YouTube (Essential for shared live hosts)
if (!$realData && function_exists('curl_init')) {
    if ($platform === 'YouTube' && !$realData) {
        if (preg_match('/(?:v=|\/embed\/|\/1\/|\/v\/|https?:\/\/youtu\.be\/|\/shorts\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            $videoId = $m[1];
            $title = "YouTube Video";
            $thumbnail = "https://i.ytimg.com/vi/$videoId/maxresdefault.jpg";
            
            $oembedRes = curl_request("https://www.youtube.com/oembed?url=" . urlencode("https://www.youtube.com/watch?v=$videoId") . "&format=json");
            if ($oembedRes) {
                $oembedData = json_decode($oembedRes, true);
                if (isset($oembedData['title'])) $title = $oembedData['title'];
                if (isset($oembedData['thumbnail_url'])) $thumbnail = $oembedData['thumbnail_url'];
            }

            // Loader.to API Fallback
            $loaderRes = curl_request("https://loader.to/ajax/download.php?format=720&url=" . urlencode("https://www.youtube.com/watch?v=$videoId"), 'GET', null, [], 8);
            if ($loaderRes) {
                $lJson = json_decode($loaderRes, true);
                if ($lJson && isset($lJson['success']) && $lJson['success'] && !empty($lJson['progress_url'])) {
                    $pUrl = $lJson['progress_url'];
                    if (isset($lJson['title'])) $title = $lJson['title'];

                    for ($k = 0; $k < 15; $k++) {
                        usleep(800000);
                        $pRes = curl_request($pUrl, 'GET', null, [], 5);
                        if ($pRes) {
                            $pData = json_decode($pRes, true);
                            if (!empty($pData['download_url']) && strpos($pData['download_url'], 'http') === 0) {
                                $realData = [
                                    'title' => $title,
                                    'thumbnail' => $thumbnail,
                                    'duration' => '--',
                                    'size' => '--',
                                    'platform' => 'YouTube',
                                    'links' => [
                                        ['url' => $pData['download_url'], 'format' => 'mp4', 'label' => 'Download MP4 Video (HD)']
                                    ]
                                ];
                                break;
                            }
                        }
                    }
                }
            }
        }
    }
}

if (!$realData) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch video. The media might be private, blocked, or not supported.'
    ]);
    exit;
}

// Log to DB
try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (isset($pdo) && $pdo !== null) {
        $stmt = $pdo->prepare("INSERT INTO downloads (video_url, platform, video_title, file_size, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$url, $platform, $realData['title'], $realData['size'], $ip]);
    }
} catch (Exception $e) {
    // Silently handle DB error
}

echo json_encode([
    'success' => true,
    'data' => $realData
]);
exit;
