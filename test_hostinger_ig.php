<?php
$reel1 = 'https://www.instagram.com/reel/DD764RMykFn/';
$reel2 = 'https://www.instagram.com/reel/C8r_P-bS9Qv/';

function testHostingerCurl($apiUrl, $postData = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
    if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : $postData);
    }
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'len' => strlen($res), 'res' => $res];
}

echo "=== TESTING API PROVIDERS FOR INSTAGRAM REELS ===\n";

// Provider 1: SaveIg
echo "--- 1. SaveIg ---\n";
$r1 = testHostingerCurl("https://saveig.app/api/ajaxSearch", "q=" . urlencode($reel1) . "&t=media&lang=en", [
    'Content-Type: application/x-www-form-urlencoded',
    'X-Requested-With: XMLHttpRequest',
    'Origin: https://saveig.app',
    'Referer: https://saveig.app/'
]);
echo "R1 SaveIg Code: {$r1['code']}, Len: {$r1['len']}\n";
if ($r1['code'] == 200 && strpos($r1['res'], 'data') !== false) {
    echo "SAVEIG OUTPUT: " . substr($r1['res'], 0, 500) . "\n";
}

// Provider 2: FastDl
echo "--- 2. FastDl ---\n";
$r2 = testHostingerCurl("https://fastdl.app/c/", "url=" . urlencode($reel1) . "&ts=" . time(), [
    'Content-Type: application/x-www-form-urlencoded',
    'Origin: https://fastdl.app',
    'Referer: https://fastdl.app/'
]);
echo "R1 FastDl Code: {$r2['code']}, Len: {$r2['len']}\n";
if ($r2['code'] == 200) {
    echo "FASTDL OUTPUT: " . substr(strip_tags($r2['res']), 0, 300) . "\n";
}

// Provider 3: InDown
echo "--- 3. InDown ---\n";
$r3 = testHostingerCurl("https://indown.io/download", "link=" . urlencode($reel1) . "&referer=https://indown.io/", [
    'Content-Type: application/x-www-form-urlencoded',
    'Origin: https://indown.io',
    'Referer: https://indown.io/'
]);
echo "R1 InDown Code: {$r3['code']}, Len: {$r3['len']}\n";
if ($r3['code'] == 200) {
    if (preg_match_all('/href=["\']([^"\'\s]+(?:\.mp4|cdninstagram|fbcdn)[^"\'\s]*)["\']/i', $r3['res'], $m)) {
        echo "INDOWN MATCHES: " . print_r($m[1], true) . "\n";
    }
}

// Provider 4: SSSInstagram
echo "--- 4. SSSInstagram ---\n";
$r4 = testHostingerCurl("https://sssinstagram.com/api/convert", "id=" . urlencode($reel1) . "&locale=en", [
    'Content-Type: application/x-www-form-urlencoded',
    'X-Requested-With: XMLHttpRequest',
    'Origin: https://sssinstagram.com'
]);
echo "R1 SSSInstagram Code: {$r4['code']}, Len: {$r4['len']}\n";
if ($r4['code'] == 200) {
    echo "SSSINSTAGRAM OUTPUT: " . substr($r4['res'], 0, 400) . "\n";
}

// Provider 5: SnapInsta
echo "--- 5. SnapInsta ---\n";
$r5 = testHostingerCurl("https://snapinsta.app/action2.php", "url=" . urlencode($reel1) . "&action=post", [
    'Content-Type: application/x-www-form-urlencoded',
    'Origin: https://snapinsta.app',
    'Referer: https://snapinsta.app/'
]);
echo "R1 SnapInsta Code: {$r5['code']}, Len: {$r5['len']}\n";

// Provider 6: Publer Tool Media
echo "--- 6. Publer ---\n";
$r6 = testHostingerCurl("https://publer.io/api/v1/tools/media/download", json_encode(['url' => $reel1, 'iphone' => false]), [
    'Content-Type: application/json',
    'Origin: https://publer.io',
    'Referer: https://publer.io/'
]);
echo "R6 Publer Code: {$r6['code']}, Len: {$r6['len']}\n";
if ($r6['code'] == 200) {
    echo "PUBLER OUTPUT: " . substr($r6['res'], 0, 400) . "\n";
}
