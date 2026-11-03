<?php
declare(strict_types=1);
/* Customer uploads a screenshot or photo of their payment receipt for one of their checkouts. */
require_once dirname(__DIR__).'/auth/require.php';
require_once dirname(__DIR__).'/ratelimit.php';
require_once dirname(__DIR__).'/notify.php';
require_once dirname(__DIR__).'/proofs.php';
post_only(); csrf_check(); $user=require_user('customer');
$ref=trim((string)($_POST['checkout_ref']??''));
if(!preg_match('/^CO-[A-F0-9]{12}$/',$ref))respond(422,['ok'=>false,'message'=>'Choose a booking payment.']);
$f=$_FILES['proof']??null;
$ext=ct_proof_check($f);
try{
 $db=cleantee_db();
 ct_rate_limit($db,'proof',10,3600,(string)$user['id']);
 $q=$db->prepare('SELECT id,payment_status,payment_method,proof_file FROM checkouts WHERE reference=? AND user_id=?');$q->execute([$ref,$user['id']]);$c=$q->fetch();
 if(!$c)respond(404,['ok'=>false,'message'=>'Payment not found.']);
 if($c['payment_status']==='Verified')respond(409,['ok'=>false,'message'=>'This payment is already verified.']);
 // A receipt that is already on file can only be replaced after CLEANTEE asks for a new one (sql/07).
 if($c['proof_file']){
  try{$rq=$db->prepare('SELECT proof_requested_at FROM checkouts WHERE id=?');$rq->execute([$c['id']]);
   if(!$rq->fetchColumn())respond(409,['ok'=>false,'message'=>'Your receipt is already on file. CLEANTEE will ask you to send a new one if needed.']);
  }catch(PDOException $e){error_log('CLEANTEE: run sql/07_proof_request.sql ('.$e->getMessage().')');}
 }
 $name=ct_proof_save($f,$ext);
 $db->prepare('UPDATE checkouts SET proof_file=?,proof_at=NOW() WHERE id=?')->execute([$name,$c['id']]);
 try{$db->prepare('UPDATE checkouts SET proof_requested_at=NULL,proof_request_note=NULL WHERE id=?')->execute([$c['id']]);}catch(PDOException $e){}
 ct_proof_delete($c['proof_file']??null);
 ct_after_response(function()use($ref){ct_notify_admin("Payment proof uploaded for $ref","A customer uploaded a payment receipt for $ref. Please verify the payment.");});
 respond(200,['ok'=>true,'message'=>'Receipt uploaded. We will verify your payment soon.']);
}catch(Throwable $e){error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not upload the receipt. Please try again.']);}
