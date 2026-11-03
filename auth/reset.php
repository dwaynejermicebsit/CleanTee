<?php
require_once dirname(__DIR__).'/bootstrap.php';post_only();csrf_check();
$token=(string)($_POST['token']??'');$pass=(string)($_POST['password']??'');$confirm=(string)($_POST['confirm']??'');
if(!preg_match('/^[a-f0-9]{64}$/',$token)||strlen($pass)<8||strlen($pass)>1024||$pass!==$confirm)respond(422,['ok'=>false,'message'=>'Use a valid reset link and matching passwords of at least 8 characters']);
$db=null;
try{
 $db=cleantee_db();$db->beginTransaction();
 $q=$db->prepare('SELECT user_id FROM password_resets WHERE token_hash=?');$q->execute([hash('sha256',$token)]);$uid=$q->fetchColumn();
 if(!$uid){$db->rollBack();respond(422,['ok'=>false,'message'=>'Reset link is invalid or expired']);}
 // Serialize resets for one user; concurrent links cannot both change a password.
 $q=$db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$q->execute([$uid]);
 $q=$db->prepare('SELECT id FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE');$q->execute([hash('sha256',$token)]);
 if(!$q->fetchColumn()){$db->rollBack();respond(422,['ok'=>false,'message'=>'Reset link is invalid or expired']);}
 $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pass,PASSWORD_DEFAULT),$uid]);
 $db->prepare('UPDATE password_resets SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND used_at IS NULL')->execute([$uid]);$db->commit();
 $_SESSION=[];respond(200,['ok'=>true,'message'=>'Password changed. You can now log in.']);
}catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();error_log($e);respond(500,['ok'=>false,'message'=>'Could not reset password']);}
