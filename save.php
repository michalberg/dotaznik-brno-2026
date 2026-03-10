<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://jakebrno.cz');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['uuid'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing uuid']);
    exit;
}

$uuid   = preg_replace('/[^a-f0-9\-]/', '', $input['uuid']);
$status = ($input['status'] ?? 'partial') === 'complete' ? 'complete' : 'partial';
$email  = isset($input['data']['email']) ? trim($input['data']['email']) : null;
$data   = json_encode($input['data'] ?? []);
$page   = $input['page'] ?? null;

// DB je o úroveň výš než public_html — mimo webový kořen
$dbPath = __DIR__ . '/../dotaznik.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec('CREATE TABLE IF NOT EXISTS responses (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid       TEXT    UNIQUE NOT NULL,
        data       TEXT,
        email      TEXT,
        status     TEXT    DEFAULT "partial",
        last_page  TEXT,
        created_at TEXT,
        updated_at TEXT
    )');

    $stmt = $db->prepare('
        INSERT INTO responses (uuid, data, email, status, last_page, created_at, updated_at)
        VALUES (:uuid, :data, :email, :status, :page, datetime("now"), datetime("now"))
        ON CONFLICT(uuid) DO UPDATE SET
            data       = excluded.data,
            email      = COALESCE(excluded.email, email),
            status     = excluded.status,
            last_page  = excluded.last_page,
            updated_at = datetime("now")
    ');

    $stmt->execute([
        ':uuid'   => $uuid,
        ':data'   => $data,
        ':email'  => $email,
        ':status' => $status,
        ':page'   => $page,
    ]);

    echo json_encode(['ok' => true, 'status' => $status]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
