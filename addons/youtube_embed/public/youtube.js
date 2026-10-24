(function () {
  window.TC_CHAT_ADDONS = window.TC_CHAT_ADDONS || { renderers: [] };
  window.TC_CHAT_ADDONS.renderers = window.TC_CHAT_ADDONS.renderers || [];
  function extractVideo(text) {
    const matches = String(text || '').match(/https?:\/\/[^\s<]+/ig) || [];
    for (let raw of matches) {
      raw = raw.replace(/[),.!?;]+$/, '');
      let u;
      try { u = new URL(raw); } catch (_) { continue; }
      const host = u.hostname.toLowerCase().replace(/^www\./, '');
      let id = '';
      if (host === 'youtu.be') id = u.pathname.split('/').filter(Boolean)[0] || '';
      else if (host === 'youtube.com' || host === 'm.youtube.com' || host === 'music.youtube.com') {
        if (u.pathname === '/watch') id = u.searchParams.get('v') || '';
        else if (/^\/(shorts|embed|live)\//.test(u.pathname)) id = u.pathname.split('/')[2] || '';
      } else continue;
      if (/^[A-Za-z0-9_-]{11}$/.test(id)) return { raw, id };
    }
    return null;
  }
  window.TC_CHAT_ADDONS.renderers.push(function (body, message) {
    const found = extractVideo(message.text);
    if (!found) return false;
    const text = String(message.text || '');
    const before = text.slice(0, text.indexOf(found.raw)).trim();
    const after = text.slice(text.indexOf(found.raw) + found.raw.length).trim();
    if (before) { const p = document.createElement('div'); p.className = 'text'; p.textContent = before; body.append(p); }
    const wrap = document.createElement('div'); wrap.className = 'tc-youtube-wrap';
    const frame = document.createElement('iframe');
    frame.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(found.id);
    frame.title = 'YouTube video'; frame.loading = 'lazy'; frame.referrerPolicy = 'strict-origin-when-cross-origin';
    frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
    frame.allowFullscreen = true;
    wrap.append(frame); body.append(wrap);
    if (after) { const p = document.createElement('div'); p.className = 'text'; p.textContent = after; body.append(p); }
    return true;
  });
})();
