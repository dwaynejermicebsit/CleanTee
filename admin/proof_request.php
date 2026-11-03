<?php
declare(strict_types=1);
/* Admin asks a customer to re-send a payment receipt (or cancels that request). The customer sees it in My History. */
require_once dirname(__DIR__).'/auth/require.php';
require_once dirname(__DIR__).'/adminlog.php';
post_only();csrf_check();$user=require_user('admin');
$ref=trim((string)($_POST['reference']??''));
$mode=(string)($_POST['mode']??'request');
$note=trim((string)($_POST['note']??''));
if(!preg_match('/^CO-[A-F0-9]{12}$/',$ref))respond(422,['ok'=>false,'message'=>'Invalid payment reference.']);
if(!in_array($mode,['request','clear'],true))respond(422,['ok'=>false,'message'=>'Invalid request.']);
if($mode==='request'&&mb_strlen($note)>255)respond(422,['ok'=>false,'message'=>'Note is too long (255 characters max).']);
try{
 $db=cleantee_db();
 $q=$db->prepare('SELECT id,payment_status,payment_method,proof_file FROM checkouts WHERE reference=?');$q->execute([$ref]);$c=$q->fetch();
 if(!$c)respond(404,['ok'=>false,'message'=>'Payment not found.']);
 if($mode==='request'){
  if($c['payment_method']==='cash')respond(409,['ok'=>false,'message'=>'Cash payments have no receipt.']);
  if($c['payment_status']==='Verified')respond(409,['ok'=>false,'message'=>'This payment is already verified.']);
  if(!$c['proof_file'])respond(409,['ok'=>false,'message'=>'No receipt has been uploaded yet.']);
  $db->prepare('UPDATE checkouts SET proof_requested_at=NOW(),proof_request_note=? WHERE id=?')->execute([$note!==''?mb_substr($note,0,255):null,$c['id']]);
  ct_admin_log($db,$user,'request_receipt',$ref,$note!==''?$note:null);
  respond(200,['ok'=>true,'message'=>'Asked the customer to send a new receipt.']);
 }
 $db->prepare('UPDATE checkouts SET proof_requested_at=NULL,proof_request_note=NULL WHERE id=?')->execute([$c['id']]);
 ct_admin_log($db,$user,'clear_receipt_request',$ref);
 respond(200,['ok'=>true,'message'=>'Request cancelled.']);
}catch(Throwable $e){error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not save. Import sql/07_proof_request.sql.']);}
