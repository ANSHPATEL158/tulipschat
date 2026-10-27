/*
 * Private Messages: header dropdown + floating PM windows
 * File: assets/pm.js
 * Load order: chat.php mein chat.js ke BAAD.
 * Backend: ajax.php (pm_list, pm_fetch, pm_send, pm_read, pm_read_all,
 *          pm_hide, pm_hide_all, upload with `to`).
 */
(function() {
    'use strict';

    const btn = document.getElementById('pmBtn');
    const badge = document.getElementById('pmBadge');
    const dropdown = document.getElementById('pmDropdown');
    const winsBox = document.getElementById('pmWindows');

    if (!btn || !badge || !dropdown || !winsBox || !window.AK) {
        return;
    }

    const POLL = Math.max(1500, Number(AK.poll) || 2000);
    const MAX_WINDOWS = 3;
    const mainInput = document.getElementById('msgInput');
    const MAX_LEN = mainInput && mainInput.maxLength > 0 ? mainInput.maxLength : 500;

    const EMOJIS = [
        '😀', '😁', '😂', '🤣', '😊', '😍', '😘', '😎',
        '🥰', '😉', '🙂', '🤗', '🤔', '😏', '😴', '😢',
        '😭', '😡', '😱', '🥳', '😇', '🤩', '😋', '🙄',
        '👍', '👎', '👏', '🙏', '💪', '🤝', '👋', '✌️',
        '❤️', '💔', '🔥', '✨', '🎉', '💯', '🌹', '🌷',
        '☕', '🍕', '🎂', '🎶', '🌙', '⭐', '☀️', '🌈'
    ];

    // Material-style 24x24 icon paths (static, safe).
    const ICONS = {
        mail: 'M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z',
        trash: 'M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z',
        checkbox: 'M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z',
        doneall: 'M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z',
        close: 'M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z',
        fullscreen: 'M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z',
        minus: 'M19 13H5v-2h14v2z',
        phone: 'M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z',
        gear: 'M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z',
        attach: 'M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z',
        smile: 'M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z',
        mic: 'M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z',
        send: 'M2.01 21L23 12 2.01 3 2 10l15 2-15 2z'
    };

    const state = { convos: [], unread: 0, open: false };
    const windows = new Map(); // userId -> window object

    /* ------------------------------------------------------------------ */
    /* Small helpers                                                       */
    /* ------------------------------------------------------------------ */

    function el(tag, cls, text) {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function icon(name, size) {
        const s = Number(size) || 20;
        const wrap = el('span', 'pm-ico');
        wrap.innerHTML =
            '<svg viewBox="0 0 24 24" width="' + s + '" height="' + s +
            '" fill="currentColor" aria-hidden="true"><path d="' +
            (ICONS[name] || '') + '"/></svg>';
        return wrap;
    }

    function iconBtn(name, title, onClick, cls) {
        const b = el('button', 'pm-btn' + (cls ? ' ' + cls : ''));
        b.type = 'button';
        b.title = title;
        b.setAttribute('aria-label', title);
        b.append(icon(name, 20));
        b.addEventListener('click', onClick);
        return b;
    }

    function avatarEl(src, cls, online) {
        const wrap = el('span', 'pm-av-wrap');
        const img = el('img', cls || 'pm-av');
        img.alt = '';
        img.src = src || 'assets/default.svg';
        img.onerror = function() {
            img.onerror = null;
            img.src = 'assets/default.svg';
        };
        wrap.append(img);
        if (online) wrap.append(el('span', 'pm-online'));
        return wrap;
    }

    function api(act, params) {
        const qs = new URLSearchParams(Object.assign({ act: act }, params || {}));
        return fetch('ajax.php?' + qs.toString(), { cache: 'no-store' })
            .then(function(r) { return r.json(); });
    }

    function post(act, data) {
        const fd = new FormData();
        Object.entries(data || {}).forEach(function(pair) {
            fd.append(pair[0], pair[1]);
        });
        return fetch('ajax.php?act=' + encodeURIComponent(act), {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF': AK.csrf }
        }).then(function(r) { return r.json(); });
    }

    function loggedOut(res) {
        if (res && res.error === 'logout') {
            location.href = 'login.php';
            return true;
        }
        return false;
    }

    function nearBottom(node) {
        return node.scrollHeight - node.scrollTop - node.clientHeight < 80;
    }

    function fileInfo(text) {
        if (typeof text !== 'string') return null;
        const m = text.match(
            /^__TC_FILE__:(image|video|document):(uploads\/chat\/[a-f0-9]{32}\.(jpg|png|gif|webp|mp4|webm|mov|pdf|txt|docx|xlsx|pptx))$/i
        );
        return m ? { kind: m[1].toLowerCase(), path: m[2] } : null;
    }

    /* ------------------------------------------------------------------ */
    /* Header badge + dropdown                                             */
    /* ------------------------------------------------------------------ */

    function renderBadge() {
        const n = state.unread;
        badge.hidden = n < 1;
        badge.textContent = n > 99 ? '99+' : String(n);
    }

    function renderDropdown() {
        const prev = dropdown.querySelector('.pm-dd-list');
        const keepScroll = prev ? prev.scrollTop : 0;

        dropdown.textContent = '';

        const head = el('div', 'pm-dd-head');
        head.append(el('span', 'pm-dd-title', 'Private'));

        const tools = el('div', 'pm-dd-tools');
        tools.append(
            iconBtn('checkbox', 'Sab chats read mark karein', onReadAll),
            iconBtn('trash', 'Saari chats hatayein', onHideAll)
        );
        head.append(tools);
        dropdown.append(head);

        const list = el('div', 'pm-dd-list');

        if (!state.convos.length) {
            list.append(el(
                'div',
                'pm-empty',
                'Abhi koi private chat nahi hai. Online list mein kisi user ke envelope par click karke chat shuru karein.'
            ));
        }

        state.convos.forEach(function(c) {
            const item = el('div', 'pm-item' + (c.unread ? ' unread' : ''));
            item.tabIndex = 0;
            item.setAttribute('role', 'button');

            const info = el('div', 'pm-item-info');
            info.append(
                el('div', 'pm-item-name' + (c.role === 'admin' ? ' is-admin' : ''), c.name),
                el('div', 'pm-item-prev', (c.mine ? 'You: ' : '') + c.preview)
            );

            item.append(avatarEl(c.avatar, 'pm-av', c.online), info);

            if (c.unread) {
                item.append(el('span', 'pm-count', c.unread > 99 ? '99+' : c.unread));
            }

            const x = el('button', 'pm-x');
            x.type = 'button';
            x.title = 'Chat hatayein';
            x.setAttribute('aria-label', 'Chat hatayein');
            x.append(icon('close', 18));
            x.addEventListener('click', function(ev) {
                ev.stopPropagation();
                hideConvo(c.id, true);
            });
            item.append(x);

            const openIt = function() {
                openWindow({ id: c.id, name: c.name, avatar: c.avatar });
                closeDropdown();
            };
            item.addEventListener('click', openIt);
            item.addEventListener('keydown', function(ev) {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    ev.preventDefault();
                    openIt();
                }
            });

            list.append(item);
        });

        dropdown.append(list);
        list.scrollTop = keepScroll;
    }

    function openDropdown() {
        state.open = true;
        dropdown.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
        renderDropdown();
        pollList();
    }

    function closeDropdown() {
        state.open = false;
        dropdown.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
    }

    btn.addEventListener('click', function() {
        if (state.open) closeDropdown();
        else openDropdown();
    });

    function onReadAll() {
        post('pm_read_all').then(function() {
            windows.forEach(function(w) {
                w.unread = 0;
                w.root.classList.remove('has-unread');
            });
            pollList();
        }).catch(function() {});
    }

    function onHideAll() {
        if (!confirm('Saari private chats hata dein? Ye sirf aapke liye hatengi.')) return;

        post('pm_hide_all').then(function(res) {
            if (res && res.error) { alert(res.error); return; }
            Array.from(windows.keys()).forEach(closeWindow);
            state.convos = [];
            state.unread = 0;
            renderBadge();
            renderDropdown();
        }).catch(function() {
            alert('Chats nahi hati. Dobara try karein.');
        });
    }

    function hideConvo(id, ask) {
        if (ask && !confirm('Is chat ko hata dein? Ye sirf aapke liye hategi.')) return;

        state.convos = state.convos.filter(function(c) { return c.id !== id; });
        renderDropdown();
        closeWindow(id);

        post('pm_hide', { with: id }).then(function() {
            pollList();
        }).catch(function() {});
    }

    let listBusy = false;
    let prevUnread = -1;

    function pollList() {
        if (listBusy) return;
        listBusy = true;

        api('pm_list').then(function(res) {
            if (loggedOut(res) || res.error) return;

            state.convos = res.conversations || [];
            state.unread = Number(res.unread) || 0;
            renderBadge();
            if (state.open) renderDropdown();

            if (prevUnread >= 0 && state.unread > prevUnread) {
                btn.classList.add('pm-ring');
                setTimeout(function() { btn.classList.remove('pm-ring'); }, 1300);
            }
            prevUnread = state.unread;
        }).catch(function() {
            /* network error: agli poll mein dobara try hoga */
        }).finally(function() {
            listBusy = false;
        });
    }

    /* ------------------------------------------------------------------ */
    /* PM window                                                           */
    /* ------------------------------------------------------------------ */

    function openWindow(user) {
        const uid = Number(user && user.id);
        if (!uid || uid === Number(AK.me)) return null;

        let w = windows.get(uid);

        if (w) {
            if (w.minimized) setMinimized(w, false);
            w.input.focus();
            return w;
        }

        const limit = window.innerWidth < 600 ? 1 : MAX_WINDOWS;
        while (windows.size >= limit) {
            closeWindow(windows.keys().next().value);
        }

        w = buildWindow(uid, user);
        windows.set(uid, w);
        winsBox.append(w.root);
        pollWindow(w, true);
        w.input.focus();

        return w;
    }

    function closeWindow(uid) {
        const w = windows.get(uid);
        if (!w) return;
        w.closed = true;
        w.root.remove();
        windows.delete(uid);
    }

    function buildWindow(uid, user) {
        const w = {
            uid: uid,
            name: user.name || 'User',
            avatar: user.avatar || '',
            lastId: 0,
            busy: false,
            again: false,
            closed: false,
            sending: false,
            minimized: false,
            unread: 0,
            rows: new Map(),
            toastTimer: null
        };

        const root = el('section', 'pm-win');
        root.setAttribute('aria-label', 'Private chat with ' + w.name);
        w.root = root;

        /* ---- header ---- */
        const head = el('div', 'pm-head');
        w.avatarWrap = avatarEl(w.avatar, 'pm-av', false);

        const title = el('div', 'pm-title');
        w.nameEl = el('span', 'pm-title-name', w.name);
        w.subEl = el('span', 'pm-title-sub', '');
        title.append(w.nameEl, w.subEl);

        const tools = el('div', 'pm-head-tools');
        w.bFull = iconBtn('fullscreen', 'Fullscreen', function() { toggleFull(w); });
        w.bMin = iconBtn('minus', 'Minimize', function() { setMinimized(w, !w.minimized); });
        w.bCall = iconBtn('phone', 'Call (jald aa raha hai)', function() {
            toast(w, 'Call feature abhi available nahi hai.');
        }, 'pm-off');
        w.bSet = iconBtn('gear', 'Settings', function() { w.menu.hidden = !w.menu.hidden; });
        w.bClose = iconBtn('close', 'Close', function() { closeWindow(uid); });
        tools.append(w.bFull, w.bMin, w.bCall, w.bSet, w.bClose);

        head.append(w.avatarWrap, title, tools);

        head.addEventListener('click', function(ev) {
            if (w.minimized && !tools.contains(ev.target)) setMinimized(w, false);
        });

        /* ---- settings menu ---- */
        const menu = el('div', 'pm-menu');
        menu.hidden = true;

        const mProfile = el('a', 'pm-menu-item', 'Profile dekhein');
        mProfile.href = 'profile.php?id=' + encodeURIComponent(uid);

        const mClear = el('button', 'pm-menu-item danger', 'Chat hatayein');
        mClear.type = 'button';
        mClear.addEventListener('click', function() { hideConvo(uid, true); });

        menu.append(mProfile, mClear);
        w.menu = menu;

        /* ---- messages ---- */
        const body = el('div', 'pm-body');
        w.body = body;
        w.hello = el('div', 'pm-hello', w.name + ' ko pehla message bhejein.');
        body.append(w.hello);

        /* ---- emoji picker ---- */
        const emojiBox = el('div', 'pm-emoji');
        emojiBox.hidden = true;
        EMOJIS.forEach(function(emo) {
            const b = el('button', 'pm-emo', emo);
            b.type = 'button';
            b.addEventListener('click', function() {
                insertAtCursor(w.input, emo);
            });
            emojiBox.append(b);
        });
        w.emojiBox = emojiBox;

        /* ---- footer ---- */
        const foot = el('div', 'pm-foot');

        const fileInput = el('input');
        fileInput.type = 'file';
        fileInput.hidden = true;
        fileInput.accept =
            'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime,.pdf,.txt,.docx,.xlsx,.pptx';
        fileInput.addEventListener('change', function() {
            const f = fileInput.files && fileInput.files[0];
            fileInput.value = '';
            if (f) uploadFile(w, f);
        });

        w.bAttach = iconBtn('attach', 'File bhejein', function() { fileInput.click(); });
        w.bEmoji = iconBtn('smile', 'Emoji', function() { emojiBox.hidden = !emojiBox.hidden; });

        const input = el('input', 'pm-input');
        input.type = 'text';
        input.placeholder = 'Type here...';
        input.maxLength = MAX_LEN;
        input.autocomplete = 'off';
        input.setAttribute('aria-label', 'Message likhein');
        input.addEventListener('keydown', function(ev) {
            if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing) {
                ev.preventDefault();
                sendText(w);
            }
        });
        input.addEventListener('focus', function() {
            if (w.unread && !w.minimized) markRead(w);
        });
        w.input = input;

        w.bMic = iconBtn('mic', 'Voice message (jald aa raha hai)', function() {
            toast(w, 'Voice message abhi available nahi hai.');
        }, 'pm-off');
        w.bSend = iconBtn('send', 'Send', function() { sendText(w); }, 'pm-send');

        foot.append(fileInput, w.bAttach, w.bEmoji, input, w.bMic, w.bSend);

        w.toastEl = el('div', 'pm-toast');
        w.toastEl.hidden = true;

        root.append(head, menu, body, emojiBox, w.toastEl, foot);
        return w;
    }

    function insertAtCursor(input, text) {
        const s = typeof input.selectionStart === 'number' ? input.selectionStart : input.value.length;
        const e = typeof input.selectionEnd === 'number' ? input.selectionEnd : s;
        input.value = input.value.slice(0, s) + text + input.value.slice(e);
        const pos = s + text.length;
        input.focus();
        try { input.setSelectionRange(pos, pos); } catch (err) { /* ignore */ }
    }

    function toast(w, message) {
        w.toastEl.textContent = message;
        w.toastEl.hidden = false;
        clearTimeout(w.toastTimer);
        w.toastTimer = setTimeout(function() { w.toastEl.hidden = true; }, 2600);
    }

    function toggleFull(w) {
        const on = w.root.classList.toggle('full');
        if (on && w.minimized) setMinimized(w, false);
        w.bFull.title = on ? 'Fullscreen band karein' : 'Fullscreen';
        scrollDown(w);
    }

    function setMinimized(w, on) {
        w.minimized = on;
        w.root.classList.toggle('min', on);
        if (on) {
            w.root.classList.remove('full');
            w.menu.hidden = true;
            w.emojiBox.hidden = true;
            return;
        }
        scrollDown(w);
        w.input.focus();
        if (w.unread) markRead(w);
    }

    function scrollDown(w) {
        w.body.scrollTop = w.body.scrollHeight;
    }

    /* ---- rendering ---- */

    function renderFile(box, text, onMedia) {
        const info = fileInfo(text);
        if (!info) return false;

        if (info.kind === 'image') {
            const img = el('img', 'pm-img');
            img.src = info.path;
            img.alt = 'Image';
            img.loading = 'lazy';
            img.addEventListener('load', onMedia);
            img.addEventListener('error', function() {
                img.replaceWith(el('div', 'pm-text', 'Image unavailable'));
            });
            img.addEventListener('click', function() {
                window.open(info.path, '_blank', 'noopener,noreferrer');
            });
            box.append(img);
            return true;
        }

        if (info.kind === 'video') {
            const v = el('video', 'pm-video');
            v.src = info.path;
            v.controls = true;
            v.preload = 'metadata';
            v.addEventListener('loadedmetadata', onMedia);
            v.addEventListener('error', function() {
                v.replaceWith(el('div', 'pm-text', 'Video unavailable'));
            });
            box.append(v);
            return true;
        }

        const a = el('a', 'pm-doc', '📄 Open / Download document');
        a.href = info.path;
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        box.append(a);
        return true;
    }

    function addMsg(w, m) {
        const id = Number(m.id);
        if (w.rows.has(id)) return;

        if (w.hello) {
            w.hello.remove();
            w.hello = null;
        }

        const row = el('div', 'pm-row ' + (m.mine ? 'mine' : 'theirs'));
        row.dataset.id = String(id);

        if (!m.mine) {
            row.append(avatarEl(w.avatar, 'pm-row-av', false));
        }

        const col = el('div', 'pm-col');
        const bubble = el('div', 'pm-bubble');

        const keepBottom = function() {
            if (w.stick) scrollDown(w);
        };

        if (!renderFile(bubble, m.text, keepBottom)) {
            bubble.append(el('div', 'pm-text', m.text || ''));
        }

        const meta = el('div', 'pm-meta');
        meta.append(el('span', 'pm-time', m.time || ''));

        if (m.mine) {
            const tick = el('span', 'pm-tick' + (m.seen ? ' seen' : ''));
            tick.title = m.seen ? 'Seen' : 'Sent';
            tick.append(icon('doneall', 15));
            meta.append(tick);
            row._tick = tick;
        }

        col.append(bubble, meta);
        row.append(col);
        w.body.append(row);
        w.rows.set(id, row);
    }

    function applySeen(w, ids) {
        (ids || []).forEach(function(id) {
            const row = w.rows.get(Number(id));
            if (row && row._tick && !row._tick.classList.contains('seen')) {
                row._tick.classList.add('seen');
                row._tick.title = 'Seen';
            }
        });
    }

    function updateUnreadDot(w) {
        w.root.classList.toggle('has-unread', w.unread > 0);
    }

    /* ---- network ---- */

    function pollWindow(w, force) {
        if (w.closed) return;
        if (w.busy) { w.again = true; return; }
        if (document.hidden && !force) return;

        w.busy = true;

        api('pm_fetch', { with: w.uid, last: w.lastId }).then(function(res) {
            if (w.closed || loggedOut(res)) return;

            if (res.error) {
                if (w.lastId === 0) toast(w, res.error);
                return;
            }

            if (res.user) {
                w.subEl.textContent = res.user.online ? 'Online' : '';
            }

            w.stick = nearBottom(w.body) || w.lastId === 0;

            let incoming = 0;

            (res.messages || []).forEach(function(m) {
                addMsg(w, m);
                w.lastId = Math.max(w.lastId, Number(m.id));
                if (!m.mine && !m.seen) incoming++;
            });

            applySeen(w, res.seen);

            if (w.stick) scrollDown(w);

            if (incoming) {
                if (!w.minimized && !document.hidden) {
                    markRead(w);
                } else {
                    w.unread += incoming;
                    updateUnreadDot(w);
                }
            }
        }).catch(function() {
            /* network error: agli poll mein dobara try hoga */
        }).finally(function() {
            w.busy = false;
            if (w.again) {
                w.again = false;
                pollWindow(w, true);
            }
        });
    }

    function markRead(w) {
        w.unread = 0;
        updateUnreadDot(w);
        post('pm_read', { with: w.uid }).then(pollList).catch(function() {});
    }

    async function sendText(w) {
        const text = w.input.value.trim();
        if (!text || w.sending) return;

        w.sending = true;
        w.input.value = '';
        w.emojiBox.hidden = true;

        try {
            const res = await post('pm_send', { to: w.uid, message: text });

            if (loggedOut(res)) return;

            if (res.error) {
                toast(w, res.error);
                w.input.value = text;
                return;
            }

            w.stick = true;
            pollWindow(w, true);
            pollList();
        } catch (err) {
            toast(w, 'Message send nahi hua. Dobara try karein.');
            w.input.value = text;
        } finally {
            w.sending = false;
            w.input.focus();
        }
    }

    async function uploadFile(w, file) {
        const name = file.name.toLowerCase();
        let max;
        let kind;

        if (/\.(jpg|jpeg|png|gif|webp)$/.test(name)) {
            max = 5 * 1024 * 1024;
            kind = 'Image';
        } else if (/\.(mp4|webm|mov)$/.test(name)) {
            max = 25 * 1024 * 1024;
            kind = 'Video';
        } else if (/\.(pdf|txt|docx|xlsx|pptx)$/.test(name)) {
            max = 10 * 1024 * 1024;
            kind = 'Document';
        } else {
            toast(w, 'Supported image, video ya document file chunein.');
            return;
        }

        if (file.size > max) {
            toast(w, kind + ' file size limit se badi hai.');
            return;
        }

        toast(w, 'Upload ho raha hai...');

        try {
            const res = await post('upload', { file: file, to: w.uid });

            if (loggedOut(res)) return;

            if (res.error) {
                toast(w, res.error);
                return;
            }

            w.toastEl.hidden = true;
            w.stick = true;
            pollWindow(w, true);
            pollList();
        } catch (err) {
            toast(w, 'Upload nahi hua. Dobara try karein.');
        }
    }

    /* ------------------------------------------------------------------ */
    /* Online users list: har user ke saamne envelope button               */
    /* ------------------------------------------------------------------ */

    const usersBox = document.getElementById('users');

    function decorateUsers() {
        if (!usersBox) return;

        usersBox.querySelectorAll('a.user').forEach(function(a) {
            if (a.querySelector('.pm-user-btn')) return;

            const m = (a.getAttribute('href') || '').match(/[?&]id=(\d+)/);
            if (!m) return;

            const id = Number(m[1]);
            if (id === Number(AK.me)) return;

            const b = el('button', 'pm-user-btn');
            b.type = 'button';
            b.title = 'Private message';
            b.setAttribute('aria-label', 'Private message bhejein');
            b.append(icon('mail', 18));

            b.addEventListener('click', function(ev) {
                ev.preventDefault();
                ev.stopPropagation();

                const nameEl = a.querySelector('.uname');
                const avEl = a.querySelector('img.av');

                openWindow({
                    id: id,
                    name: nameEl ? nameEl.textContent : 'User',
                    avatar: avEl ? avEl.getAttribute('src') : ''
                });
            });

            const dot = a.querySelector('.dot');
            if (dot) a.insertBefore(b, dot);
            else a.append(b);
        });
    }

    if (usersBox) {
        new MutationObserver(decorateUsers).observe(usersBox, { childList: true });
        decorateUsers();
    }

    /* ------------------------------------------------------------------ */
    /* Global listeners + polling                                          */
    /* ------------------------------------------------------------------ */

    document.addEventListener('click', function(ev) {
        if (state.open && !dropdown.contains(ev.target) && !btn.contains(ev.target)) {
            closeDropdown();
        }

        windows.forEach(function(w) {
            if (!w.menu.hidden && !w.menu.contains(ev.target) && !w.bSet.contains(ev.target)) {
                w.menu.hidden = true;
            }
            if (!w.emojiBox.hidden && !w.emojiBox.contains(ev.target) && !w.bEmoji.contains(ev.target)) {
                w.emojiBox.hidden = true;
            }
        });
    });

    document.addEventListener('keydown', function(ev) {
        if (ev.key !== 'Escape') return;

        if (state.open) closeDropdown();

        windows.forEach(function(w) {
            w.menu.hidden = true;
            w.emojiBox.hidden = true;
        });
    });

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) return;

        pollList();
        windows.forEach(function(w) {
            pollWindow(w, true);
            if (w.unread && !w.minimized) markRead(w);
        });
    });

    setInterval(function() {
        windows.forEach(function(w) { pollWindow(w, false); });
    }, POLL);

    setInterval(pollList, Math.max(3000, POLL * 2));
    pollList();

    // Doosre scripts ke liye: TC_PM.open({id, name, avatar})
    window.TC_PM = { open: openWindow };
})();