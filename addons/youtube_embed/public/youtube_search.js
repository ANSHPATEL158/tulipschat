(function() {
    'use strict';

    const openBtn = document.getElementById('tcYouTubeOpen');
    const panel = document.getElementById('tcYouTubePanel');

    if (!openBtn || !panel) return;

    document.body.appendChild(panel);
    panel.classList.add('tc-yt-modal');

    const closeBtn = document.getElementById('tcYouTubeClose');
    const form = document.getElementById('tcYouTubeSearchForm');
    const query = document.getElementById('tcYouTubeQuery');
    const status = document.getElementById('tcYouTubeStatus');
    const results = document.getElementById('tcYouTubeResults');
    const preview = document.getElementById('tcYouTubePreview');

    if (!form || !query || !status || !results || !preview) return;

    openBtn.addEventListener('click', function() {
        const klipyPanel = document.getElementById('tcKlipyPanel');
        if (klipyPanel) klipyPanel.hidden = true;

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

    // Video ko typing box mein daalne ke bajaye seedha chat mein bhejo.
    async function shareVideo(video) {
        if (!video || !/^[A-Za-z0-9_-]{11}$/.test(video.id || '')) {
            status.textContent = 'YouTube video ID invalid hai.';
            return;
        }

        const sendMessage = window.TC_SEND_CHAT_MESSAGE;

        if (typeof sendMessage !== 'function') {
            status.textContent =
                'Direct send setup nahi mila. assets/chat.js mein helper add karein.';
            console.error('window.TC_SEND_CHAT_MESSAGE is not available.');
            return;
        }

        const url = 'https://www.youtube.com/watch?v=' +
            encodeURIComponent(video.id);

        status.textContent = 'Video chat mein send ho raha hai...';

        try {
            const sent = await sendMessage(url);

            if (sent) {
                status.textContent = 'YouTube video chat mein send ho gaya.';
                panel.hidden = true;
                preview.hidden = true;
                preview.replaceChildren();
            } else {
                status.textContent =
                    'Video send nahi hua. Dobara try karein.';
            }
        } catch (error) {
            console.error('YouTube direct send error:', error);
            status.textContent =
                error.message || 'Video send nahi hua. Dobara try karein.';
        }
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
            status.textContent =
                'YouTube par search karne ke liye kuch likhein.';
            return;
        }

        status.textContent = 'Searching YouTube...';
        results.replaceChildren();
        preview.replaceChildren();
        preview.hidden = true;

        const searchButton = form.querySelector('[type="submit"]');
        if (searchButton) searchButton.disabled = true;

        try {
            const endpoint = new URL(
                '/youtube_search.php',
                window.location.origin
            );

            endpoint.searchParams.set('q', q);

            const response = await fetch(endpoint.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });

            const contentType =
                response.headers.get('content-type') || '';
            const raw = await response.text();

            console.log('YouTube response:', response.status, contentType, raw.slice(0, 1000));
            if (!contentType.toLowerCase().includes('application/json')) {
                console.error(
                    'YouTube endpoint returned non-JSON:',
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
                throw new Error(data.error || 'YouTube search fail hua.');
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