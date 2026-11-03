<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
session_name('CLEANTEESESSIDV2');
session_set_cookie_params([
    'lifetime'=>0, 'path'=>'/', 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly'=>true, 'samesite'=>'Lax'
]);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function respond(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}
function post_only(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405,['ok'=>false,'error'=>'method_not_allowed','message'=>'POST required']);
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    if ($origin && parse_url($origin,PHP_URL_HOST)!==($_SERVER['HTTP_HOST'] ? explode(':',$_SERVER['HTTP_HOST'])[0] : '')) {
        respond(403,['ok'=>false,'error'=>'origin','message'=>'Invalid origin']);
    }
}
function csrf_check(): void {
    $token=$_SERVER['HTTP_X_CSRF_TOKEN']??($_POST['csrf_token']??'');
    if (!is_string($token) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        respond(403,['ok'=>false,'error'=>'csrf','message'=>'Refresh the page and try again']);
    }
}
function public_user(array $u): array {
    return ['id'=>(int)$u['id'],'name'=>$u['full_name'],'email'=>$u['email'],'phone'=>$u['phone'],'role'=>$u['role']];
}
function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $s=cleantee_db()->prepare('SELECT id,full_name,email,phone,role,status,password_hash FROM users WHERE id=?');
    $s->execute([$_SESSION['uid']]);
    $u=$s->fetch();
    if (!$u || $u['status']!=='Active' || !hash_equals($_SESSION['password_stamp']??'', hash('sha256',$u['password_hash']))) { unset($_SESSION['uid']); return null; }
    return public_user($u);
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
