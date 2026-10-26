(function() {
    'use strict';

    const box = document.getElementById('messages');
    const usersBox = document.getElementById('users');
    const input = document.getElementById('msgInput');
    const form = document.getElementById('sendForm');

    if (!box || !usersBox || !input || !form) {
        console.error('TulipsChat: chat elements missing.');
        return;
    }

    let lastId = 0;
    let busy = false;
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

    function getFileInfo(text) {
        if (typeof text !== 'string') return null;

        if (text.startsWith('__TC_UPLOAD__:')) {
            const path = text.slice('__TC_UPLOAD__:'.length);
            if (/^uploads\/chat\/[a-f0-9]{32}\.(jpg|png|gif|webp)$/i.test(path)) {
                return { kind: 'image', path };
            }
        }

        const match = text.match(
            /^__TC_FILE__:(image|video|document):(uploads\/chat\/[a-f0-9]{32}\.(jpg|png|gif|webp|mp4|webm|mov|pdf|txt|docx|xlsx|pptx))$/i
        );

        return match ? { kind: match[1].toLowerCase(), path: match[2] } :
            null;
    }

    function isUpload(text) {
        return getFileInfo(text) !== null;
    }

    function post(act, data) {
        const fd = new FormData();

        Object.entries(data || {}).forEach(([key, value]) => {
            fd.append(key, value);
        });

        return fetch('ajax.php?act=' + encodeURIComponent(act), {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF': AK.csrf }
        }).then(response => response.json());
    }

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
                'display:none;padding:8px;margin:6px 0;border-left:3px solid #5145ed;background:rgba(128,128,128,.12);';
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

    function renderFile(body, text) {
        const info = getFileInfo(text);
        if (!info) return false;

        if (info.kind === 'image') {
            const image = el('img', 'chat-image');
            image.src = info.path;
            image.alt = 'Uploaded image';
            image.loading = 'lazy';
            image.style.cssText =
                'display:block;max-width:100%;max-height:320px;border-radius:8px;margin-top:6px;cursor:pointer;';

            image.onerror = () => {
                image.replaceWith(el('div', 'text', 'Image unavailable: file nahi mil rahi.'));
            };

            image.onclick = () => window.open(info.path, '_blank', 'noopener,noreferrer');
            body.append(image);
            return true;
        }

        if (info.kind === 'video') {
            const video = el('video', 'chat-video');
            video.src = info.path;
            video.controls = true;
            video.preload = 'metadata';
            video.style.cssText =
                'display:block;max-width:100%;max-height:360px;margin-top:6px;border-radius:8px;';

            video.onerror = () => {
                video.replaceWith(el('div', 'text', 'Video unavailable: file nahi mil rahi.'));
            };

            body.append(video);
            return true;
        }

        const link = el('a', 'chat-document', '📄 Open / Download document');
        link.href = info.path;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.download = '';
        link.style.cssText =
            'display:inline-block;margin-top:8px;word-break:break-word;';

        body.append(link);
        return true;
    }

    function addMsg(m) {
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
        avatar.onclick = () => {
            location.href = 'profile.php?id=' + encodeURIComponent(m.uid);
        };

        const body = el('div', 'msg-body');
        const head = el('div', 'msg-head');

        const name = el(
            'span',
            'uname' + (m.role === 'admin' ? ' is-admin' : ''),
            m.name || 'User'
        );

        name.title = 'Mention';
        name.onclick = () => {
            input.value += '@' + (m.name || 'User') + ' ';
            input.focus();
        };

        head.append(name, el('span', 'time', m.time || ''));

        const replyBtn = el('button', 'reply-btn', '↩ Reply');
        replyBtn.type = 'button';

        replyBtn.onclick = () => {
            const file = getFileInfo(m.text);
            let preview = m.text || '';

            if (file) {
                preview = file.kind === 'image' ?
                    'Image' :
                    file.kind === 'video' ?
                    'Video' :
                    'Document';
            }

            replyTo = {
                id: Number(m.id),
                name: m.name || 'User',
                text: preview
            };

            showReplyBar();
            input.focus();
        };

        head.append(replyBtn);

        if (AK.admin) {
            const del = el('button', 'del', '×');
            del.type = 'button';
            del.title = 'Delete message';

            del.onclick = async() => {
                if (!confirm('Message delete karein?')) return;

                try {
                    const result = await post('delete', { id: m.id });

                    if (result.error) {
                        alert(result.error);
                        return;
                    }

                    row.remove();
                } catch (error) {
                    alert('Message delete nahi hua. Dobara try karein.');
                }
            };

            head.append(del);
        }

        body.append(head);

        if (m.reply) {
            const quote = el('div', 'reply-preview');
            quote.style.cssText =
                'padding:5px 8px;margin:4px 0;border-left:3px solid #999;background:rgba(128,128,128,.10);font-size:.9em;';

            const replyText = m.reply.text || m.reply.message || 'Message unavailable';
            const replyFile = getFileInfo(replyText);

            const label = replyFile ?
                (replyFile.kind === 'image' ?
                    'Image' :
                    replyFile.kind === 'video' ?
                    'Video' :
                    'Document') :
                replyText;

            quote.append(
                el('strong', '', (m.reply.name || m.reply.username || 'Original message') + ': '),
                el('span', '', label)
            );

            quote.style.cursor = 'pointer';
            quote.title = 'Original message par jaayein';

            quote.onclick = () => {
                const original = document.getElementById('m' + m.reply.id);

                if (original) {
                    original.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    original.style.outline = '2px solid #5145ed';
                    setTimeout(() => { original.style.outline = ''; }, 1500);
                }
            };

            body.append(quote);
        }

        if (!renderFile(body, m.text)) {
            let handled = false;

            const renderers =
                window.TC_CHAT_ADDONS &&
                Array.isArray(window.TC_CHAT_ADDONS.renderers) ?
                window.TC_CHAT_ADDONS.renderers : [];

            for (const render of renderers) {
                try {
                    if (typeof render === 'function' && render(body, m) === true) {
                        handled = true;
                        break;
                    }
                } catch (error) {
                    console.error('Chat add-on renderer failed:', error);
                }
            }

            if (!handled) body.append(el('div', 'text', m.text || ''));
        }

        row.append(avatar, body);
        box.append(row);
    }

    function renderUsers(list) {
        const count = document.getElementById('onCount');
        if (count) count.textContent = list.length;

        usersBox.textContent = '';

        list.forEach(user => {
            const link = el('a', 'user');
            link.href = 'profile.php?id=' + encodeURIComponent(user.id);

            const avatar = el('img', 'av');
            avatar.src = user.avatar || '';
            avatar.alt = '';

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

    function load() {
        if (busy) return;
        busy = true;

        const visible = [...box.querySelectorAll('.msg')]
            .slice(-200)
            .map(node => node.dataset.id)
            .join(',');

        fetch(
                'ajax.php?act=fetch&last=' + lastId +
                '&visible=' + encodeURIComponent(visible)
            )
            .then(response => response.json())
            .then(data => {
                if (data.error === 'logout') {
                    location.href = 'login.php';
                    return;
                }

                if (data.error) {
                    console.error('Chat fetch error:', data.error);
                    return;
                }

                const stick = nearBottom() || lastId === 0;

                (data.removed || []).forEach(id => {
                    const node = document.getElementById('m' + id);
                    if (node) node.remove();
                });

                (data.messages || []).forEach(message => {
                    addMsg(message);
                    lastId = Math.max(lastId, Number(message.id));
                });

                while (box.children.length > 300) {
                    box.firstChild.remove();
                }

                if (stick) {
                    box.scrollTo({
                        top: box.scrollHeight,
                        behavior: 'instant'
                    });
                }
                renderUsers(data.users || []);
            })
            .catch(error => console.error('Chat refresh failed:', error))
            .finally(() => { busy = false; });
    }

    // Upload button
    const fileInput = el('input');
    fileInput.type = 'file';
    fileInput.accept =
        'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime,.pdf,.txt,.docx,.xlsx,.pptx';
    fileInput.hidden = true;

    const uploadBtn = el('button', 'upload-btn', '⬆ Upload');
    uploadBtn.type = 'button';
    uploadBtn.title = 'Upload image, video or document';
    uploadBtn.style.cssText =
        'width:64px;height:64px;min-width:64px;padding:4px;' +
        'display:inline-flex;align-items:center;justify-content:center;' +
        'font-size:12px;line-height:1.2;border-radius:10px;cursor:pointer;flex-shrink:0;';

    form.append(fileInput, uploadBtn);
    uploadBtn.onclick = () => fileInput.click();

    fileInput.onchange = async() => {
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

            const result = await fetch('ajax.php?act=upload', {
                method: 'POST',
                body: fd,
                headers: { 'X-CSRF': AK.csrf }
            }).then(response => response.json());

            if (result.error) {
                alert(result.error);
            } else {
                load();
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

    // Compact square controls
    const style = el('style');
    style.textContent = `
    #sendForm {
      display:flex;
      align-items:center;
      gap:6px;
    }
    #sendForm button {
      box-sizing:border-box;
      width:64px;
      min-width:64px;
      height:64px;
      padding:4px;
      border-radius:10px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      font-size:12px;
      line-height:1.2;
      flex-shrink:0;
    }
    #sendForm #msgInput {
      min-width:0;
      flex:1;
    }
  `;
    document.head.append(style);

    form.addEventListener('submit', async event => {
        event.preventDefault();

        const text = input.value.trim();
        if (!text) return;

        const data = { message: text };
        if (replyTo) data.reply_to_id = replyTo.id;

        input.value = '';

        try {
            const result = await post('send', data);

            if (result.error) {
                alert(result.error);
                input.value = text;
                return;
            }

            clearReply();
            load();
        } catch (error) {
            console.error('Message send failed:', error);
            alert('Message send nahi hua. Dobara try karein.');
            input.value = text;
        }
    });

    const toggle = document.getElementById('toggleUsers');
    if (toggle) {
        toggle.onclick = () => {
            const userlist = document.getElementById('userlist');
            if (userlist) userlist.classList.toggle('open');
        };
    }

    showReplyBar();
    load();
    setInterval(load, Number(AK.poll) || 2000);
    const refreshBtn = document.getElementById('refreshPageBtn');

    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            window.location.reload();
        });
    }
})();