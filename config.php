<?php
// ====== KONFIGURASI ======
// Folder tempat video .mp4 disimpan (relatif terhadap file ini, atau path absolut).
define('VIDEO_DIR', __DIR__ . '/videos');

// Password panel moderator. WAJIB diganti sebelum dipakai.
define('MOD_PASSWORD', 'admin123');

// File penyimpan daftar video yang disembunyikan moderator.
define('HIDDEN_FILE', __DIR__ . '/hidden.json');

// File penyimpan filter play.php (all | video | image).
define('FILTER_FILE', __DIR__ . '/filter.json');
// ==========================

// Mulai session untuk login moderator
session_start();

// ---- Fungsi bantu ----
function load_hidden() {
    if (!file_exists(HIDDEN_FILE)) return [];
    $data = json_decode(file_get_contents(HIDDEN_FILE), true);
    return is_array($data) ? $data : [];
}

function save_hidden($list) {
    file_put_contents(HIDDEN_FILE, json_encode(array_values($list), JSON_PRETTY_PRINT));
}

function list_videos() {
    if (!is_dir(VIDEO_DIR)) @mkdir(VIDEO_DIR, 0775, true);
    $hidden = load_hidden();
    $allowed = ['mp4', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    $out = [];
    foreach (scandir(VIDEO_DIR) as $f) {
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;
        $path = VIDEO_DIR . '/' . $f;
        $out[] = [
            'name'   => $f,
            'size'   => filesize($path),
            'mtime'  => filemtime($path) * 1000, // ke milidetik agar konsisten dgn JS
            'hidden' => in_array($f, $hidden, true),
            'type'   => ($ext === 'mp4') ? 'video' : 'image',
        ];
    }
    // urutkan terbaru di atas
    usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}

// Validasi nama file agar aman dari path traversal
function safe_video_path($name) {
    $name = basename($name); // buang komponen path
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp4','jpg','jpeg','png','gif','webp'])) return null;
    $path = VIDEO_DIR . '/' . $name;
    return file_exists($path) ? $path : null;
}

function is_moderator() {
    return !empty($_SESSION['is_mod']);
}

// Baca filter play.php; default 'all'. Nilai sah: all | video | image
function load_filter() {
    if (!file_exists(FILTER_FILE)) return 'all';
    $v = trim(@file_get_contents(FILTER_FILE));
    return in_array($v, ['all','video','image'], true) ? $v : 'all';
}

function save_filter($v) {
    if (!in_array($v, ['all','video','image'], true)) $v = 'all';
    file_put_contents(FILTER_FILE, $v);
}
