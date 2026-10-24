(function() {
    'use strict';

    const openBtn = document.getElementById('tcYouTubeOpen');
    const panel = document.getElementById('tcYouTubePanel');

    if (!openBtn || !panel) return;

    const closeBtn = document.getElementById('tcYouTubeClose');
    const form = document.getElementById('tcYouTubeSearchForm');
    const query = document.getElementById('tcYouTubeQuery');
    const status = document.getElementById('tcYouTubeStatus');
    const results = document.getElementById('tcYouTubeResults');
    const preview = document.getElementById('tcYouTubePreview');
    const messageInput = document.getElementById('msgInput');

    if (!form || !query || !status || !results || !preview) return;

    openBtn.addEventListener('click', function() {
        panel.hidden = !panel.hidden;
        if (!panel.hidden) query.focus();
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            panel.hidden = true;
        });
    }

    function make(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function shareVideo(video) {
        if (!messageInput) {
            status.textContent = 'Chat message box nahi mila.';
            return;
        }

        messageInput.value =
            'https://www.youtube.com/watch?v=' +
            encodeURIComponent(video.id);

        messageInput.focus();
        panel.hidden = true;
    }

    function showVideo(video) {
        preview.replaceChildren();
        preview.hidden = false;

        const wrap = make('div', 'tc-yt-player');
        const frame = document.createElement('iframe');

        frame.src =
            'https://www.youtube-nocookie.com/embed/' +
            encodeURIComponent(video.id);

        frame.title = video.title || 'YouTube video';
        frame.loading = 'lazy';
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.allow =
            'accelerometer; autoplay; clipboard-write; encrypted-media; ' +
            'gyroscope; picture-in-picture; web-share';
        frame.allowFullscreen = true;

        wrap.appendChild(frame);

        const share = make('button', 'tc-yt-share', 'Share in chat');
        share.type = 'button';
        share.addEventListener('click', function() {
            shareVideo(video);
        });

        preview.append(wrap, share);
    }

    function showResults(items) {
        results.replaceChildren();

        items.forEach(function(video) {
            if (!video || !/^[A-Za-z0-9_-]{11}$/.test(video.id || '')) {
                return;
            }

            const card = make('article', 'tc-yt-result');
            const image = document.createElement('img');

            image.src = video.thumbnail || '';
            image.alt = '';
            image.loading = 'lazy';

            const info = make('div', 'tc-yt-info');
            info.append(
                make('strong', '', video.title || 'YouTube video'),
                make('span', '', video.channel || '')
            );

            const play = make('button', '', 'Play');
            play.type = 'button';
            play.addEventListener('click', function() {
                showVideo(video);
            });

            const share = make('button', '', 'Share');
            share.type = 'button';
            share.addEventListener('click', function() {
                shareVideo(video);
            });

            card.append(image, info, play, share);
            results.appendChild(card);
        });
    }

    form.addEventListener('submit', async function(event) {
        event.preventDefault();

        const q = query.value.trim();
        if (!q) {
            status.textContent = 'YouTube par search karne ke liye kuch likhein.';
            return;
        }

        status.textContent = 'Searching YouTube...';
        results.replaceChildren();
        preview.replaceChildren();
        preview.hidden = true;

        const searchButton = form.querySelector('[type="submit"]');
        if (searchButton) searchButton.disabled = true;

        try {
            // Local XAMPP aur Render dono ke liye relative URL.
            const endpoint = new URL(
                'youtube_search.php',
                window.location.href
            );

            endpoint.searchParams.set('q', q);

            const response = await fetch(endpoint.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            const contentType =
                response.headers.get('content-type') || '';

            const raw = await response.text();

            if (!contentType.toLowerCase().includes('application/json')) {
                console.error(
                    'YouTube endpoint ne JSON ke bajaye HTML diya:',
                    raw.slice(0, 500)
                );

                throw new Error(
                    'YouTube search endpoint nahi mil raha. ' +
                    'youtube_search.php aur server logs check karein.'
                );
            }

            let data;

            try {
                data = JSON.parse(raw);
            } catch (_) {
                throw new Error('Server ka JSON response invalid hai.');
            }

            if (!response.ok || data.error) {
                throw new Error(
                    data.error || 'YouTube search fail hua.'
                );
            }

            if (!Array.isArray(data.items) || data.items.length === 0) {
                status.textContent = 'Koi video nahi mila.';
                return;
            }

            showResults(data.items);
            status.textContent =
                results.childElementCount + ' results mile.';

        } catch (error) {
            console.error('YouTube search error:', error);
            status.textContent =
                error.message || 'Search fail hua. Dobara try karein.';
        } finally {
            if (searchButton) searchButton.disabled = false;
        }
    });
})();