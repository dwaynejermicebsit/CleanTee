<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth/require.php';
require_once dirname(__DIR__).'/adminlog.php';
$admin=require_user('admin');
post_only();csrf_check();
$uid=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$uid)respond(422,['ok'=>false,'message'=>'Choose a customer']);
try{
 $db=cleantee_db();
 $q=$db->prepare("SELECT id,full_name,email FROM users WHERE id=? AND role='customer'");$q->execute([$uid]);$u=$q->fetch();
 if(!$u)respond(404,['ok'=>false,'message'=>'Customer not found']);
 // 10 characters, no look-alike letters (0/O, 1/l/I)
 $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';$temp='';
 for($i=0;$i<10;$i++)$temp.=$alphabet[random_int(0,strlen($alphabet)-1)];
 $db->beginTransaction();
 $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($temp,PASSWORD_DEFAULT),$uid]);
 $db->prepare('UPDATE password_resets SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND used_at IS NULL')->execute([$uid]);
 $db->commit();
 ct_admin_log($db,$admin,'reset_password',$u['email'],'Temporary password created for '.$u['full_name']);
 // The temporary password is shown once and is never stored in readable form.
 respond(200,['ok'=>true,'name'=>$u['full_name'],'email'=>$u['email'],'temporary_password'=>$temp]);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not reset the password']);}
