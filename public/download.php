<?php
// /public/download.php
session_start();
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../config/db.php';
auth_required(); // only logged-in users

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = $_GET['type'] ?? ''; // e.g. proposal/template/report

// validate type
$validTypes = ['proposal' => 'proposals', 'template' => 'templates', 'report' => 'reports'];
if (!isset($validTypes[$type]) || $id <= 0) {
    http_response_code(400);
    exit("Invalid request.");
}

// fetch file path from DB
switch ($type) {
    case 'proposal':
        $stmt = db()->prepare("SELECT attachment_path AS path FROM proposals WHERE id=?");
        break;
    case 'template':
        $stmt = db()->prepare("SELECT file_path AS path FROM templates WHERE id=?");
        break;
    case 'report':
        $stmt = db()->prepare("SELECT file_path AS path FROM reports WHERE id=?");
        break;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if (!$row || empty($row['path'])) {
    http_response_code(404);
    exit("File not found.");
}

$filePath = __DIR__ . '/../content/' . $row['path'];
if (!file_exists($filePath)) {
    http_response_code(404);
    exit("File missing on server.");
}

// output securely
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
