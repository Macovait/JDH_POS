<?php
require_once __DIR__ . '/../../src/bootstrap.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$userId = (int) ($input['user_id'] ?? 0);
$type = $input['type'] ?? 'admin';
$action = $input['action'] ?? 'generate';
if (!$userId) { echo json_encode(['error'=>'Missing user_id']); exit; }
require_once __DIR__ . '/../../src/Auth/TwoFactorAuth.php';
$auth = new \JDH\POS\Auth\TwoFactorAuth(get_db_connection());
if ($action === 'generate') {
    $secret = $auth->generateSecret();
    $table = $type==='tenant'?'users':'admins';
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT email FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $email = $stmt->fetchColumn();
    echo json_encode(['secret'=>$secret,'qr_uri'=>$auth->getQrUri($secret,$email?:'user')]);
} elseif ($action === 'enable') {
    $secret = $input['secret'] ?? '';
    $code = $input['code'] ?? '';
    $ok = $auth->enable($userId,$type,$secret,$code);
    echo json_encode(['success'=>$ok]);
} elseif ($action === 'disable') {
    $auth->disable($userId,$type);
    echo json_encode(['success'=>true]);
}
