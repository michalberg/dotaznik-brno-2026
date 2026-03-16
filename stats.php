<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$DB_PATH = __DIR__ . '/../dotaznik.db';

try {
    $db = new PDO('sqlite:' . $DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $total = (int)$db->query("SELECT COUNT(*) FROM responses")->fetchColumn();
    $complete = (int)$db->query("SELECT COUNT(*) FROM responses WHERE status = 'complete'")->fetchColumn();
    $yesterday = (int)$db->query("SELECT COUNT(*) FROM responses WHERE date(updated_at) = date('now', '-1 day')")->fetchColumn();

    echo json_encode([
        'total'     => $total,
        'complete'  => $complete,
        'yesterday' => $yesterday,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database unavailable']);
}
