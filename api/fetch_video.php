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
                    'links' => array_slice($links, 0, 10)
                ];
            }
        }
    }
}

// Attempt 2: REST APIs & Scrapers Fallback (Essential for shared live hosts like Hostinger)
if (!$realData && function_exists('curl_init')) {

    // A. Facebook Fallback (Reels, Videos, Watch, Share Links)
    if ($platform === 'Facebook' && !$realData) {
        // Attempt A1: GetMyFB API (Supports Reels & Videos)
        $fbRes = curl_request("https://getmyfb.com/process", 'POST', "id=" . urlencode($url) . "&locale=en", [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'X-Requested-With: XMLHttpRequest',
            'Origin: https://getmyfb.com',
            'Referer: https://getmyfb.com/'
        ], 8);

        if ($fbRes) {
            $fbTitle = "Facebook Video";
            $fbThumb = "assets/images/placeholder.jpg";
            $fbLinks = [];

            if (preg_match('/<h4[^>]*class=["\']results-list-item-title["\'][^>]*>(.*?)<\/h4>/is', $fbRes, $m)) {
                $fbTitle = trim(strip_tags(html_entity_decode($m[1])));
            }
            if (preg_match('/<img[^>]+src=["\']([^"\'\s]+)["\']/', $fbRes, $m)) {
                $fbThumb = html_entity_decode($m[1]);
            }

            if (preg_match_all('/<a[^>]+href=["\']([^"\'\s]*ssscdn\.io[^"\'\s]*)["\']/i', $fbRes, $m)) {
                foreach (array_unique($m[1]) as $idx => $linkUrl) {
                    $cleanUrl = html_entity_decode($linkUrl);
                    if (strpos($cleanUrl, 'http') === 0 && strpos($cleanUrl, 'play.google.com') === false) {
                        $label = ($idx === 0) ? 'Download HD Video' : (($idx === 1) ? 'Download SD Video' : 'Download Option ' . ($idx + 1));
                        $fbLinks[] = [
                            'url' => $cleanUrl,
                            'format' => 'mp4',
                            'label' => $label
                        ];
                    }
                }
            }

            if (!empty($fbLinks)) {
                $realData = [
                    'title' => $fbTitle ?: 'Facebook Video',
                    'thumbnail' => $fbThumb,
                    'duration' => '--',
                    'size' => '--',
                    'platform' => 'Facebook',
                    'links' => $fbLinks
                ];
            }
        }

        // Attempt A2: Direct Crawl using Facebook External Hit User Agent
        if (!$realData) {
            $fbHtml = curl_request($url, 'GET', null, [
                'User-Agent: facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'
            ], 6);

            if ($fbHtml) {
                $fbTitle = "Facebook Video";
                $fbThumb = "assets/images/placeholder.jpg";
                $fbLinks = [];

                if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\'\s]+)["\']/i', $fbHtml, $m)) {
                    $fbTitle = html_entity_decode($m[1]);
                }
                if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\'\s]+)["\']/i', $fbHtml, $m)) {
                    $fbThumb = html_entity_decode($m[1]);
                }

                if (preg_match_all('/(?:browser_native_hd_url|browser_native_sd_url|hd_src|sd_src|playable_url|playable_url_quality_hd)["\']\s*:\s*["\']([^"\'\s]+)["\']/i', $fbHtml, $m)) {
                    foreach ($m[1] as $idx => $vUrl) {
                        $vUrl = str_replace(['\/', '\\u00253D', '\\u0026'], ['/', '=', '&'], $vUrl);
                        if (strpos($vUrl, 'http') === 0) {
                            $label = ($idx === 0) ? 'Download HD Video' : 'Download SD Video';
                            $fbLinks[] = ['url' => $vUrl, 'format' => 'mp4', 'label' => $label];
                        }
                    }
                }

                if (!empty($fbLinks)) {
                    $realData = [
                        'title' => $fbTitle,
                        'thumbnail' => $fbThumb,
                        'duration' => '--',
                        'size' => '--',
                        'platform' => 'Facebook',
                        'links' => $fbLinks
                    ];
                }
            }
        }
    }

    // B. TikTok & Instagram via Tikwm API
    if (($platform === 'TikTok' || $platform === 'Instagram') && !$realData) {
        $tikwmRes = curl_request("https://www.tikwm.com/api/?url=" . urlencode($url));
        if ($tikwmRes) {
            $json = json_decode($tikwmRes, true);
            if ($json && isset($json['code']) && $json['code'] === 0 && isset($json['data'])) {
                $d = $json['data'];
                $title = $d['title'] ?? ($platform . ' Video');
                $thumb = $d['cover'] ?? ($d['origin_cover'] ?? 'assets/images/placeholder.jpg');
                $dur = isset($d['duration']) ? gmdate("H:i:s", (int)$d['duration']) : '--';
                
                $links = [];
                if (!empty($d['play'])) {
                    $links[] = ['url' => $d['play'], 'format' => 'mp4', 'label' => 'Download (No Watermark)'];
                }
                if (!empty($d['wmplay'])) {
                    $links[] = ['url' => $d['wmplay'], 'format' => 'mp4', 'label' => 'Download (Watermark)'];
                }
                if (!empty($d['music'])) {
                    $links[] = ['url' => $d['music'], 'format' => 'mp3', 'label' => 'Download Audio (MP3)'];
                }
                if (!empty($d['images']) && is_array($d['images'])) {
                    foreach ($d['images'] as $idx => $imgUrl) {
                        $links[] = ['url' => $imgUrl, 'format' => 'jpg', 'label' => 'Download Photo ' . ($idx + 1)];
                    }
                }
                
                if (!empty($links)) {
                    $realData = [
                        'title' => $title,
                        'thumbnail' => $thumb,
                        'duration' => $dur,
                        'size' => '--',
                        'platform' => $platform,
                        'links' => $links
                    ];
                }
            }
        }
    }

    // C. YouTube Fallback
    if ($platform === 'YouTube' && !$realData) {
        if (preg_match('/(?:v=|\/embed\/|\/1\/|\/v\/|https?:\/\/youtu\.be\/|\/shorts\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            $videoId = $m[1];
            $title = "YouTube Video";
            $thumbnail = "https://i.ytimg.com/vi/$videoId/maxresdefault.jpg";
            
            $oembedRes = curl_request("https://www.youtube.com/oembed?url=" . urlencode($url) . "&format=json");
            if ($oembedRes) {
                $oembedData = json_decode($oembedRes, true);
                if (isset($oembedData['title'])) $title = $oembedData['title'];
                if (isset($oembedData['thumbnail_url'])) $thumbnail = $oembedData['thumbnail_url'];
            }
            
            $invidiousInstances = [
                "https://invidious.projectsegfau.lt/api/v1/videos/$videoId",
                "https://invidious.flokinet.to/api/v1/videos/$videoId",
                "https://invidious.nerdvpn.de/api/v1/videos/$videoId",
                "https://inv.tux.pizza/api/v1/videos/$videoId"
            ];
            
            $links = [];
            $durationFormat = '--';
            
            foreach ($invidiousInstances as $inst) {
                $res = curl_request($inst, 'GET', null, [], 5);
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
    }

    // D. Instagram Direct Metadata Scrape
    if ($platform === 'Instagram' && !$realData) {
        $igHtml = curl_request($url, 'GET', null, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ], 5);
        if ($igHtml) {
            $igTitle = "Instagram Video";
            $igThumb = "assets/images/placeholder.jpg";
            $igVideo = null;
            if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\'\s]+)["\']/i', $igHtml, $m)) {
                $igTitle = html_entity_decode($m[1]);
            }
            if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\'\s]+)["\']/i', $igHtml, $m)) {
                $igThumb = html_entity_decode($m[1]);
            }
            if (preg_match('/<meta\s+property=["\']og:video(?::url)?["\']\s+content=["\']([^"\'\s]+)["\']/i', $igHtml, $m)) {
                $igVideo = html_entity_decode($m[1]);
            }
            if ($igVideo) {
                $realData = [
                    'title' => $igTitle,
                    'thumbnail' => $igThumb,
                    'duration' => '--',
                    'size' => '--',
                    'platform' => 'Instagram',
                    'links' => [['url' => $igVideo, 'format' => 'mp4', 'label' => 'Download MP4']]
                ];
            }
        }
    }

    // E. Twitter / Pinterest / Snapchat Metadata Scrape
    if (in_array($platform, ['Twitter', 'Pinterest', 'Snapchat']) && !$realData) {
        $metaHtml = curl_request($url, 'GET', null, [], 5);
        if ($metaHtml) {
            $mTitle = "$platform Media";
            $mThumb = "assets/images/placeholder.jpg";
            $mVideo = null;

            if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\'\s]+)["\']/i', $metaHtml, $m)) {
                $mTitle = html_entity_decode($m[1]);
            }
            if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\'\s]+)["\']/i', $metaHtml, $m)) {
                $mThumb = html_entity_decode($m[1]);
            }
            if (preg_match('/<meta\s+property=["\']og:video(?::url)?["\']\s+content=["\']([^"\'\s]+)["\']/i', $metaHtml, $m)) {
                $mVideo = html_entity_decode($m[1]);
            }

            if ($mVideo) {
                $realData = [
                    'title' => $mTitle,
                    'thumbnail' => $mThumb,
                    'duration' => '--',
                    'size' => '--',
                    'platform' => $platform,
                    'links' => [['url' => $mVideo, 'format' => 'mp4', 'label' => 'Download Media']]
                ];
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
