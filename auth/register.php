<?php
require_once dirname(__DIR__).'/bootstrap.php';
post_only(); csrf_check();
$name=trim((string)($_POST['name']??''));
$email=strtolower(trim((string)($_POST['email']??'')));
$phone=trim((string)($_POST['phone']??''));
$pass=(string)($_POST['password']??'');
$confirm=(string)($_POST['confirm']??'');
if (strlen($name)<2 || strlen($name)>150 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254 || !preg_match('/^09[0-9]{9}$/',$phone) || strlen($pass)<8 || strlen($pass)>1024 || $pass!==$confirm || ($_POST['terms']??'')==='') {
    respond(422,['ok'=>false,'error'=>'validation','message'=>'Check your name, email, 11-digit mobile number (09…), matching passwords (8+ characters), and terms agreement']);
}
try {
    $db=cleantee_db();
    $stmt=$db->prepare("INSERT INTO users (full_name,email,phone,password_hash,role,status) VALUES (?,?,?,?,'customer','Active')");
    $hash=password_hash($pass,PASSWORD_DEFAULT);
    $stmt->execute([$name,$email,$phone,$hash]);
    session_regenerate_id(true);
    $_SESSION['uid']=(int)$db->lastInsertId();
    $_SESSION['password_stamp']=hash('sha256',$hash);
    respond(201,['ok'=>true,'user'=>current_user(),'csrf'=>csrf_token()]);
} catch (PDOException $e) {
    if ($e->getCode()==='23000') respond(409,['ok'=>false,'error'=>'exists','message'=>'Email already registered']);
    error_log($e); respond(500,['ok'=>false,'message'=>'Registration unavailable']);
} catch (Throwable $e) { error_log($e); respond(500,['ok'=>false,'message'=>'Registration unavailable']); }
