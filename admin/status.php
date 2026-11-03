<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth/require.php';
require_once dirname(__DIR__).'/adminlog.php';
post_only();csrf_check();
$admin=require_user('admin');
/* Bookings are no longer changed here. They move through stages in booking_action.php
   (accept / decline / assign / start / complete / cancel) so every change is validated and logged. */
$kind=(string)($_POST['kind']??'');
$ref=trim((string)($_POST['ref']??''));
$status=(string)($_POST['status']??'');
$rules=[
 'report'=>['New'=>['Investigating','Resolved'],'Investigating'=>['Resolved'],'Resolved'=>[]],
 'customer'=>['Active'=>['Suspended'],'Suspended'=>['Active']]
];
if(!isset($rules[$kind])||strlen($ref)>254||$ref===''||!isset($rules[$kind][$status])) {
 respond(422,['ok'=>false,'message'=>'Invalid request']);
}
$tables=['report'=>['reports','reference'],'customer'=>['users','email']];
[$table,$key]=$tables[$kind];
try {
 $db=cleantee_db();
 $db->beginTransaction();
 $q=$db->prepare("SELECT id,status FROM $table WHERE $key=? FOR UPDATE");
 $q->execute([$ref]);$row=$q->fetch();
 if(!$row){$db->rollBack();respond(404,['ok'=>false,'message'=>'Record not found']);}
 if($kind==='customer'){
    $role=$db->prepare("SELECT role FROM users WHERE id=?");
    $role->execute([$row['id']]);
    if($role->fetchColumn()!=='customer'){$db->rollBack();respond(403,['ok'=>false,'message'=>'Cannot change an admin account']);}
 }
 $current=$row['status'];
 if($current!==$status&&!in_array($status,$rules[$kind][$current]??[],true)){
   $db->rollBack();respond(409,['ok'=>false,'message'=>"Cannot change $current to $status"]);
 }
 if($current!==$status){
   $u=$db->prepare("UPDATE $table SET status=? WHERE id=?");$u->execute([$status,$row['id']]);
 }
 $db->commit();
 if($current!==$status)ct_admin_log($db,$admin,$kind.'_status',$ref,"$current to $status");
 respond(200,['ok'=>true,'status'=>$status]);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not update status']);}
