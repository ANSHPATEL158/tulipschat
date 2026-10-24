(function () {
  const box = document.getElementById('messages');
  const usersBox = document.getElementById('users');
  const input = document.getElementById('msgInput');
  let lastId = 0, busy = false;

  function el(tag, cls, text) {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  }

  function nearBottom() { return box.scrollHeight - box.scrollTop - box.clientHeight < 80; }

  function addMsg(m) {
    if (document.getElementById('m' + m.id)) return;
    const row = el('div', 'msg' + (m.uid === AK.me ? ' mine' : ''));
    row.id = 'm' + m.id;
    row.dataset.id = m.id;

    const img = el('img', 'av');
    img.src = m.avatar; img.alt = '';
    img.onclick = () => location.href = 'profile.php?id=' + m.uid;

    const body = el('div', 'msg-body');
    const head = el('div', 'msg-head');
    const name = el('span', 'uname' + (m.role === 'admin' ? ' is-admin' : ''), m.name);
    name.title = 'Mention';
    name.onclick = () => { input.value += '@' + m.name + ' '; input.focus(); };
    head.append(name, el('span', 'time', m.time));
    if (AK.admin) {
      const del = el('button', 'del', '×');
      del.title = 'Delete';
      del.onclick = () => { if (confirm('Message delete karein?')) post('delete', { id: m.id }).then(() => row.remove()); };
      head.append(del);
    }
    body.append(head);
    let handled = false;
    const renderers = window.TC_CHAT_ADDONS && Array.isArray(window.TC_CHAT_ADDONS.renderers)
      ? window.TC_CHAT_ADDONS.renderers : [];
    for (const render of renderers) {
      try { if (typeof render === 'function' && render(body, m) === true) { handled = true; break; } }
      catch (err) { console.error('Chat add-on renderer failed', err); }
    }
    if (!handled) body.append(el('div', 'text', m.text));
    row.append(img, body);
    box.append(row);
  }

  function renderUsers(list) {
    document.getElementById('onCount').textContent = list.length;
    usersBox.textContent = '';
    list.forEach(u => {
      const a = el('a', 'user');
      a.href = 'profile.php?id=' + u.id;
      const img = el('img', 'av'); img.src = u.avatar; img.alt = '';
      const n = el('span', 'uname' + (u.role === 'admin' ? ' is-admin' : ''), u.name);
      a.append(img, n);
      if (u.role === 'admin') a.append(el('span', 'badge', 'Admin'));
      a.append(el('span', 'dot'));
      usersBox.append(a);
    });
  }

  function post(act, data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    return fetch('ajax.php?act=' + act, { method: 'POST', body: fd, headers: { 'X-CSRF': AK.csrf } })
      .then(r => r.json());
  }

  function load() {
    if (busy) return;
    busy = true;
    const visible = [...box.querySelectorAll('.msg')].slice(-200).map(n => n.dataset.id).join(',');
    fetch('ajax.php?act=fetch&last=' + lastId + '&visible=' + visible)
      .then(r => r.json())
      .then(d => {
        if (d.error === 'logout') { location.href = 'login.php'; return; }
        const stick = nearBottom() || lastId === 0;
        (d.removed || []).forEach(id => { const n = document.getElementById('m' + id); if (n) n.remove(); });
        (d.messages || []).forEach(m => { addMsg(m); lastId = Math.max(lastId, m.id); });
        // purane messages zyada ho jayen to hata do
        while (box.children.length > 300) box.firstChild.remove();
        if (stick) box.scrollTop = box.scrollHeight;
        renderUsers(d.users || []);
      })
      .catch(() => {})
      .finally(() => { busy = false; });
  }

  document.getElementById('sendForm').addEventListener('submit', e => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    post('send', { message: text }).then(d => {
      if (d.error) { alert(d.error); input.value = text; }
      load();
    });
  });

  document.getElementById('toggleUsers').onclick = () =>
    document.getElementById('userlist').classList.toggle('open');

  load();
  setInterval(load, AK.poll);
})();
