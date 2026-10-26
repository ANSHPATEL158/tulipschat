(function() {
    'use strict';

    const API_KEY = String(window.TC_KLIPY_APP_KEY || '').trim();
    const API_BASE = 'https://api.klipy.com/api/v1/';

    function getItems(payload) {
        if (!payload || typeof payload !== 'object') return [];

        const data = payload.data;

        if (Array.isArray(data)) return data;
        if (data && Array.isArray(data.data)) return data.data;
        if (data && Array.isArray(data.gifs)) return data.gifs;
        if (data && Array.isArray(data.results)) return data.results;
        if (Array.isArray(payload.results)) return payload.results;
        if (Array.isArray(payload.gifs)) return payload.gifs;

        return [];
    }

    function getGifUrl(item) {
        if (!item || typeof item !== 'object') return '';

        const file = item.file || {};

        const formats = [
            file.md && file.md.gif,
            file.hd && file.hd.gif,
            file.sm && file.sm.gif,
            file.xs && file.xs.gif,
            file.md && file.md.webp,
            file.hd && file.hd.webp,
            file.sm && file.sm.webp,
            file.xs && file.xs.webp
        ];

        for (const format of formats) {
            if (
                format &&
                typeof format.url === 'string' &&
                format.url.startsWith('https://')
            ) {
                return format.url;
            }
        }

        return '';
    }

    function isKlipyUrl(value) {
        try {
            const url = new URL(value);

            return url.protocol === 'https:' &&
                (
                    url.hostname === 'klipy.com' ||
                    url.hostname.endsWith('.klipy.com')
                );
        } catch (error) {
            return false;
        }
    }

    function init() {
        const form = document.getElementById('sendForm');
        const input = document.getElementById('msgInput');

        if (!form || !input) return;

        // Prevent duplicate buttons if the script is loaded twice.
        if (document.getElementById('tcKlipyButton')) return;

        // Create the KLIPY button regardless of API-key availability.
        const button = document.createElement('button');
        button.type = 'button';
        button.id = 'tcKlipyButton';
        button.className = 'tc-klipy-button';
        button.title = 'Search KLIPY GIFs';
        button.setAttribute('aria-label', 'Search KLIPY GIFs');

        button.innerHTML = `
            <svg class="tc-klipy-icon"
                 viewBox="0 0 24 24"
                 width="20"
                 height="20"
                 aria-hidden="true">
                <rect x="2" y="4" width="20" height="16"
                      rx="4" fill="none"
                      stroke="currentColor" stroke-width="1.8"/>
                <text x="12" y="14.5"
                      text-anchor="middle"
                      font-size="7.5"
                      font-weight="800"
                      fill="currentColor">GIF</text>
            </svg>
            <span>KLIPY</span>
        `;

        const panel = document.createElement('section');
        panel.id = 'tcKlipyPanel';
        panel.className = 'tc-klipy-panel';
        panel.hidden = true;

        panel.innerHTML = `
            <div class="tc-klipy-heading">
                <strong>KLIPY GIFs</strong>
                <button type="button" data-close
                        aria-label="Close GIF search">×</button>
            </div>

            <form class="tc-klipy-search">
                <input type="search"
                       placeholder="Search GIFs..."
                       maxlength="100"
                       aria-label="Search KLIPY GIFs"
                       required>
                <button type="submit">Search</button>
            </form>

            <p class="tc-klipy-status" role="status">
                Ready to search GIFs.
            </p>

            <div class="tc-klipy-results"></div>
            <div class="tc-klipy-brand">Powered by KLIPY</div>
        `;

        const sendButton = form.querySelector('button[type="submit"]');

        if (sendButton) {
            form.insertBefore(button, sendButton);
        } else {
            form.appendChild(button);
        }

        form.parentNode.insertBefore(panel, form.nextSibling);

        const searchForm = panel.querySelector('.tc-klipy-search');
        const searchInput = searchForm.querySelector('input');
        const status = panel.querySelector('.tc-klipy-status');
        const results = panel.querySelector('.tc-klipy-results');

        async function search(query) {
            results.replaceChildren();

            const cleanQuery = String(query || '').trim();

            // The UI stays available even when the API key is missing.
            if (!API_KEY) {
                status.textContent =
                    'KLIPY API key configure nahi hai. Admin Settings mein key save karein.';
                return;
            }

            status.textContent = 'Loading GIFs...';

            const endpoint = cleanQuery ?
                'gifs/search' :
                'gifs/trending';

            const url = new URL(
                encodeURIComponent(API_KEY) + '/' + endpoint,
                API_BASE
            );

            url.searchParams.set('page', '1');
            url.searchParams.set('per_page', '18');
            url.searchParams.set('locale', 'IN');

            if (cleanQuery) {
                url.searchParams.set('q', cleanQuery);
            }

            try {
                const response = await fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error('KLIPY API HTTP ' + response.status);
                }

                const payload = await response.json();

                if (
                    payload &&
                    (
                        payload.result === false ||
                        payload.success === false
                    )
                ) {
                    throw new Error(
                        'KLIPY API returned an unsuccessful result'
                    );
                }

                const items = getItems(payload);

                if (!items.length) {
                    status.textContent =
                        'GIFs nahi mile. API response ya key access check karein.';
                    return;
                }

                status.textContent = cleanQuery ?
                    'Search results' :
                    'Trending GIFs';

                items.forEach(function(item) {
                    const src = getGifUrl(item);

                    if (!src) return;

                    const choice = document.createElement('button');
                    choice.type = 'button';
                    choice.className = 'tc-klipy-choice';
                    choice.title = 'Select this GIF';

                    const image = document.createElement('img');
                    image.src = src;
                    image.alt = item.title || 'KLIPY GIF';
                    image.loading = 'lazy';

                    choice.appendChild(image);

                    choice.addEventListener('click', function() {
                        input.value = src;
                        input.focus();
                        panel.hidden = true;
                    });

                    results.appendChild(choice);
                });

                if (!results.children.length) {
                    status.textContent =
                        'GIF URLs nahi mile. KLIPY API response format check karein.';
                }
            } catch (error) {
                console.error('KLIPY search failed:', error);

                status.textContent =
                    'GIFs load nahi hue. API key, access ya network check karein.';
            }
        }

        button.addEventListener('click', function() {
            panel.hidden = !panel.hidden;

            if (!panel.hidden) {
                if (!results.children.length) {
                    search('');
                }

                searchInput.focus();
            }
        });

        panel.querySelector('[data-close]').addEventListener(
            'click',
            function() {
                panel.hidden = true;
            }
        );

        searchForm.addEventListener('submit', function(event) {
            event.preventDefault();
            search(searchInput.value);
        });

        // Register the KLIPY message renderer.
        window.TC_CHAT_ADDONS = window.TC_CHAT_ADDONS || {};
        window.TC_CHAT_ADDONS.renderers =
            window.TC_CHAT_ADDONS.renderers || [];

        window.TC_CHAT_ADDONS.renderers.push(function(body, message) {
            const text = message && message.text;

            if (typeof text !== 'string') return false;

            const gifUrl = text.trim();

            if (!isKlipyUrl(gifUrl)) return false;

            const image = document.createElement('img');
            image.src = gifUrl;
            image.alt = 'KLIPY GIF';
            image.loading = 'lazy';
            image.className = 'tc-klipy-message';

            body.appendChild(image);
            return true;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();