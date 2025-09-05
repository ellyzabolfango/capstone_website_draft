<?php
// /public/upload_proposal_user.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../bootstrap.php'; // db(), csrf, auth, constants

auth_required();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
if (!csrf_check($_POST['csrf_token'] ?? null)) { http_response_code(400); exit('Bad request (CSRF).'); }

$userId     = (int)($_SESSION['user_id'] ?? 0);
$proposalId = isset($_POST['proposal_id']) ? (int) $_POST['proposal_id'] : 0;

// Where to go back after upload (defaults to user/projects.php)
$redirect = (string)($_POST['redirect'] ?? (USER_URL . '/projects.php'));

if ($userId <= 0 || $proposalId <= 0) {
  header('Location: ' . $redirect . '?msg=' . rawurlencode('Missing data.'));
  exit();
}
if (empty($_FILES['file']['name'])) {
  header('Location: ' . $redirect . '?msg=' . rawurlencode('No file selected.'));
  exit();
}

// --- Ensure the proposal belongs to the current user ---
$own = db()->prepare("SELECT 1 FROM proposals WHERE id=? AND submitted_by=? LIMIT 1");
$own->bind_param('ii', $proposalId, $userId);
$own->execute();
if (!$own->get_result()->fetch_row()) {
  header('Location: ' . $redirect . '?msg=' . rawurlencode('You cannot upload to this proposal.'));
  exit();
}

// --- Validate file ---
$allowedExt = ['pdf','doc','docx','xls','xlsx','ppt','pptx','png','jpg','jpeg'];
$maxMB      = 10;

$sizeOK  = ((int)($_FILES['file']['size'] ?? 0)) <= ($maxMB * 1024 * 1024);
$orig    = (string) $_FILES['file']['name'];
$ext     = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExt, true) || !$sizeOK) {
  header('Location: ' . $redirect . '?msg=' . rawurlencode('Invalid file or too large.'));
  exit();
}

// Optional MIME sniff (best-effort)
$mimeOK = true;
if (is_uploaded_file($_FILES['file']['tmp_name'])) {
  $fi  = new finfo(FILEINFO_MIME_TYPE);
  $mime = (string) $fi->file($_FILES['file']['tmp_name']);
  $allowedMime = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword','application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/octet-stream'],
    'xls'  => ['application/vnd.ms-excel','application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/octet-stream'],
    'ppt'  => ['application/vnd.ms-powerpoint','application/octet-stream'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation','application/octet-stream'],
    'png'  => ['image/png'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
  ];
  $mimeOK = isset($allowedMime[$ext]) && in_array($mime, $allowedMime[$ext], true);
}
if (!$mimeOK) {
  header('Location: ' . $redirect . '?msg=' . rawurlencode('File type not allowed.'));
  exit();
}

// --- Ensure uploads dir exists (web-accessible) ---
$uploadsDir = BASE_PATH . '/uploads/proposals';
if (!is_dir($uploadsDir) && !@mkdir($uploadsDir, 0777, true)) {
  header('Location: ' . $redirect . '?msg=' . rawurlencode('Server storage not ready.'));
  exit();
}

// --- Generate safe unique filename + move ---
$slug    = preg_replace('/[^A-Za-z0-9._-]/', '_', $orig);
$base    = pathinfo($slug, PATHINFO_FILENAME);
$fname   = sprintf('%d_u%s_p%s_%s.%s', time(), $userId, $proposalId, bin2hex(random_bytes(4)), $ext);
$disk    = $uploadsDir . '/' . $fname;

if (!move_uploaded_file($_FILES['file']['tmp_name'], $disk)) {
  header('Location: ' . $redirect . '?msg=' . rawurlencode('Upload failed.'));
  exit();
}
@chmod($disk, 0644);

// --- Save relative web path ---
$webRel = 'uploads/proposals/' . $fname;
$upd = db()->prepare("UPDATE proposals SET attachment_path=? WHERE id=? AND submitted_by=?");
$upd->bind_param('sii', $webRel, $proposalId, $userId);
$upd->execute();

header('Location: ' . $redirect . '?' . http_build_query([
  'msg' => 'Upload successful.',
]));
exit();
