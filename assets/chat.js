(function() {
    'use strict';

    const box = document.getElementById('messages');
    const usersBox = document.getElementById('users');
    const input = document.getElementById('msgInput');
    const form = document.getElementById('sendForm');

    if (!box || !usersBox || !input || !form) {
        console.error('Chaos Chat: chat elements missing.');
        return;
    }

    if (form.dataset.tcChatInitialized === '1') return;
    form.dataset.tcChatInitialized = '1';

    let lastId = 0;
    let busy = false;
    let sending = false;
    let replyTo = null;

    function el(tag, cls, text) {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function nearBottom() {
        return box.scrollHeight - box.scrollTop - box.clientHeight < 80;
    }

    // Floating menus are outside message layout, so they cannot add empty space.
    function closeMessageMenus() {
        document.querySelectorAll('.tc-message-actions').forEach(function(menu) {
            menu.style.display = 'none';
        });
    }

    function post(act, data) {
        const fd = new FormData();

        Object.entries(data || {}).forEach(function(entry) {
            fd.append(entry[0], entry[1]);
        });

        return fetch('ajax.php?act=' + encodeURIComponent(act), {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF': AK.csrf }
        }).then(async function(response) {
            let result;

            try {
                result = await response.json();
            } catch (_) {
                throw new Error('Server se valid JSON response nahi mila.');
            }

            if (!response.ok) {
                throw new Error(result.error || 'HTTP ' + response.status);
            }

            return result;
        });
    }

    // Uploaded file information.
    function getFileInfo(text) {
        if (typeof text !== 'string') return null;

        if (text.startsWith('__TC_UPLOAD__:')) {
            const path = text.slice('__TC_UPLOAD__:'.length);

            if (/^uploads\/chat\/[a-f0-9]{32}\.(jpg|jpeg|png|gif|webp)$/i.test(path)) {
                return { kind: 'image', path: path };
            }
        }

        const match = text.match(
            /^__TC_FILE__:(image|video|document):(uploads\/chat\/[a-f0-9]{32}\.(jpg|jpeg|png|gif|webp|mp4|webm|mov|pdf|txt|docx|xlsx|pptx))$/i
        );

        return match ? {
            kind: match[1].toLowerCase(),
            path: match[2]
        } : null;
    }

    function getMessagePreview(text) {
        if (typeof text !== 'string' || !text.trim()) {
            return 'Message unavailable';
        }

        const value = text.trim();
        const file = getFileInfo(value);

        if (file) {
            if (file.kind === 'image') return '🖼 Image';
            if (file.kind === 'video') return '🎬 Video';
            return '📎 Document';
        }

        if (/youtube\.com|youtu\.be|youtube-nocookie\.com/i.test(value)) {
            return '▶ YouTube video';
        }

        if (/klipy\.com|giphy\.com|tenor\.com|\.gif(?:[?#]|$)/i.test(value)) {
            return '🎞 GIF';
        }

        if (/\.(webp|png|jpe?g)(?:[?#]|$)/i.test(value)) {
            return '🖼 Image';
        }

        try {
            const url = new URL(value);

            if (url.protocol === 'http:' || url.protocol === 'https:') {
                return '🎬 Media link';
            }
        } catch (_) {
            // Normal text.
        }

        return value;
    }

    function getReplyMediaInfo(reply) {
        if (!reply) return null;

        const text = String(
            reply.text || reply.message || reply.url || ''
        ).trim();

        const candidates = [
            reply.media_url,
            reply.gif_url,
            reply.thumbnail_url,
            reply.thumbnail,
            reply.preview_url,
            reply.embed_url,
            text
        ].filter(function(value) {
            return typeof value === 'string' && value.trim();
        });

        for (const candidate of candidates) {
            let url;

            try {
                url = new URL(candidate.trim());
            } catch (_) {
                continue;
            }

            if (url.protocol !== 'https:' && url.protocol !== 'http:') {
                continue;
            }

            if (/youtube\.com|youtu\.be|youtube-nocookie\.com/i.test(url.hostname)) {
                let videoId = '';

                if (url.hostname === 'youtu.be' ||
                    url.hostname.endsWith('.youtu.be')) {
                    videoId = url.pathname.split('/').filter(Boolean)[0] || '';
                } else {
                    videoId = url.searchParams.get('v') || '';

                    if (!videoId) {
                        const parts = url.pathname.split('/').filter(Boolean);
                        const marker = parts.findIndex(function(part) {
                            return ['embed', 'shorts', 'live', 'v'].includes(part);
                        });

                        if (marker >= 0) {
                            videoId = parts[marker + 1] || '';
                        }
                    }
                }

                if (/^[a-zA-Z0-9_-]{11}$/.test(videoId)) {
                    return {
                        kind: 'youtube',
                        url: 'https://www.youtube.com/watch?v=' + videoId,
                        thumbnail: 'https://img.youtube.com/vi/' +
                            videoId + '/hqdefault.jpg'
                    };
                }

                continue;
            }

            if (/\.(gif|webp|png|jpe?g)(?:[?#]|$)/i.test(url.href)) {
                return {
                    kind: 'gif',
                    url: url.href,
                    thumbnail: url.href
                };
            }

            if (candidate !== text) {
                return {
                    kind: 'gif',
                    url: url.href,
                    thumbnail: url.href
                };
            }
        }

        return null;
    }

    // Reply bar.
    function clearReply() {
        replyTo = null;
        showReplyBar();
    }

    function showReplyBar() {
        let bar = document.getElementById('tcReplyBar');

        if (!bar) {
            bar = el('div');
            bar.id = 'tcReplyBar';

            bar.style.cssText =
                'display:none;padding:8px;margin:6px 0;' +
                'border-left:3px solid #a855f7;' +
                'background:rgba(128,128,128,.12);';

            form.parentNode.insertBefore(bar, form);
        }

        bar.textContent = '';

        if (!replyTo) {
            bar.style.display = 'none';
            return;
        }

        bar.append(
            el('span', '', 'Replying to ' + replyTo.name + ': '),
            el('span', '', replyTo.text.slice(0, 120))
        );

        const cancel = el('button', '', 'Cancel');
        cancel.type = 'button';
        cancel.style.marginLeft = '10px';
        cancel.onclick = clearReply;

        bar.append(cancel);
        bar.style.display = 'block';
    }

    // Allow YouTube/GIF add-ons to send messages.
    window.TC_SEND_CHAT_MESSAGE = async function(message) {
        const text = String(message || '').trim();
        if (!text) return false;

        try {
            const result = await post('send', { message: text });

            if (result && result.error) {
                throw new Error(result.error);
            }

            await load();
            return true;
        } catch (error) {
            console.error('Chaos Chat direct send failed:', error);
            alert(error.message || 'Message send nahi hua. Dobara try karein.');
            return false;
        }
    };

    // Online users.
    function renderUsers(list) {
        const count = document.getElementById('onCount');

        if (!Array.isArray(list)) {
            console.error('Invalid online users data:', list);
            return;
        }

        if (count) count.textContent = String(list.length);
        usersBox.textContent = '';

        list.forEach(function(user) {
            const link = el('a', 'user');
            link.href = 'profile.php?id=' + encodeURIComponent(user.id);

            const avatar = el('img', 'av');
            avatar.src = user.avatar || '';
            avatar.alt = '';
            avatar.loading = 'lazy';

            const name = el(
                'span',
                'uname' + (user.role === 'admin' ? ' is-admin' : ''),
                user.name || 'User'
            );

            link.append(avatar, name);

            if (user.role === 'admin') {
                link.append(el('span', 'badge', 'Admin'));
            }

            link.append(el('span', 'dot'));
            usersBox.append(link);
        });
    }

    // Uploaded image, video or document.
    function renderFile(container, messageText) {
        const file = getFileInfo(messageText);
        if (!file) return false;

        const wrapper = el('div', 'message-file');
        wrapper.style.marginTop = '8px';

        if (file.kind === 'image') {
            const img = el('img');
            img.src = file.path;
            img.alt = 'Uploaded image';
            img.loading = 'lazy';

            img.style.cssText =
                'max-width:240px;max-height:240px;width:auto;height:auto;' +
                'border-radius:10px;display:block;object-fit:contain;';

            wrapper.append(img);
        } else if (file.kind === 'video') {
            const video = el('video');
            video.src = file.path;
            video.controls = true;
            video.preload = 'metadata';

            video.style.cssText =
                'max-width:300px;width:100%;border-radius:10px;';

            wrapper.append(video);
        } else {
            const link = el('a', '', '📎 Open document');
            link.href = file.path;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            wrapper.append(link);
        }

        container.append(wrapper);
        return true;
    }

    // Position a fixed menu close to the click, within the visible viewport.
    function positionMessageMenu(menu, event) {
        menu.style.display = 'flex';

        const margin = 8;
        const gap = 8;
        const menuWidth = menu.offsetWidth;
        const menuHeight = menu.offsetHeight;

        let left = event.clientX + gap;
        let top = event.clientY + gap;

        if (left + menuWidth > window.innerWidth - margin) {
            left = event.clientX - menuWidth - gap;
        }

        if (top + menuHeight > window.innerHeight - margin) {
            top = event.clientY - menuHeight - gap;
        }

        left = Math.max(
            margin,
            Math.min(left, window.innerWidth - menuWidth - margin)
        );

        top = Math.max(
            margin,
            Math.min(top, window.innerHeight - menuHeight - margin)
        );

        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
    }

    // Floating Reply/Delete menu. Appended to document.body, NOT the message.
    function createMessageActions(m, row, body) {
        const actions = el('div', 'tc-message-actions');

        actions.style.cssText =
            'display:none;position:fixed;z-index:999999;' +
            'min-width:145px;padding:6px;' +
            'flex-direction:column;gap:4px;box-sizing:border-box;' +
            'background:#101020;border:1px solid #49336f;' +
            'border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.55);';

        actions.dataset.messageId = String(m.id);

        const replyBtn = el('button', 'tc-action-reply', '↩ Reply');
        replyBtn.type = 'button';

        replyBtn.style.cssText =
            'display:block;width:100%;box-sizing:border-box;' +
            'padding:8px 12px;background:rgba(168,85,247,.12);' +
            'border:1px solid #49336f;border-radius:7px;' +
            'color:#f5f3ff;cursor:pointer;text-align:left;';

        replyBtn.onclick = function(event) {
            event.preventDefault();
            event.stopPropagation();

            const messageId = Number(m.id);

            if (!Number.isFinite(messageId) || messageId <= 0) {
                console.error('Invalid message ID for reply:', m.id);
                return;
            }

            replyTo = {
                id: messageId,
                name: m.name || 'User',
                text: getMessagePreview(m.text)
            };

            closeMessageMenus();
            showReplyBar();
            input.focus();
        };

        actions.append(replyBtn);

        const currentUserId = Number(AK.me);
        const messageUserId = Number(m.uid);

        const isOwnMessage =
            Number.isFinite(currentUserId) &&
            Number.isFinite(messageUserId) &&
            currentUserId > 0 &&
            currentUserId === messageUserId;

        const isStaffOrAdmin = Boolean(
            AK.admin ||
            AK.staff ||
            AK.isStaff ||
            AK.role === 'admin' ||
            AK.role === 'staff'
        );

        if (isOwnMessage || isStaffOrAdmin) {
            const delBtn = el('button', 'tc-action-delete', '🗑 Delete');
            delBtn.type = 'button';

            delBtn.style.cssText =
                'display:block;width:100%;box-sizing:border-box;' +
                'padding:8px 12px;background:rgba(255,23,79,.08);' +
                'border:1px solid rgba(255,23,79,.45);' +
                'border-radius:7px;color:#ff5470;cursor:pointer;' +
                'text-align:left;';

            delBtn.onclick = async function(event) {
                event.preventDefault();
                event.stopPropagation();

                if (delBtn.disabled) return;

                delBtn.disabled = true;
                delBtn.textContent = 'Deleting...';

                try {
                    const result = await post('delete', { id: m.id });

                    if (result && result.error) {
                        throw new Error(result.error);
                    }

                    closeMessageMenus();
                    row.remove();
                } catch (error) {
                    console.error('Delete failed:', error);
                    delBtn.textContent = 'Delete failed';

                    setTimeout(function() {
                        if (document.body.contains(delBtn)) {
                            delBtn.textContent = '🗑 Delete';
                            delBtn.disabled = false;
                        }
                    }, 1200);
                }
            };

            actions.append(delBtn);
        }

        // Crucial fix: keep the floating menu outside the message layout.
        document.body.append(actions);

        return actions;
    }

    // Render one message.
    function addMsg(m) {
        if (!m || m.id == null) return;
        if (document.getElementById('m' + m.id)) return;

        const row = el(
            'div',
            'msg' + (Number(m.uid) === Number(AK.me) ? ' mine' : '')
        );

        row.id = 'm' + m.id;
        row.dataset.id = m.id;

        const avatar = el('img', 'av');
        avatar.src = m.avatar || '';
        avatar.alt = '';
        avatar.loading = 'lazy';

        avatar.onclick = function(event) {
            event.stopPropagation();
            location.href = 'profile.php?id=' + encodeURIComponent(m.uid);
        };

        const body = el('div', 'msg-body');
        body.style.minWidth = '0';

        const head = el('div', 'msg-head');

        const name = el(
            'span',
            'uname' + (m.role === 'admin' ? ' is-admin' : ''),
            m.name || 'User'
        );

        name.title = 'Mention';

        name.onclick = function(event) {
            event.stopPropagation();
            input.value += '@' + (m.name || 'User') + ' ';
            input.focus();
        };

        head.append(name, el('span', 'time', m.time || ''));
        body.append(head);

        // Reply preview.
        if (m.reply) {
            const quote = el('div', 'reply-preview');

            quote.style.cssText =
                'padding:7px 9px;margin:5px 0;' +
                'border-left:3px solid #a855f7;' +
                'background:rgba(128,128,128,.10);' +
                'font-size:.9em;border-radius:5px;';

            const replyText = m.reply.text || m.reply.message || '';
            const label = getMessagePreview(replyText);
            const media = getReplyMediaInfo(m.reply);

            if (media && media.thumbnail) {
                const preview = el('div', 'tc-reply-media');

                preview.style.cssText =
                    'margin-top:6px;display:flex;align-items:center;gap:8px;';

                const thumbnail = el('img', 'tc-reply-thumbnail');

                thumbnail.src = media.thumbnail;
                thumbnail.alt = media.kind === 'youtube' ?
                    'YouTube video thumbnail' : 'GIF preview';
                thumbnail.loading = 'lazy';

                thumbnail.style.cssText =
                    'width:100px;height:64px;object-fit:cover;' +
                    'border-radius:7px;display:block;flex-shrink:0;';

                thumbnail.onerror = function() {
                    thumbnail.remove();
                };

                preview.append(thumbnail);
                quote.append(preview);
            } else {
                quote.append(el('span', '', label));
            }

            quote.style.cursor = 'pointer';
            quote.title = 'Original message par jaayein';

            quote.onclick = function(event) {
                event.stopPropagation();

                const originalId = m.reply.id;
                if (originalId == null) return;

                const original = document.getElementById('m' + originalId);

                if (original) {
                    original.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    original.style.outline = '2px solid #a855f7';

                    setTimeout(function() {
                        original.style.outline = '';
                    }, 1500);
                }
            };

            body.append(quote);
        }

        // File and add-on renderers.
        const isFile = renderFile(body, m.text);

        if (!isFile) {
            let handled = false;

            const renderers =
                window.TC_CHAT_ADDONS &&
                Array.isArray(window.TC_CHAT_ADDONS.renderers) ?
                window.TC_CHAT_ADDONS.renderers : [];

            for (const render of renderers) {
                try {
                    if (typeof render === 'function' &&
                        render(body, m) === true) {
                        handled = true;
                        break;
                    }
                } catch (error) {
                    console.error('Chat add-on renderer failed:', error);
                }
            }

            if (!handled) {
                body.append(el('div', 'text', m.text || ''));
            }
        }

        const actions = createMessageActions(m, row, body);

        // Clicking the message opens the floating menu near the click.
        row.addEventListener('click', function(event) {
            if (event.target.closest(
                    'button, a, video, input, img, .uname, .reply-preview'
                )) {
                return;
            }

            const wasHidden =
                getComputedStyle(actions).display === 'none';

            closeMessageMenus();

            if (!wasHidden) return;

            positionMessageMenu(actions, event);
        });

        row.append(avatar, body);
        box.append(row);
    }

    // Fetch messages and online users.
    async function load() {
        if (busy) return;

        busy = true;

        const visible = Array.from(box.querySelectorAll('.msg'))
            .slice(-200)
            .map(function(node) {
                return node.dataset.id;
            })
            .join(',');

        try {
            const response = await fetch(
                'ajax.php?act=fetch&last=' + lastId +
                '&visible=' + encodeURIComponent(visible), { cache: 'no-store' }
            );

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();

            if (data.error === 'logout') {
                location.href = 'login.php';
                return;
            }

            if (data.error) {
                console.error('Chat fetch error:', data.error);
                return;
            }

            const stick = nearBottom() || lastId === 0;

            (data.removed || []).forEach(function(id) {
                const node = document.getElementById('m' + id);
                if (node) node.remove();

                const menu = document.querySelector(
                    '.tc-message-actions[data-message-id="' + id + '"]'
                );

                if (menu) menu.remove();
            });

            (data.messages || []).forEach(function(message) {
                addMsg(message);
                lastId = Math.max(lastId, Number(message.id) || 0);
            });

            while (box.children.length > 300) {
                const first = box.firstChild;
                const oldId = first && first.dataset ? first.dataset.id : null;

                if (oldId != null) {
                    const oldMenu = document.querySelector(
                        '.tc-message-actions[data-message-id="' + oldId + '"]'
                    );

                    if (oldMenu) oldMenu.remove();
                }

                first.remove();
            }

            if (stick) box.scrollTop = box.scrollHeight;

            if (Array.isArray(data.users)) {
                renderUsers(data.users);
            } else {
                console.error('Fetch response has no users array:', data);
            }
        } catch (error) {
            console.error('Chat refresh failed:', error);
        } finally {
            busy = false;
        }
    }

    // Upload controls.
    const fileInput = el('input');

    fileInput.type = 'file';
    fileInput.accept =
        'image/jpeg,image/png,image/gif,image/webp,' +
        'video/mp4,video/webm,video/quicktime,' +
        '.pdf,.txt,.docx,.xlsx,.pptx';
    fileInput.hidden = true;

    const uploadBtn = el('button', 'upload-btn', '⬆ Upload');
    uploadBtn.type = 'button';
    uploadBtn.title = 'Upload image, video or document';

    uploadBtn.style.cssText =
        'width:64px;height:64px;min-width:64px;padding:4px;' +
        'display:inline-flex;align-items:center;justify-content:center;' +
        'font-size:12px;line-height:1.2;border-radius:10px;' +
        'cursor:pointer;flex-shrink:0;';

    form.append(fileInput, uploadBtn);

    uploadBtn.onclick = function() {
        fileInput.click();
    };

    fileInput.onchange = async function() {
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;

        const filename = file.name.toLowerCase();
        let maxSize;
        let fileType;

        if (/\.(jpg|jpeg|png|gif|webp)$/.test(filename)) {
            maxSize = 5 * 1024 * 1024;
            fileType = 'Image';
        } else if (/\.(mp4|webm|mov)$/.test(filename)) {
            maxSize = 25 * 1024 * 1024;
            fileType = 'Video';
        } else if (/\.(pdf|txt|docx|xlsx|pptx)$/.test(filename)) {
            maxSize = 10 * 1024 * 1024;
            fileType = 'Document';
        } else {
            alert('Supported image, video ya document file select karein.');
            fileInput.value = '';
            return;
        }

        if (file.size > maxSize) {
            alert(fileType + ' file size limit se badi hai.');
            fileInput.value = '';
            return;
        }

        uploadBtn.disabled = true;

        const oldText = uploadBtn.textContent;
        uploadBtn.textContent = 'Uploading…';

        try {
            const fd = new FormData();
            fd.append('file', file);

            const response = await fetch('ajax.php?act=upload', {
                method: 'POST',
                body: fd,
                headers: { 'X-CSRF': AK.csrf }
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                alert(result.error || 'Upload failed.');
            } else {
                await load();
            }
        } catch (error) {
            console.error('Upload failed:', error);
            alert('Upload nahi hua. PHP upload limit aur ajax.php check karein.');
        } finally {
            uploadBtn.disabled = false;
            uploadBtn.textContent = oldText;
            fileInput.value = '';
        }
    };

    // Form and floating-menu styling.
    const style = el('style');

    style.textContent = `
        #sendForm {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        #sendForm button {
            box-sizing: border-box;
            width: 64px;
            min-width: 64px;
            height: 64px;
            padding: 4px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            line-height: 1.2;
            flex-shrink: 0;
        }

        #sendForm #msgInput {
            min-width: 0;
            flex: 1;
        }

        .tc-message-actions {
            color: #f5f3ff;
            font-family: inherit;
        }

        .tc-message-actions button {
            min-width: 100%;
            font-size: 13px !important;
            line-height: 1.4 !important;
        }

        .tc-message-actions button:hover {
            background: rgba(168, 85, 247, .18) !important;
        }

        .tc-message-actions .tc-action-delete:hover {
            background: rgba(255, 23, 79, .12) !important;
        }
    `;

    document.head.append(style);

    // Close menus on outside click, but not when clicking the menu itself.
    document.addEventListener('click', function(event) {
        if (event.target.closest('.tc-message-actions')) return;
        if (event.target.closest('.msg')) return;

        closeMessageMenus();
    });

    // Keep menus within the viewport after resizing.
    window.addEventListener('resize', closeMessageMenus);

    // Close menus when scrolling the message list.
    box.addEventListener('scroll', closeMessageMenus, { passive: true });

    // Send message, including the selected reply ID.
    form.addEventListener('submit', async function(event) {
        event.preventDefault();

        if (sending) return;

        const text = input.value.trim();
        if (!text) return;

        const data = { message: text };

        if (replyTo && Number.isFinite(replyTo.id) && replyTo.id > 0) {
            data.reply_to_id = replyTo.id;
        }

        sending = true;
        input.value = '';

        try {
            const result = await post('send', data);

            if (result.error) {
                alert(result.error);
                input.value = text;
                return;
            }

            clearReply();
            await load();
        } catch (error) {
            console.error('Message send failed:', error);
            alert(error.message || 'Message send nahi hua. Dobara try karein.');
            input.value = text;
        } finally {
            sending = false;
        }
    });

    // Toggle online users panel.
    const toggle = document.getElementById('toggleUsers');

    if (toggle) {
        toggle.onclick = function() {
            const userlist = document.getElementById('userlist');
            if (userlist) userlist.classList.toggle('open');
        };
    }

    // Manual refresh.
    const refreshBtn = document.getElementById('refreshPageBtn');

    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            window.location.reload();
        });
    }

    // Start chat.
    showReplyBar();
    load();

    setInterval(function() {
        load();
    }, Math.max(1000, Number(AK.poll) || 2000));

})();