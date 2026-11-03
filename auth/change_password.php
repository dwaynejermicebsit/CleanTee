<?php
declare(strict_types=1);
/* A logged-in customer (or admin) changes their own password. */
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/ratelimit.php';
post_only(); csrf_check();
$user=current_user();
if(!$user)respond(401,['ok'=>false,'message'=>'Please log in first.']);
$current=(string)($_POST['current_password']??'');
$new=(string)($_POST['new_password']??'');
$confirm=(string)($_POST['confirm_password']??'');
if(strlen($new)<8||strlen($new)>1024)respond(422,['ok'=>false,'message'=>'Your new password must be at least 8 characters.']);
if($new!==$confirm)respond(422,['ok'=>false,'message'=>'The new passwords do not match.']);
if($new===$current)respond(422,['ok'=>false,'message'=>'Choose a password different from your current one.']);
try{
 $db=cleantee_db();
 ct_rate_limit($db,'chgpw',8,900,(string)$user['id']);
 $q=$db->prepare("SELECT password_hash FROM users WHERE id=? AND status='Active'");$q->execute([$user['id']]);$hash=(string)$q->fetchColumn();
 if($hash===''||!password_verify($current,$hash))respond(403,['ok'=>false,'message'=>'Your current password is not correct.']);
 $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]);
 $db->prepare('UPDATE password_resets SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND used_at IS NULL')->execute([$user['id']]);
 respond(200,['ok'=>true,'message'=>'Password changed.']);
}catch(Throwable $e){error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not change the password.']);}
