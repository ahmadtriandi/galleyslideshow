<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel Moderator</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #0f1115; color: #e7e9ee; padding: 24px; }
  h1 { font-size: 22px; margin-bottom: 20px; }
  .login { max-width: 340px; margin: 80px auto; background: #181b22; padding: 28px; border-radius: 12px; border: 1px solid #262a35; }
  input { width: 100%; padding: 11px; border-radius: 8px; border: 1px solid #2d3340; background: #0f1115; color: #fff; margin: 10px 0; }
  button { cursor: pointer; border: none; border-radius: 8px; padding: 9px 14px; font-weight: 600; font-size: 13px; }
  .btn-primary { background: #2563eb; color: #fff; width: 100%; }
  .btn-hide { background: #f59e0b; color: #1a1a1a; }
  .btn-show { background: #10b981; color: #062b1f; }
  .btn-del { background: #ef4444; color: #fff; }
  table { width: 100%; border-collapse: collapse; background: #181b22; border-radius: 12px; overflow: hidden; }
  th, td { text-align: left; padding: 12px 14px; border-bottom: 1px solid #262a35; font-size: 14px; }
  th { color: #8b90a0; font-size: 12px; text-transform: uppercase; }
  tr.hidden-row td { opacity: .5; }
  .actions { display: flex; gap: 8px; }
  .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  a { color: #6b8afd; font-size: 13px; }
  .tag { font-size: 11px; padding: 2px 8px; border-radius: 6px; }
  .tag-on { background: #064e3b; color: #6ee7b7; }
  .tag-off { background: #7c2d12; color: #fdba74; }
  .err { color: #ef4444; font-size: 13px; }

  /* Filter bar */
  .filterbar { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
  .filterlabel { color: #8b90a0; font-size: 13px; margin-right: 4px; }
  .fbtn {
    cursor: pointer; border: 1px solid #2d3340; border-radius: 8px;
    padding: 7px 14px; font-size: 13px; font-weight: 600;
    background: #181b22; color: #cfd3dd;
  }
  .fbtn:hover { border-color: #6b8afd; }
  .fbtn.active { background: #2563eb; color: #fff; border-color: #2563eb; }
  .filtercount { color: #8b90a0; font-size: 13px; margin-left: auto; }

  /* Thumbnail ringan: hanya menampilkan 1 frame video */
  .thumb {
    width: 120px; height: 68px; border-radius: 6px;
    background: #000; object-fit: cover; cursor: pointer;
    display: block; border: 1px solid #2d3340;
  }
  .thumb:hover { border-color: #6b8afd; }

  /* Popup pemutar saat thumbnail diklik */
  #modal {
    position: fixed; inset: 0; z-index: 100; display: none;
    align-items: center; justify-content: center;
    background: rgba(0,0,0,.8);
  }
  #modal.show { display: flex; }
  #modal .box { max-width: 80vw; max-height: 85vh; }
  #modal video { max-width: 80vw; max-height: 80vh; border-radius: 10px; background: #000; }
  #modal .cap { color: #cfd3dd; font-size: 13px; margin-top: 8px; text-align: center; word-break: break-all; }
  #modal .close {
    position: absolute; top: 18px; right: 24px; font-size: 28px;
    color: #fff; cursor: pointer; background: none; border: none;
  }
</style>
</head>
<body>
<div id="loginView" class="login">
  <h1>🔐 Login Moderator</h1>
  <input type="password" id="pass" placeholder="Password" onkeydown="if(event.key==='Enter')doLogin()" />
  <button class="btn-primary" onclick="doLogin()">Masuk</button>
  <div class="err" id="loginErr"></div>
</div>

<div id="panel" style="display:none">
  <div class="top">
    <h1>🛠️ Panel Moderator</h1>
    <div><a href="index.php">← Halaman penonton</a> &nbsp; <button class="btn-hide" onclick="logout()">Keluar</button></div>
  </div>
  <div class="filterbar">
    <span class="filterlabel">Filter Putar Otomatis (play.php):</span>
    <button class="fbtn active" data-filter="all" onclick="setFilter('all')">Semua</button>
    <button class="fbtn" data-filter="video" onclick="setFilter('video')">Video (.mp4)</button>
    <button class="fbtn" data-filter="image" onclick="setFilter('image')">Gambar (.jpg)</button>
    <span class="filtercount" id="filtercount"></span>
  </div>
  <table>
    <thead><tr><th>Preview</th><th>Nama</th><th>Status</th><th>Ukuran</th><th>Diunggah</th><th>Aksi</th></tr></thead>
    <tbody id="tbody"></tbody>
  </table>
</div>

<!-- Popup pemutar (muncul saat thumbnail diklik) -->
<div id="modal" onclick="closeModal(event)">
  <button class="close" onclick="closeModal(event,true)">✕</button>
  <div class="box" onclick="event.stopPropagation()">
    <video id="modalVideo" controls></video>
    <img id="modalImage" style="display:none;max-width:80vw;max-height:80vh;border-radius:10px">
    <div class="cap" id="modalCap"></div>
  </div>
</div>

<script>
function fmtSize(b){ return (b/1048576).toFixed(1)+' MB'; }
function fmtTime(ms){ return new Date(ms).toLocaleString('id-ID'); }

async function doLogin() {
  const p = document.getElementById('pass').value;
  const res = await fetch('api.php?action=login', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ password: p })
  });
  if (res.ok) showPanel();
  else document.getElementById('loginErr').textContent = 'Password salah';
}

async function logout(){
  await fetch('api.php?action=logout');
  location.reload();
}

function showPanel() {
  document.getElementById('loginView').style.display = 'none';
  document.getElementById('panel').style.display = 'block';
  load();
}

async function load() {
  const res = await fetch('api.php?action=admin_videos');
  if (res.status === 401) { document.getElementById('panel').style.display='none'; document.getElementById('loginView').style.display='block'; return; }
  allVideos = await res.json();
  render();
  loadFilterStatus();
}

let allVideos = [];

// Muat filter aktif dari server & tandai tombol yang sesuai
async function loadFilterStatus() {
  try {
    const res = await fetch('api.php?action=get_filter');
    const data = await res.json();
    const f = data.filter || 'all';
    document.querySelectorAll('.fbtn').forEach(b => {
      b.classList.toggle('active', b.dataset.filter === f);
    });
  } catch {}
}

// Simpan pilihan filter ke server (berlaku untuk play.php)
async function setFilter(f) {
  document.querySelectorAll('.fbtn').forEach(b => {
    b.classList.toggle('active', b.dataset.filter === f);
  });
  try {
    await fetch('api.php?action=set_filter', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ filter: f })
    });
  } catch {}
}

function render() {
  const tb = document.getElementById('tbody');
  tb.innerHTML = '';
  // Tabel moderator SELALU tampilkan semua (filter hanya untuk play.php)
  const nVid = allVideos.filter(v => v.type === 'video').length;
  const nImg = allVideos.filter(v => v.type === 'image').length;
  document.getElementById('filtercount').textContent =
    `Total ${nVid} video, ${nImg} gambar`;

  allVideos.forEach(v => {
    const tr = document.createElement('tr');
    if (v.hidden) tr.className = 'hidden-row';
    const safe = v.name.replace(/'/g,"\\'");
    const url = 'api.php?action=stream&name=' + encodeURIComponent(v.name);
    const thumb = v.type === 'image'
      ? `<img class="thumb" src="${url}" onclick="openModal('${safe}','image')">`
      : `<video class="thumb" muted preload="metadata" src="${url}#t=0.5" onclick="openModal('${safe}','video')"></video>`;
    tr.innerHTML = `
      <td>${thumb}</td>
      <td>${v.name}</td>
      <td><span class="tag ${v.hidden?'tag-off':'tag-on'}">${v.hidden?'Disembunyikan':'Tampil'}</span></td>
      <td>${fmtSize(v.size)}</td>
      <td>${fmtTime(v.mtime)}</td>
      <td><div class="actions">
        <button class="${v.hidden?'btn-show':'btn-hide'}" onclick="toggle('${safe}')">${v.hidden?'Tampilkan':'Sembunyikan'}</button>
        <button class="btn-del" onclick="del('${safe}')">Hapus</button>
      </div></td>`;
    tb.appendChild(tr);
  });
}

async function toggle(name) {
  await fetch('api.php?action=toggle', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ name })
  });
  load();
}

async function del(name) {
  if (!confirm('Hapus permanen "'+name+'"?')) return;
  await fetch('api.php?action=delete', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ name })
  });
  load();
}

// Cek apakah sudah login (session masih aktif)
fetch('api.php?action=admin_videos').then(r => { if (r.ok) showPanel(); });

// ---- Popup preview ----
function openModal(name, type) {
  const m = document.getElementById('modal');
  const vid = document.getElementById('modalVideo');
  const img = document.getElementById('modalImage');
  const url = 'api.php?action=stream&name=' + encodeURIComponent(name);
  document.getElementById('modalCap').textContent = name;
  if (type === 'image') {
    vid.pause(); vid.removeAttribute('src'); vid.style.display = 'none';
    img.src = url; img.style.display = 'block';
  } else {
    img.removeAttribute('src'); img.style.display = 'none';
    vid.src = url; vid.style.display = 'block';
    vid.play().catch(()=>{});
  }
  m.classList.add('show');
}
function closeModal(e, force) {
  // tutup hanya jika klik area gelap atau tombol ✕
  if (force || e.target.id === 'modal') {
    const m = document.getElementById('modal');
    const vid = document.getElementById('modalVideo');
    const img = document.getElementById('modalImage');
    vid.pause();
    vid.removeAttribute('src');
    vid.load();
    img.removeAttribute('src');
    m.classList.remove('show');
  }
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(null, true); });
</script>
</body>
</html>
