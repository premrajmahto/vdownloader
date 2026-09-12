<?php
function detectPlatform($url)
{
    $url = strtolower($url);

    if (strpos($url, 'instagram.com') !== false) {
        return 'Instagram';
    } elseif (strpos($url, 'facebook.com') !== false || strpos($url, 'fb.watch') !== false) {
        return 'Facebook';
    } elseif (strpos($url, 'tiktok.com') !== false) {
        return 'TikTok';
    } elseif (strpos($url, 'twitter.com') !== false || strpos($url, 'x.com') !== false) {
        return 'Twitter';
    } elseif (strpos($url, 'pinterest.com') !== false || strpos($url, 'pin.it') !== false) {
        return 'Pinterest';
    } elseif (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
        return 'YouTube';
    }

    return 'Unknown';
}
?>