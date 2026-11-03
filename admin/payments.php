<?php
require_once dirname(__DIR__).'/auth/require.php';
require_once dirname(__DIR__).'/notify.php';
require_once dirname(__DIR__).'/adminlog.php';
$user=require_user('admin');
try{
 $db=cleantee_db();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  post_only();csrf_check();$ref=(string)($_POST['reference']??'');
  $q=$db->prepare("UPDATE checkouts SET payment_status='Verified',verified_at=UTC_TIMESTAMP() WHERE reference=? AND payment_status<>'Verified'");$q->execute([$ref]);
  if(!$q->rowCount())respond(422,['ok'=>false,'message'=>'Checkout not found or already verified']);
  ct_admin_log($db,$user,'verify_payment',$ref);
  ct_after_response(function()use($db,$ref){
   $b=$db->prepare('SELECT b.reference FROM checkout_bookings cb JOIN checkouts c ON c.id=cb.checkout_id JOIN bookings b ON b.id=cb.booking_id WHERE c.reference=?');$b->execute([$ref]);
   foreach($b->fetchAll(PDO::FETCH_COLUMN) as $r)ct_notify_customer($db,$r,'payment_verified');
  });
 }
 $rows=$db->query('SELECT c.reference,c.payment_method,c.payment_reference,c.payment_status,c.estimated_total,c.down_payment_due,c.created_at,u.full_name AS name FROM checkouts c JOIN users u ON u.id=c.user_id ORDER BY c.id DESC LIMIT 1000')->fetchAll();
 respond(200,['ok'=>true,'payments'=>$rows]);
}catch(Throwable $e){error_log($e);respond(500,['ok'=>false,'message'=>'Could not load payments. Import sql/02_upgrade.sql.']);}
