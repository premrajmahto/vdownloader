<?php
// Catch fatal errors and exceptions to return JSON instead of 500
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

$input = json_decode(file_get_contents('php://input'), true);
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

// Attempt 1: Try local yt-dlp if it's available (Support both Windows and Linux OS)
$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$ytFilename = $isWin ? 'yt-dlp.exe' : 'yt-dlp';
$ytDlpExe = __DIR__ . '/' . $ytFilename;

if (file_exists($ytDlpExe)) {
    // For linux, try to make it executable if it lost permissions in FTP
    if (!$isWin && !is_executable($ytDlpExe)) {
        @chmod($ytDlpExe, 0755);
    }
    
    // Command
    $extraArgs = ($platform === 'YouTube') ? ' --extractor-args "youtube:player_client=android"' : '';
    $cmd = escapeshellarg($ytDlpExe) . " --no-playlist --no-warnings{$extraArgs} --dump-json " . escapeshellarg($url);
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
                    'links' => array_slice($links, 0, 10) // Limit to top 10 options
                ];
            }
        }
    }
}

// Attempt 2: Fallback to external REST APIs (essential for shared live servers like Hostinger)
if (!$realData) {
    if (function_exists('curl_init')) {
        // Fallback for YouTube URLs via Invidious / oEmbed
        if ($platform === 'YouTube' && preg_match('/(?:v=|\/embed\/|\/1\/|\/v\/|https?:\/\/youtu\.be\/|\/shorts\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            $videoId = $m[1];
            $title = "YouTube Video";
            $thumbnail = "https://i.ytimg.com/vi/$videoId/maxresdefault.jpg";
            
            $oembedUrl = "https://www.youtube.com/oembed?url=" . urlencode($url) . "&format=json";
            $ch = curl_init($oembedUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
            $oembedRes = @curl_exec($ch);
            curl_close($ch);
            
            if ($oembedRes) {
                $oembedData = json_decode($oembedRes, true);
                if (isset($oembedData['title'])) $title = $oembedData['title'];
                if (isset($oembedData['thumbnail_url'])) $thumbnail = $oembedData['thumbnail_url'];
            }
            
            $invidiousInstances = [
                "https://invidious.nerdvpn.de/api/v1/videos/$videoId",
                "https://inv.tux.pizza/api/v1/videos/$videoId",
                "https://invidious.drgns.space/api/v1/videos/$videoId",
                "https://invidious.flokinet.to/api/v1/videos/$videoId"
            ];
            
            $links = [];
            $durationFormat = '--';
            
            foreach ($invidiousInstances as $inst) {
                $ch = curl_init($inst);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
                $res = @curl_exec($ch);
                curl_close($ch);
                
                if ($res) {
                    $json = json_decode($res, true);
                    if ($json && isset($json['formatStreams'])) {
                        if (isset($json['lengthSeconds'])) {
                            $durationFormat = gmdate("H:i:s", (int)$json['lengthSeconds']);
                        }
                        foreach ($json['formatStreams'] as $f) {
                            if (!empty($f['url'])) {
                                $quality = $f['qualityLabel'] ?? ($f['quality'] ?? 'Video');
                                $ext = $f['container'] ?? 'mp4';
                                $links[] = [
                                    'url' => $f['url'],
                                    'format' => $ext,
                                    'label' => "Download ($quality)"
                                ];
                            }
                        }
                        if (!empty($links)) break;
                    }
                }
            }
            
            if (!empty($links)) {
                $realData = [
                    'title' => $title,
                    'thumbnail' => $thumbnail,
                    'duration' => $durationFormat,
                    'size' => '--',
                    'platform' => 'YouTube',
                    'links' => $links
                ];
            }
        }
        
        // Fallback for Facebook URLs
        if ($platform === 'Facebook' && !$realData) {
            $ch = curl_init("https://getmyfb.com/process");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'X-Requested-With: XMLHttpRequest',
                'Origin: https://getmyfb.com',
                'Referer: https://getmyfb.com/'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "id=" . urlencode($url) . "&locale=en");
            $fbHtml = @curl_exec($ch);
            curl_close($ch);

            if ($fbHtml) {
                $fbLinks = [];
                $fbTitle = "Facebook Video";
                $fbThumbnail = "assets/images/placeholder.jpg";

                if (preg_match('/<h4[^>]*class=["\']results-list-item-title["\'][^>]*>(.*?)<\/h4>/is', $fbHtml, $m)) {
                    $fbTitle = trim(strip_tags(html_entity_decode($m[1])));
                }
                if (preg_match('/<img[^>]+src=["\']([^"\'\s]+)["\']/', $fbHtml, $m)) {
                    $fbThumbnail = html_entity_decode($m[1]);
                }
                
                if (preg_match_all('/<li[^>]*class=["\']results-list-item(?:\s+[^"\']*)?["\'][^>]*>(.*?)<a[^>]+href=["\']([^"\'\s]+)["\'][^>]*>(.*?)<\/a>/is', $fbHtml, $m)) {
                    foreach ($m[2] as $idx => $linkUrl) {
                        $itemContent = $m[1][$idx];
                        if (strpos($itemContent, 'install-app') !== false) continue;
                        
                        $qualityLabel = "Download Media";
                        if (preg_match('/(\d+p\s*\([^)]+\)|\d+p|HD|SD|Mp3)/i', $itemContent, $qm)) {
                            $qualityLabel = "Download (" . trim($qm[1]) . ")";
                        } else {
                            $qualityLabel = "Download Option " . (count($fbLinks) + 1);
                        }

                        $fbLinks[] = [
                            'url' => html_entity_decode($linkUrl),
                            'format' => (strpos(strtolower($qualityLabel), 'mp3') !== false) ? 'mp3' : 'mp4',
                            'label' => $qualityLabel
                        ];
                    }
                }

                if (!empty($fbLinks)) {
                    $realData = [
                        'title' => $fbTitle ?: 'Facebook Video',
                        'thumbnail' => $fbThumbnail,
                        'duration' => '--',
                        'size' => '--',
                        'platform' => 'Facebook',
                        'links' => $fbLinks
                    ];
                }
            }
        }
        
        // General fallback for all social media platforms
        if (!$realData) {
            $apiEndpoints = [
                'https://api.cobalt.tools/',
                'https://co.pussthecat.org/api/json'
            ];
            
            foreach ($apiEndpoints as $apiEndpoint) {
                $ch = curl_init($apiEndpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url' => $url]));
                
                $apiResp = @curl_exec($ch);
                curl_close($ch);
            
                if ($apiResp) {
                    $apiJson = json_decode($apiResp, true);
                    if ($apiJson && isset($apiJson['status']) && $apiJson['status'] !== 'error') {
                        $links = [];
                        
                        if ($apiJson['status'] === 'stream' || $apiJson['status'] === 'redirect') {
                            $links[] = [
                                'url' => $apiJson['url'],
                                'format' => 'mp4',
                                'label' => 'Download Media'
                            ];
                        } elseif ($apiJson['status'] === 'picker') {
                            foreach ($apiJson['picker'] as $index => $item) {
                                 $links[] = [
                                     'url' => $item['url'] ?? $item,
                                     'format' => 'mp4',
                                     'label' => 'Download ' . ($index + 1)
                                 ];
                            }
                        }
                        
                        if (!empty($links)) {
                            $realData = [
                                'title' => 'Social Media Video',
                                'thumbnail' => 'assets/images/placeholder.jpg',
                                'duration' => '--',
                                'size' => '--',
                                'platform' => $platform,
                                'links' => $links
                            ];
                            break;
                        }
                    }
                }
            }
        }
    }
}

if (!$realData) {
    echo json_encode(['success' => false, 'message' => 'Unable to fetch video. The media might be private, blocked, or the server is restricting execution.']);
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

