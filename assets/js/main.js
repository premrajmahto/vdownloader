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
                loader.style.display = 'none';
                if (data.success) {
                    showResult(data.data);
                } else {
                    alert(data.message || 'Unable to fetch video. The media might be private or blocked.');
                }
            })
            .catch(error => {
                console.warn('Backend fetch failed, attempting client fallback...', error);
                fallbackCobalt(url);
            });
            
            function fallbackCobalt(videoUrl) {
                fetch('https://api.cobalt.tools/', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ url: videoUrl })
                })
                .then(res => {
                    if (!res.ok) throw new Error("API rate limited or blocked");
                    return res.json();
                })
                .then(data => {
                    loader.style.display = 'none';
                    if (data && data.status) {
                        let links = [];
                        if (data.status === 'stream' || data.status === 'redirect') {
                            links.push({ url: data.url, format: 'mp4', label: 'Download Media' });
                        } else if (data.status === 'picker') {
                            data.picker.forEach((item, i) => {
                                links.push({ url: item.url || item, format: 'mp4', label: 'Download ' + (i+1) });
                            });
                        }
                        
                        if (links.length > 0) {
                            showResult({
                                title: 'Social Media Video',
                                thumbnail: 'assets/images/placeholder.jpg',
                                duration: '--',
                                size: '--',
                                platform: 'Extracted Download',
                                links: links
                            });
                            return;
                        }
                    }
                    alert('Unable to fetch video. The media might be private, blocked, or not supported.');
                })
                .catch(err => {
                    loader.style.display = 'none';
                    console.error("Client fallback error:", err);
                    alert('Unable to fetch video. Please ensure the link is public and valid.');
                });
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
                a.innerHTML = `<i class="fas fa-download"></i> Download ${link.label}`;
                a.target = '_blank';
                linksContainer.appendChild(a);
            });
        } else {
            linksContainer.innerHTML = '<p class="text-danger">No download links available.</p>';
        }

        resultBox.style.display = 'block';
    }
});
