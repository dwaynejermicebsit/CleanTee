<?php
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/ratelimit.php';
post_only(); csrf_check();
$email=strtolower(trim((string)($_POST['email']??'')));
$password=(string)($_POST['password']??'');
if (!filter_var($email,FILTER_VALIDATE_EMAIL) || $password==='') respond(401,['ok'=>false,'error'=>'bad','message'=>'Invalid email or password']);
try {
    $db=cleantee_db();
    // At most 8 attempts per 10 minutes for the same email from the same device address.
    ct_rate_limit($db,'login',8,600,($_SERVER['REMOTE_ADDR']??'?').'|'.$email);
    $stmt=$db->prepare('SELECT id,full_name,email,phone,role,status,password_hash FROM users WHERE email=?');
    $stmt->execute([$email]); $u=$stmt->fetch();
    if (!$u || !password_verify($password,$u['password_hash'])) respond(401,['ok'=>false,'error'=>'bad','message'=>'Invalid email or password']);
    if ($u['status']!=='Active') respond(403,['ok'=>false,'error'=>'suspended','message'=>'Account suspended']);
    session_regenerate_id(true);
    $_SESSION['uid']=(int)$u['id'];
    $_SESSION['password_stamp']=hash('sha256',$u['password_hash']);
    respond(200,['ok'=>true,'user'=>public_user($u),'csrf'=>csrf_token()]);
} catch (Throwable $e) { error_log($e); respond(500,['ok'=>false,'message'=>'Login unavailable']); }
