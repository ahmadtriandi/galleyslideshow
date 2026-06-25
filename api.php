<?php
require __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

// ---- Streaming video dengan dukungan HTTP Range (seek/pause berfungsi) ----
if ($action === 'stream') {
    $path = safe_video_path($_GET['name'] ?? '');
    if (!$path) { http_response_code(404); exit; }

    $size = filesize($path);
    $fp = fopen($path, 'rb');
    $start = 0;
    $end = $size - 1;

    header('Content-Type: video/mp4');
    header('Accept-Ranges: bytes');

    if (isset($_SERVER['HTTP_RANGE'])) {
        if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            if ($m[1] !== '') $start = intval($m[1]);
            if ($m[2] !== '') $end = intval($m[2]);
        }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }

    $length = $end - $start + 1;
    header("Content-Length: $length");
    fseek($fp, $start);
    $buffer = 8192;
    $pos = $start;
    while (!feof($fp) && $pos <= $end) {
        $read = ($pos + $buffer > $end) ? ($end - $pos + 1) : $buffer;
        echo fread($fp, $read);
        flush();
        $pos += $buffer;
    }
    fclose($fp);
    exit;
}

header('Content-Type: application/json');

// ---- Daftar video untuk penonton (tanpa yang disembunyikan) ----
if ($action === 'videos') {
    $all = array_filter(list_videos(), fn($v) => !$v['hidden']);
    echo json_encode(array_values($all));
    exit;
}

// ---- Login moderator ----
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (($body['password'] ?? '') === MOD_PASSWORD) {
        $_SESSION['is_mod'] = true;
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['ok' => false]);
    }
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['ok' => true]);
    exit;
}

// ===== Mulai di sini wajib moderator =====
if (in_array($action, ['admin_videos', 'toggle', 'delete'], true) && !is_moderator()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ---- Daftar semua video (untuk moderator) ----
if ($action === 'admin_videos') {
    echo json_encode(list_videos());
    exit;
}

// ---- Sembunyikan / tampilkan ----
if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $name = basename($body['name'] ?? '');
    $hidden = load_hidden();
    if (in_array($name, $hidden, true)) {
        $hidden = array_filter($hidden, fn($n) => $n !== $name);
    } else {
        $hidden[] = $name;
    }
    save_hidden($hidden);
    echo json_encode(['ok' => true]);
    exit;
}

// ---- Hapus permanen ----
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $path = safe_video_path($body['name'] ?? '');
    if ($path) {
        unlink($path);
        save_hidden(array_filter(load_hidden(), fn($n) => $n !== basename($path)));
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid name']);
    }
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Unknown action']);
