(function() {
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

    openBtn.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        if (!panel.hidden) query.focus();
    });

    closeBtn.addEventListener('click', () => {
        panel.hidden = true;
    });

    function make(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function showVideo(video) {
        preview.replaceChildren();
        preview.hidden = false;

        const frameWrap = make('div', 'tc-yt-player');
        const frame = document.createElement('iframe');

        frame.src = 'https://www.youtube-nocookie.com/embed/' +
            encodeURIComponent(video.id);
        frame.title = video.title;
        frame.loading = 'lazy';
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        frame.allowFullscreen = true;

        frameWrap.append(frame);

        const share = make('button', 'tc-yt-share', 'Share in chat');
        share.type = 'button';

        share.addEventListener('click', () => {
            messageInput.value = 'https://www.youtube.com/watch?v=' + video.id;
            messageInput.focus();
            panel.hidden = true;
        });

        preview.append(frameWrap, share);
    }

    form.addEventListener('submit', async(event) => {
        event.preventDefault();

        const q = query.value.trim();
        if (!q) return;

        status.textContent = 'Searching YouTube...';
        results.replaceChildren();
        preview.replaceChildren();
        preview.hidden = true;
        try {
            const response = await fetch(
                '/tulipschat/youtube_search.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' }
            );

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Search fail hua.');
            }

            if (!Array.isArray(data.items) || data.items.length === 0) {
                status.textContent = 'Koi video nahi mila.';
                return;
            }

            status.textContent = data.items.length + ' results mile.';

            data.items.forEach((video) => {
                const card = make('article', 'tc-yt-result');
                const image = document.createElement('img');

                image.src = video.thumbnail;
                image.alt = '';
                image.loading = 'lazy';

                const info = make('div', 'tc-yt-info');
                info.append(
                    make('strong', '', video.title),
                    make('span', '', video.channel)
                );

                const select = make('button', '', 'Play');
                select.type = 'button';
                select.addEventListener('click', () => showVideo(video));

                const share = make('button', '', 'Share');
                share.type = 'button';
                share.addEventListener('click', () => {
                    messageInput.value =
                        'https://www.youtube.com/watch?v=' + video.id;
                    messageInput.focus();
                    panel.hidden = true;
                });

                card.append(image, info, select, share);
                results.append(card);
            });
        } catch (error) {
            status.textContent = error.message || 'Search fail hua.';
        }
    });
})();