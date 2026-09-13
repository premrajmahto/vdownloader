document.addEventListener('DOMContentLoaded', () => {
    // Dark mode toggle
    const themeToggle = document.getElementById('theme-toggle');
    const currentTheme = localStorage.getItem('theme') || 'light';
    
    document.documentElement.setAttribute('data-theme', currentTheme);
    updateThemeIcon(currentTheme);

    themeToggle.addEventListener('click', () => {
        let theme = document.documentElement.getAttribute('data-theme');
        let newTheme = theme === 'light' ? 'dark' : 'light';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeIcon(newTheme);
    });

    function updateThemeIcon(theme) {
        if(theme === 'light') {
            themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
        } else {
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
    }

    // Download form handling
    const downloadForm = document.getElementById('downloadForm');
    const loader = document.getElementById('loader');
    const resultBox = document.getElementById('resultBox');
    
    if (downloadForm) {
        downloadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const url = document.getElementById('videoUrl').value.trim();
            if (!url) {
                alert('Please enter a valid URL');
                return;
            }

            // Show loader, hide result
            loader.style.display = 'block';
            resultBox.style.display = 'none';

            // Make API call
            fetch('api/fetch_video.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ url: url })
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success && data.data && data.data.links && data.data.links.length > 0) {
                    loader.style.display = 'none';
                    showResult(data.data);
                } else {
                    console.log('Backend returned no direct links, attempting client-side fallback...');
                    attemptClientSideFallback(url, data.message);
                }
            })
            .catch(error => {
                console.warn('Backend fetch error, attempting client fallback...', error);
                attemptClientSideFallback(url);
            });
            
            function attemptClientSideFallback(videoUrl, failMessage) {
                const lowerUrl = videoUrl.toLowerCase();

                // 1. TikTok, Instagram, or Facebook via Tikwm Client API
                if (lowerUrl.includes('tiktok.com') || lowerUrl.includes('instagram.com') || lowerUrl.includes('facebook.com') || lowerUrl.includes('fb.watch')) {
                    fetch('https://www.tikwm.com/api/?url=' + encodeURIComponent(videoUrl))
                    .then(res => res.json())
                    .then(resData => {
                        if (resData && resData.code === 0 && resData.data) {
                            loader.style.display = 'none';
                            const d = resData.data;
                            let links = [];
                            if (d.play) links.push({ url: d.play, format: 'mp4', label: 'Download (No Watermark)' });
                            if (d.wmplay) links.push({ url: d.wmplay, format: 'mp4', label: 'Download (Watermark)' });
                            if (d.music) links.push({ url: d.music, format: 'mp3', label: 'Download Audio (MP3)' });
                            if (d.images && Array.isArray(d.images)) {
                                d.images.forEach((img, i) => {
                                    links.push({ url: img, format: 'jpg', label: 'Download Photo ' + (i + 1) });
                                });
                            }
                            if (links.length > 0) {
                                let detectedPlatform = 'Social Media';
                                if (lowerUrl.includes('tiktok.com')) detectedPlatform = 'TikTok';
                                else if (lowerUrl.includes('instagram.com')) detectedPlatform = 'Instagram';
                                else if (lowerUrl.includes('facebook.com') || lowerUrl.includes('fb.watch')) detectedPlatform = 'Facebook';

                                showResult({
                                    title: d.title || (detectedPlatform + ' Video'),
                                    thumbnail: d.cover || d.origin_cover || 'assets/images/placeholder.jpg',
                                    duration: d.duration ? d.duration + 's' : '--',
                                    size: '--',
                                    platform: detectedPlatform,
                                    links: links
                                });
                                return;
                            }
                        }
                        
                        // Fallback 1b: Instagram oEmbed Client Fallback
                        if (lowerUrl.includes('instagram.com')) {
                            fetch('https://api.instagram.com/oembed/?url=' + encodeURIComponent(videoUrl))
                            .then(r => r.json())
                            .then(oData => {
                                loader.style.display = 'none';
                                if (oData && oData.thumbnail_url) {
                                    showResult({
                                        title: oData.title || 'Instagram Media',
                                        thumbnail: oData.thumbnail_url,
                                        duration: '--',
                                        size: '--',
                                        platform: 'Instagram',
                                        links: [
                                            { url: oData.thumbnail_url, format: 'jpg', label: 'Download Thumbnail / Cover' }
                                        ]
                                    });
                                    return;
                                }
                                showFinalError(failMessage);
                            })
                            .catch(() => {
                                showFinalError(failMessage);
                            });
                        } else {
                            showFinalError(failMessage);
                        }
                    })
                    .catch(() => {
                        showFinalError(failMessage);
                    });
                    return;
                }

                // 2. YouTube via oEmbed & Piped / Invidious Client API
                const ytMatch = videoUrl.match(/(?:v=|\/embed\/|\/1\/|\/v\/|https?:\/\/youtu\.be\/|\/shorts\/)([a-zA-Z0-9_-]{11})/);
                if (ytMatch) {
                    const videoId = ytMatch[1];
                    fetch(`https://www.youtube.com/oembed?url=${encodeURIComponent(videoUrl)}&format=json`)
                    .then(r => r.json())
                    .then(meta => {
                        fetch(`https://pipedapi.lunar.icu/streams/${videoId}`)
                        .then(r => r.json())
                        .then(piped => {
                            loader.style.display = 'none';
                            let links = [];
                            if (piped && piped.audioVideoFiles) {
                                piped.audioVideoFiles.forEach(f => {
                                    links.push({
                                        url: f.url,
                                        format: f.mimeType && f.mimeType.includes('mp4') ? 'mp4' : 'webm',
                                        label: `Download (${f.quality || 'Video'})`
                                    });
                                });
                            }
                            if (links.length > 0) {
                                showResult({
                                    title: meta.title || piped.title || 'YouTube Video',
                                    thumbnail: `https://i.ytimg.com/vi/${videoId}/hqdefault.jpg`,
                                    duration: piped.duration ? piped.duration + 's' : '--',
                                    size: '--',
                                    platform: 'YouTube',
                                    links: links.slice(0, 10)
                                });
                            } else {
                                showFinalError(failMessage);
                            }
                        })
                        .catch(() => {
                            showFinalError(failMessage);
                        });
                    })
                    .catch(() => {
                        showFinalError(failMessage);
                    });
                    return;
                }

                // 3. General Fallback for Twitter / Pinterest / Snapchat
                fetch('https://www.tikwm.com/api/?url=' + encodeURIComponent(videoUrl))
                .then(r => r.json())
                .then(resData => {
                    loader.style.display = 'none';
                    if (resData && resData.code === 0 && resData.data && (resData.data.play || resData.data.wmplay)) {
                        const d = resData.data;
                        let links = [];
                        if (d.play) links.push({ url: d.play, format: 'mp4', label: 'Download Media' });
                        if (d.music) links.push({ url: d.music, format: 'mp3', label: 'Download Audio' });
                        showResult({
                            title: d.title || 'Social Video',
                            thumbnail: d.cover || 'assets/images/placeholder.jpg',
                            duration: d.duration ? d.duration + 's' : '--',
                            size: '--',
                            platform: 'Social Media',
                            links: links
                        });
                    } else {
                        showFinalError(failMessage);
                    }
                })
                .catch(() => {
                    showFinalError(failMessage);
                });
            }

            function showFinalError(msg) {
                loader.style.display = 'none';
                alert(msg || 'Unable to fetch video. The media might be private, blocked, or not supported.');
            }
        });
    }

    function showResult(data) {
        document.getElementById('resThumbnail').src = data.thumbnail || 'assets/images/placeholder.jpg';
        document.getElementById('resTitle').innerText = data.title || 'Unknown Title';
        document.getElementById('resDuration').innerText = data.duration || 'N/A';
        document.getElementById('resSize').innerText = data.size || 'Unknown';
        document.getElementById('resPlatform').innerText = data.platform || 'Unknown';

        // populate links
        const linksContainer = document.getElementById('downloadLinks');
        linksContainer.innerHTML = '';

        if(data.links && data.links.length > 0) {
            data.links.forEach(link => {
                const a = document.createElement('a');
                a.href = 'download.php?url=' + encodeURIComponent(link.url) + '&name=' + encodeURIComponent(data.title) + '&format=' + encodeURIComponent(link.format);
                a.className = 'btn btn-outline-primary w-100 mb-2';
                
                let labelText = (link.label || 'Media').trim();
                if (/^download\s+/i.test(labelText)) {
                    labelText = labelText.replace(/^download\s+/i, '');
                }
                
                a.innerHTML = `<i class="fas fa-download"></i> Download ${labelText}`;
                a.target = '_blank';
                linksContainer.appendChild(a);
            });
        } else {
            linksContainer.innerHTML = '<p class="text-danger">No download links available.</p>';
        }

        resultBox.style.display = 'block';
    }
});
