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

// Attempt 2: REST APIs & Scrapers Fallback (Essential for shared live hosts like Hostinger)
if (!$realData && function_exists('curl_init')) {

    // A. Facebook via Multi-stage Scraper (Page Scraping + Plugin Scraping + SnapSave)
    if ($platform === 'Facebook' && !$realData) {
        $fbTargetUrl = $url;
        // Resolve shortened share URLs (e.g. facebook.com/share/r/ or share/v/ or fb.watch)
        if (strpos($url, '/share/') !== false || strpos($url, 'fb.watch') !== false || strpos($url, 'fb.gg') !== false) {
            $redirectRes = curl_request($url, 'GET', null, [], 4);
            if ($redirectRes && preg_match('/<meta\s+http-equiv=["\']refresh["\']\s+content=["\']\d+;\s*url=([^"\'\s]+)["\']/i', $redirectRes, $rm)) {
                $fbTargetUrl = html_entity_decode($rm[1]);
            }
        }

        $fbSources = [
            $fbTargetUrl,
            "https://www.facebook.com/plugins/video.php?href=" . urlencode($fbTargetUrl),
            str_replace(['www.facebook.com', 'm.facebook.com'], 'mbasic.facebook.com', $fbTargetUrl)
        ];

        $fbTitle = "Facebook Video";
        $fbThumb = "assets/images/placeholder.jpg";
        $fbLinks = [];

        foreach ($fbSources as $sUrl) {
            $html = curl_request($sUrl, 'GET', null, [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                'Accept-Language: en-US,en;q=0.9'
            ], 4);

            if (!$html) continue;

            if ($fbTitle === "Facebook Video") {
                if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $tm)) {
                    $t = trim(html_entity_decode(strip_tags($tm[1])));
                    if (!empty($t) && strpos(strtolower($t), 'facebook') === false) $fbTitle = $t;
                }
                if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\'\s]+)["\']/i', $html, $tm)) {
                    $t = trim(html_entity_decode($tm[1]));
                    if (!empty($t)) $fbTitle = $t;
                }
            }

            if ($fbThumb === "assets/images/placeholder.jpg") {
                if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\'\s]+)["\']/i', $html, $im)) {
                    $fbThumb = html_entity_decode($im[1]);
                }
            }

            $patterns = [
                '/(?:playable_url_quality_hd|browser_native_hd_url|hd_src|hd_src_no_ratelimit)"\s*:\s*"([^"]+)"/i' => 'Download (HD Video)',
                '/(?:playable_url|browser_native_sd_url|sd_src|sd_src_no_ratelimit)"\s*:\s*"([^"]+)"/i' => 'Download (SD Video)',
                '/<meta\s+property=["\']og:video(?::secure_url)?["\']\s+content=["\']([^"\'\s]+)["\']/i' => 'Download Video'
            ];

            foreach ($patterns as $pattern => $label) {
                if (preg_match_all($pattern, $html, $matches)) {
                    foreach ($matches[1] as $rawVal) {
                        $vUrl = stripcslashes(str_replace(['\\/', '\/'], '/', $rawVal));
                        $vUrl = html_entity_decode($vUrl);
                        if (strpos($vUrl, 'http') === 0 && !in_array($vUrl, array_column($fbLinks, 'url'))) {
                            $fbLinks[] = [
                                'url' => $vUrl,
                                'format' => 'mp4',
                                'label' => $label
                            ];
                        }
                    }
                }
            }

            if (!empty($fbLinks)) break;
        }

        // SnapSave Fallback for Facebook
        if (empty($fbLinks)) {
            $snapRes = curl_request('https://snapsave.app/action.php?lang=en', 'POST', ['url' => $fbTargetUrl], [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
                'Origin: https://snapsave.app',
                'Referer: https://snapsave.app/'
            ], 4);

            if ($snapRes && preg_match('/eval\(function\(h,u,n,t,e,r\)\{.*?\}\s*\(\s*["\'](.*?)["\']\s*,\s*(\d+)\s*,\s*["\'](.*?)["\']\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/s', $snapRes, $m)) {
                $h_str = $m[1]; $u_val = (int)$m[2]; $n_str = $m[3]; $t_val = (int)$m[4]; $e_val = (int)$m[5];
                $baseStr = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ+/";
                
                $decodeNum = function($d, $e, $f) use ($baseStr) {
                    $h_chars = substr($baseStr, 0, $e);
                    $i_chars = substr($baseStr, 0, $f);
                    $j = 0;
                    $d_arr = str_split(strrev($d));
                    foreach ($d_arr as $c => $b) {
                        $pos = strpos($h_chars, $b);
                        if ($pos !== false) $j += $pos * pow($e, $c);
                    }
                    $k = "";
                    while ($j > 0) {
                        $k = $i_chars[$j % $f] . $k;
                        $j = intval(($j - ($j % $f)) / $f);
                    }
                    return $k ?: "0";
                };

                $r_str = "";
                $len = strlen($h_str);
                $n_arr = str_split($n_str);
                $delimiter = $n_str[$e_val] ?? '';
                for ($i = 0; $i < $len; $i++) {
                    $s_chunk = "";
                    while ($i < $len && $h_str[$i] !== $delimiter) {
                        $s_chunk .= $h_str[$i];
                        $i++;
                    }
                    for ($j = 0; $j < count($n_arr); $j++) {
                        $s_chunk = str_replace($n_arr[$j], (string)$j, $s_chunk);
                    }
                    $val = (int)$decodeNum($s_chunk, $e_val, 10) - $t_val;
                    if ($val > 0) $r_str .= chr($val);
                }

                $decoded = urldecode($r_str);
                if ($decoded) {
                    $clean = str_replace('\\/', '/', $decoded);
                    if (preg_match('/<p[^>]*class=["\']video-des["\'][^>]*>(.*?)<\/p>/is', $clean, $tm) || preg_match('/<h4[^>]*class=["\']results-list-item-title["\'][^>]*>(.*?)<\/h4>/is', $clean, $tm)) {
                        $fbTitle = trim(strip_tags($tm[1]));
                    }
                    if (preg_match('/<img[^>]+src=["\']([^"\'\s]+)["\']/', $clean, $im)) {
                        $fbThumb = $im[1];
                    }
                    if (preg_match_all('/<a[^>]+href=["\']([^"\'\s]+)["\'][^>]*>(.*?)<\/a>/is', $clean, $lmMatches)) {
                        foreach ($lmMatches[1] as $idx => $vUrl) {
                            $vUrl = html_entity_decode($vUrl);
                            $btnText = trim(strip_tags($lmMatches[2][$idx]));
                            if (strpos($vUrl, 'http') === 0 && strpos(strtolower($vUrl), 'snapsave') === false && strpos(strtolower($btnText), 'app') === false && !preg_match('/\.(jpg|png|webp)(\?|$)/i', $vUrl)) {
                                $fbLinks[] = [
                                    'url' => $vUrl,
                                    'format' => 'mp4',
                                    'label' => 'Download ' . ($btnText ?: ('Option ' . ($idx + 1)))
                                ];
                            }
                        }
                    }
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

    // B. TikTok via Tikwm API
    if ($platform === 'TikTok' && !$realData) {
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

    // C. Instagram Multi-stage Scraper (Mirrors + Embed + SnapSave + SaveInsta)
    if ($platform === 'Instagram' && !$realData) {
        $igTitle = "Instagram Reel";
        $igThumb = "assets/images/placeholder.jpg";
        $igLinks = [];

        $shortcode = '';
        if (preg_match('/(?:p|reel|reels|tv)\/([a-zA-Z0-9_-]+)/', $url, $m)) {
            $shortcode = $m[1];
        }

        // Attempt C1: InstaFix / DDInstagram / VxInstagram Mirror Parsing
        if ($shortcode) {
            $mirrors = ["ddinstagram.com", "vxinstagram.com", "instafix.app"];
            foreach ($mirrors as $domain) {
                $mirrorUrl = "https://$domain/reel/$shortcode/";
                $html = curl_request($mirrorUrl, 'GET', null, ['User-Agent: telegrambot (like twitterbot/1.0)'], 3);
                if ($html) {
                    if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\'\s]+)["\']/i', $html, $tm)) {
                        $igTitle = trim(html_entity_decode($tm[1]));
                    }
                    if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\'\s]+)["\']/i', $html, $im)) {
                        $igThumb = html_entity_decode($im[1]);
                    }
                    if (preg_match_all('/<meta\s+property=["\']og:video(?::url)?["\']\s+content=["\']([^"\'\s]+)["\']/i', $html, $vm)) {
                        foreach ($vm[1] as $idx => $rawUrl) {
                            $vUrl = html_entity_decode($rawUrl);
                            if (strpos($vUrl, 'http') === 0 && !in_array($vUrl, array_column($igLinks, 'url'))) {
                                $igLinks[] = [
                                    'url' => $vUrl,
                                    'format' => 'mp4',
                                    'label' => 'Download Reel (Option ' . ($idx + 1) . ')'
                                ];
                            }
                        }
                    }
                }
                if (!empty($igLinks)) break;
            }
        }

        // Attempt C2: Instagram Embed URL parsing (/embed/ & oEmbed)
        if (empty($igLinks)) {
            $embedUrls = [
                "https://api.instagram.com/oembed/?url=" . urlencode($url),
                "https://www.instagram.com/p/$shortcode/embed/captioned/"
            ];

            foreach ($embedUrls as $eUrl) {
                $html = curl_request($eUrl, 'GET', null, [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ], 4);
                if (!$html) continue;

                if ($igTitle === "Instagram Reel") {
                    if (preg_match('/<div[^>]*class=["\']Caption["\'][^>]*>(.*?)<\/div>/is', $html, $tm)) {
                        $igTitle = trim(strip_tags(html_entity_decode($tm[1])));
                    }
                }

                if ($igThumb === "assets/images/placeholder.jpg") {
                    if (preg_match('/<img[^>]+class=["\']EmbeddedMediaImage["\'][^>]+src=["\']([^"\'\s]+)["\']/i', $html, $im)) {
                        $igThumb = html_entity_decode($im[1]);
                    }
                }

                if (preg_match('/<video[^>]+src=["\']([^"\'\s]+)["\']/i', $html, $vm)) {
                    $vUrl = html_entity_decode($vm[1]);
                    if (strpos($vUrl, 'http') === 0 && !in_array($vUrl, array_column($igLinks, 'url'))) {
                        $igLinks[] = [
                            'url' => $vUrl,
                            'format' => 'mp4',
                            'label' => 'Download Reel (MP4)'
                        ];
                    }
                }

                if (empty($igLinks)) {
                    if (preg_match_all('/"(?:video_url|video_versions)"\s*:\s*\[?\s*\{\s*"[^"]*"\s*:\s*[^,]+,\s*"url"\s*:\s*"([^"]+)"/i', $html, $m)) {
                        foreach (array_unique($m[1]) as $idx => $vUrl) {
                            $cleanUrl = str_replace(['\/', '\\u00253D', '\\u0026'], ['/', '=', '&'], $vUrl);
                            if (strpos($cleanUrl, 'http') === 0 && !in_array($cleanUrl, array_column($igLinks, 'url'))) {
                                $igLinks[] = [
                                    'url' => $cleanUrl,
                                    'format' => 'mp4',
                                    'label' => 'Download Video Option ' . ($idx + 1)
                                ];
                            }
                        }
                    }
                }

                if (!empty($igLinks)) break;
            }
        }

        // Attempt C3: SnapSave API for Instagram
        if (empty($igLinks)) {
            $snapRes = curl_request('https://snapsave.app/action.php?lang=en', 'POST', ['url' => $url], [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Origin: https://snapsave.app',
                'Referer: https://snapsave.app/'
            ], 4);

            if ($snapRes && preg_match('/eval\(function\(h,u,n,t,e,r\)\{.*?\}\s*\(\s*["\'](.*?)["\']\s*,\s*(\d+)\s*,\s*["\'](.*?)["\']\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/s', $snapRes, $m)) {
                $h_str = $m[1]; $u_val = (int)$m[2]; $n_str = $m[3]; $t_val = (int)$m[4]; $e_val = (int)$m[5];
                $baseStr = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ+/";
                
                $decodeNum = function($d, $e, $f) use ($baseStr) {
                    $h_chars = substr($baseStr, 0, $e);
                    $i_chars = substr($baseStr, 0, $f);
                    $j = 0;
                    $d_arr = str_split(strrev($d));
                    foreach ($d_arr as $c => $b) {
                        $pos = strpos($h_chars, $b);
                        if ($pos !== false) $j += $pos * pow($e, $c);
                    }
                    $k = "";
                    while ($j > 0) {
                        $k = $i_chars[$j % $f] . $k;
                        $j = intval(($j - ($j % $f)) / $f);
                    }
                    return $k ?: "0";
                };

                $r_str = "";
                $len = strlen($h_str);
                $n_arr = str_split($n_str);
                $delimiter = $n_str[$e_val] ?? '';
                for ($i = 0; $i < $len; $i++) {
                    $s_chunk = "";
                    while ($i < $len && $h_str[$i] !== $delimiter) {
                        $s_chunk .= $h_str[$i];
                        $i++;
                    }
                    for ($j = 0; $j < count($n_arr); $j++) {
                        $s_chunk = str_replace($n_arr[$j], (string)$j, $s_chunk);
                    }
                    $val = (int)$decodeNum($s_chunk, $e_val, 10) - $t_val;
                    if ($val > 0) $r_str .= chr($val);
                }

                $decoded = urldecode($r_str);
                if ($decoded) {
                    $clean = str_replace('\\/', '/', $decoded);
                    if (preg_match('/<p[^>]*class=["\']video-des["\'][^>]*>(.*?)<\/p>/is', $clean, $tm)) {
                        $igTitle = trim(strip_tags($tm[1]));
                    }
                    if (preg_match('/<img[^>]+src=["\']([^"\'\s]+)["\']/', $clean, $im)) {
                        $igThumb = $im[1];
                    }
                    if (preg_match_all('/<a[^>]+href=["\']([^"\'\s]+)["\'][^>]*>(.*?)<\/a>/is', $clean, $lmMatches)) {
                        foreach ($lmMatches[1] as $idx => $vUrl) {
                            $vUrl = html_entity_decode($vUrl);
                            $btnText = trim(strip_tags($lmMatches[2][$idx]));
                            if (strpos($vUrl, 'http') === 0 && strpos(strtolower($vUrl), 'snapsave') === false && strpos(strtolower($btnText), 'app') === false) {
                                $igLinks[] = [
                                    'url' => $vUrl,
                                    'format' => 'mp4',
                                    'label' => 'Download ' . ($btnText ?: ('Option ' . ($idx + 1)))
                                ];
                            }
                        }
                    }
                }
            }
        }

        // Attempt C4: SaveInsta REST API
        if (empty($igLinks)) {
            $siRes = curl_request("https://saveinsta.app/action2.php", 'POST', "url=" . urlencode($url) . "&action=post", [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Origin: https://saveinsta.app',
                'Referer: https://saveinsta.app/'
            ], 4);

            if ($siRes && preg_match_all('/<a[^>]+href=["\']([^"\'\s]+(?:\.mp4|cdninstagram|fbcdn)[^"\'\s]*)["\']/i', $siRes, $m)) {
                foreach (array_unique($m[1]) as $idx => $vUrl) {
                    $cleanUrl = html_entity_decode($vUrl);
                    if (strpos($cleanUrl, 'http') === 0 && !in_array($cleanUrl, array_column($igLinks, 'url'))) {
                        $igLinks[] = [
                            'url' => $cleanUrl,
                            'format' => 'mp4',
                            'label' => 'Download Reel Option ' . ($idx + 1)
                        ];
                    }
                }
            }
        }

        if (!empty($igLinks)) {
            $realData = [
                'title' => $igTitle,
                'thumbnail' => $igThumb,
                'duration' => '--',
                'size' => '--',
                'platform' => 'Instagram',
                'links' => $igLinks
            ];
        }
    }

    // D. YouTube Fallback (Loader.to & oEmbed)
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

            // Attempt D1: Loader.to API
            $loaderRes = curl_request("https://loader.to/ajax/download.php?format=720&url=" . urlencode("https://www.youtube.com/watch?v=$videoId"), 'GET', null, [], 8);
            if ($loaderRes) {
                $lJson = json_decode($loaderRes, true);
                if ($lJson && isset($lJson['success']) && $lJson['success'] && !empty($lJson['progress_url'])) {
                    $pUrl = $lJson['progress_url'];
                    if (isset($lJson['title'])) $title = $lJson['title'];

                    for ($k = 0; $k < 25; $k++) {
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
