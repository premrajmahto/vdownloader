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

                // 1. Instagram & Facebook Client Extraction (SnapSave Client API)
                if (lowerUrl.includes('instagram.com') || lowerUrl.includes('instagr.am') || lowerUrl.includes('facebook.com') || lowerUrl.includes('fb.watch')) {
                    const isIg = lowerUrl.includes('instagram.com') || lowerUrl.includes('instagr.am');
                    const targetPlatform = isIg ? 'Instagram' : 'Facebook';

                    fetch('https://snapsave.app/action.php?lang=en', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({ url: videoUrl })
                    })
                    .then(r => r.text())
                    .then(snapRes => {
                        const decodedHtml = decodeSnapSaveJS(snapRes);
                        if (decodedHtml) {
                            const links = [];
                            let title = targetPlatform + ' Video';
                            let thumb = 'assets/images/placeholder.jpg';

                            const titleMatch = decodedHtml.match(/<p[^>]*class=["\']video-des["\'][^>]*>(.*?)<\/p>/i) || decodedHtml.match(/<h4[^>]*class=["\']results-list-item-title["\'][^>]*>(.*?)<\/h4>/i);
                            if (titleMatch) title = titleMatch[1].replace(/<[^>]+>/g, '').trim();

                            const imgMatch = decodedHtml.match(/<img[^>]+src=["\']([^"\'\s]+)["\']/i);
                            if (imgMatch) thumb = imgMatch[1];

                            const aRegex = /<a[^>]+href=["\']([^"\'\s]+)["\'][^>]*>(.*?)<\/a>/gi;
                            let m;
                            while ((m = aRegex.exec(decodedHtml)) !== null) {
                                let linkUrl = m[1].replace(/&amp;/g, '&');
                                let btnText = m[2].replace(/<[^>]+>/g, '').trim();
                                if (linkUrl.startsWith('http') && !linkUrl.toLowerCase().includes('snapsave') && !btnText.toLowerCase().includes('app') && !linkUrl.match(/\.(jpg|png|webp)(\?|$)/i)) {
                                    links.push({
                                        url: linkUrl,
                                        format: 'mp4',
                                        label: 'Download ' + (btnText || ('Video ' + (links.length + 1)))
                                    });
                                }
                            }

                            if (links.length > 0) {
                                loader.style.display = 'none';
                                showResult({
                                    title: title,
                                    thumbnail: thumb,
                                    duration: '--',
                                    size: '--',
                                    platform: targetPlatform,
                                    links: links
                                });
                                return;
                            }
                        }
                        if (isIg) {
                            fallbackIgOembed(videoUrl, failMessage);
                        } else {
                            fallbackTikwm(videoUrl, failMessage);
                        }
                    })
                    .catch(() => {
                        if (isIg) {
                            fallbackIgOembed(videoUrl, failMessage);
                        } else {
                            fallbackTikwm(videoUrl, failMessage);
                        }
                    });
                    return;
                }

                // 2. TikTok via Tikwm Client API
                if (lowerUrl.includes('tiktok.com')) {
                    fallbackTikwm(videoUrl, failMessage);
                    return;
                }

                // 3. YouTube via oEmbed & Piped / Invidious Client API
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

                // 4. General Fallback for Twitter / Pinterest / Snapchat
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

            function fallbackTikwm(videoUrl, failMessage) {
                fetch('https://www.tikwm.com/api/?url=' + encodeURIComponent(videoUrl))
                .then(r => r.json())
                .then(resData => {
                    loader.style.display = 'none';
                    if (resData && resData.code === 0 && resData.data && (resData.data.play || resData.data.wmplay)) {
                        const d = resData.data;
                        let links = [];
                        if (d.play) links.push({ url: d.play, format: 'mp4', label: 'Download (No Watermark)' });
                        if (d.wmplay) links.push({ url: d.wmplay, format: 'mp4', label: 'Download (Watermark)' });
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
                        showFinalError(failMessage || "Unable to download video from this link. Please verify the URL.");
                    }
                })
                .catch(() => {
                    showFinalError(failMessage || "Unable to download video from this link. Please verify the URL.");
                });
            }

            function fallbackIgOembed(videoUrl, failMessage) {
                showFinalError(failMessage || "Unable to download video from this Instagram link. Please ensure the post or Reel is public and contains a video.");
            }

            function decodeSnapSaveJS(snapRes) {
                try {
                    const match = snapRes.match(/eval\(function\(h,u,n,t,e,r\)\{.*?\}\s*\(\s*["\'](.*?)["\']\s*,\s*(\d+)\s*,\s*["\'](.*?)["\']\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/s);
                    if (!match) return null;
                    let h_str = match[1], u_val = parseInt(match[2]), n_str = match[3], t_val = parseInt(match[4]), e_val = parseInt(match[5]);
                    const baseStr = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ+/";
                    
                    function decodeNum(d, e, f) {
                        let h_chars = baseStr.substring(0, e);
                        let i_chars = baseStr.substring(0, f);
                        let j = 0;
                        let d_arr = d.split('').reverse();
                        d_arr.forEach((b, c) => {
                            let pos = h_chars.indexOf(b);
                            if (pos !== -1) j += pos * Math.pow(e, c);
                        });
                        let k = "";
                        while (j > 0) {
                            k = i_chars[j % f] + k;
                            j = Math.floor((j - (j % f)) / f);
                        }
                        return k || "0";
                    }

                    let r_str = "";
                    let len = h_str.length;
                    let n_arr = n_str.split('');
                    let delimiter = n_str[e_val] || '';
                    let i = 0;
                    while (i < len) {
                        let s_chunk = "";
                        while (i < len && h_str[i] !== delimiter) {
                            s_chunk += h_str[i];
                            i++;
                        }
                        n_arr.forEach((b, j) => {
                            s_chunk = s_chunk.replaceAll(b, j.toString());
                        });
                        let val = parseInt(decodeNum(s_chunk, e_val, 10)) - t_val;
                        if (val > 0) r_str += String.fromCharCode(val);
                        i++;
                    }

                    return decodeURIComponent(r_str).replace(/\\\//g, '/');
                } catch (e) {
                    return null;
                }
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
