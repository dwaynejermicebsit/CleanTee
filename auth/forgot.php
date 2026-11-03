<?php
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/mailer.php';
post_only();csrf_check();
$email=strtolower(trim((string)($_POST['email']??'')));
if(!filter_var($email,FILTER_VALIDATE_EMAIL))respond(422,['ok'=>false,'message'=>'Enter a valid email address']);
if(time()-(int)($_SESSION['last_reset_request']??0)<60)respond(429,['ok'=>false,'message'=>'Please wait one minute before requesting another link']);
$_SESSION['last_reset_request']=time();
try{
 $db=cleantee_db();$q=$db->prepare("SELECT id FROM users WHERE email=? AND role='customer' AND status='Active'");$q->execute([$email]);$uid=$q->fetchColumn();
 if($uid){
  // Limit repeated requests across sessions without revealing account existence.
  $q=$db->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id=? AND created_at>DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 HOUR)');$q->execute([$uid]);
  if((int)$q->fetchColumn()<5){
   $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);$q=$db->prepare('INSERT INTO password_resets (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))');$q->execute([$uid,$hash]);
   $cfg=require dirname(__DIR__).'/config.php';$url=rtrim($cfg['app_url'],'/').'/reset.html#token='.$token;
   $sent=ct_send_mail($email,'Reset your CLEANTEE password',"Hello,\n\nOpen this link within one hour to choose a new password:\n$url\n\nIf you did not request this, you can ignore this message.\n\nCLEANTEE Cleaning Services");
   if(!$sent){$db->prepare('DELETE FROM password_resets WHERE token_hash=?')->execute([$hash]);error_log('CLEANTEE: password reset email could not be sent; check the SMTP settings in config.php.');}
  }
 }
 respond(200,['ok'=>true,'message'=>'If an active client account matches, a reset link will be emailed. Check spam. If it does not arrive, contact CLEANTEE.']);
}catch(Throwable $e){error_log($e);respond(500,['ok'=>false,'message'=>'Password recovery unavailable. Check the database and email configuration.']);}
