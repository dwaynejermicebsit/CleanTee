<?php
// Include from future protected PHP endpoints, not directly from the browser.
require_once dirname(__DIR__).'/bootstrap.php';
function require_user(?string $role=null): array {
    $user=current_user();
    if (!$user) respond(401,['ok'=>false,'error'=>'unauthorized','message'=>'Login required']);
    if ($role!==null && $user['role']!==$role) respond(403,['ok'=>false,'error'=>'forbidden','message'=>'Access denied']);
    return $user;
}
