<?php
declare(strict_types=1);
/* A customer asks to cancel or reschedule a booking. The admin approves or rejects it. */
require_once dirname(__DIR__).'/auth/require.php';
require_once dirname(__DIR__).'/ratelimit.php';
require_once dirname(__DIR__).'/notify.php';
const MIN_NOTICE_HOURS=24;   // requests must be made at least this long before the job
post_only(); csrf_check(); $user=require_user('customer');
function bad(string $m,int $c=422): never {respond($c,['ok'=>false,'message'=>$m]);}
$ref=trim((string)($_POST['ref']??''));
$type=(string)($_POST['type']??'');
$reason=trim((string)($_POST['reason']??''));
if($ref===''||!in_array($type,['cancel','reschedule'],true))bad('Invalid request.');
if(mb_strlen($reason)<3||mb_strlen($reason)>255)bad('Please give a short reason (3 to 255 characters).');
$newDate=null;$newTime=null;
$tz=new DateTimeZone('Asia/Manila');$now=new DateTimeImmutable('now',$tz);
if($type==='reschedule'){
 $d=trim((string)($_POST['new_date']??''));$t=trim((string)($_POST['new_time']??''));
 $dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i',"$d $t",$tz);
 if(!$dt||$dt->format('Y-m-d H:i')!=="$d $t")bad('Choose a new date and time.');
 if($dt<$now->modify('+'.MIN_NOTICE_HOURS.' hours'))bad('The new schedule must be at least '.MIN_NOTICE_HOURS.' hours from now.');
 $newDate=$d;$newTime=$t.':00';
}
try{
 $db=cleantee_db();
 ct_rate_limit($db,'reqchange',10,3600,(string)$user['id']);
 $q=$db->prepare('SELECT id,status,scheduled_date,scheduled_time FROM bookings WHERE reference=? AND user_id=?');$q->execute([$ref,$user['id']]);$b=$q->fetch();
 if(!$b)bad('Booking not found.',404);
 if(!in_array($b['status'],['New','Confirmed','Assigned'],true))bad('This booking can no longer be changed online. Please call CLEANTEE at +63 998 577 5917.',409);
 $job=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$b['scheduled_date'].' '.$b['scheduled_time'],$tz);
 if($job&&$job<$now->modify('+'.MIN_NOTICE_HOURS.' hours'))bad('Changes must be requested at least '.MIN_NOTICE_HOURS.' hours before the job. Please call CLEANTEE at +63 998 577 5917.',409);
 $p=$db->prepare("SELECT COUNT(*) FROM booking_requests WHERE booking_id=? AND status='Pending'");$p->execute([$b['id']]);
 if((int)$p->fetchColumn()>0)bad('You already have a pending request for this booking.',409);
 $db->prepare('INSERT INTO booking_requests (booking_id,user_id,type,reason,new_date,new_time) VALUES (?,?,?,?,?,?)')->execute([$b['id'],$user['id'],$type,$reason,$newDate,$newTime]);
 ct_after_response(function()use($ref,$type,$reason,$newDate,$newTime){
  ct_notify_admin("Customer request: $type for $ref","A customer asked to ".($type==='cancel'?'cancel':"reschedule to $newDate ".substr((string)$newTime,0,5))." booking $ref.\nReason: $reason");
 });
 respond(200,['ok'=>true,'message'=>'Request sent. We will review it and email you the result.']);
}catch(Throwable $e){error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not send the request. Please try again.']);}
