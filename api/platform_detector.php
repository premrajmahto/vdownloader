<?php
function detectPlatform($url)
{
    $url = strtolower($url);

    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
        return 'YouTube';
    }

    return 'Unknown';
}
?>