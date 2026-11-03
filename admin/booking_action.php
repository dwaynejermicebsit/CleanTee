<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth/require.php';
require_once dirname(__DIR__).'/notify.php';
require_once dirname(__DIR__).'/adminlog.php';
$admin=require_user('admin');

/* ---- Settings you can change ---- */
const REQUIRE_VERIFIED_PAYMENT=true;  // true: a booking can only be accepted after its down payment is Verified
const DEFAULT_JOB_HOURS=3;            // used when a service has no hours (aircon, fogging...)
const TRAVEL_BUFFER_MIN=60;           // gap kept between two jobs for the same staff member

$adminId=is_array($admin)?(int)($admin['id']??0):0;
$adminName=is_array($admin)?(string)($admin['name']??$admin['full_name']??'Admin'):'Admin';
$db=cleantee_db();

function job_span($time,$hours):array{
 $p=array_map('intval',explode(':',(string)$time)+[0,0]);
 $start=$p[0]*60+$p[1];
 $len=(int)round(((float)$hours>0?(float)$hours:DEFAULT_JOB_HOURS)*60);
 return [$start,$start+$len+TRAVEL_BUFFER_MIN,$start+$len];
}
function hhmm(int $m):string{$m%=1440;return sprintf('%02d:%02d',intdiv($m,60),$m%60);}
/** staff_id => ['ref','from','to'] for staff already on another active job that overlaps this booking */
function busy_staff(PDO $db,array $b,int $excludeId):array{
 $q=$db->prepare("SELECT bs.staff_id,x.reference,x.scheduled_time,x.hours FROM booking_staff bs JOIN bookings x ON x.id=bs.booking_id WHERE x.scheduled_date=? AND x.id<>? AND x.status IN ('Confirmed','Assigned','In Progress')");
 $q->execute([$b['scheduled_date'],$excludeId]);
 [$s1,$e1]=job_span($b['scheduled_time'],$b['hours']);
 $busy=[];
 foreach($q->fetchAll() as $r){
  [$s2,$e2,$end2]=job_span($r['scheduled_time'],$r['hours']);
  $sid=(int)$r['staff_id'];
  if($s1<$e2&&$s2<$e1&&!isset($busy[$sid]))$busy[$sid]=['ref'=>$r['reference'],'from'=>hhmm($s2),'to'=>hhmm($end2)];
 }
 return $busy;
}
function crew_count(PDO $db,int $id):int{$q=$db->prepare('SELECT COUNT(*) FROM booking_staff WHERE booking_id=?');$q->execute([$id]);return (int)$q->fetchColumn();}

/* ---------------- GET: booking detail for the review panel ---------------- */
if($_SERVER['REQUEST_METHOD']==='GET'){
 $ref=trim((string)($_GET['ref']??''));
 if(($_GET['action']??'')!=='detail'||$ref==='')respond(422,['ok'=>false,'message'=>'Invalid request']);
 try{
  $q=$db->prepare('SELECT b.*,s.name AS service_name FROM bookings b JOIN services s ON s.id=b.service_id WHERE b.reference=?');
  $q->execute([$ref]);$b=$q->fetch();
  if(!$b)respond(404,['ok'=>false,'message'=>'Booking not found']);
  $id=(int)$b['id'];
  $p=$db->prepare('SELECT c.reference AS checkout_ref,c.payment_method AS method,c.payment_status AS status,c.down_payment_due AS due,c.payment_reference FROM checkout_bookings cb JOIN checkouts c ON c.id=cb.checkout_id WHERE cb.booking_id=?');
  $p->execute([$id]);$payment=$p->fetch()?:null;
  $c=$db->prepare('SELECT st.id,st.full_name AS name,st.position,st.phone FROM booking_staff bs JOIN staff st ON st.id=bs.staff_id WHERE bs.booking_id=? ORDER BY bs.assigned_at');
  $c->execute([$id]);$crew=$c->fetchAll();
  $busy=busy_staff($db,$b,$id);
  $staff=$db->query("SELECT id,full_name AS name,position FROM staff WHERE status='Active' ORDER BY full_name")->fetchAll();
  foreach($staff as &$s){$s['busy']=$busy[(int)$s['id']]??null;}unset($s);
  $l=$db->prepare('SELECT created_at AS created,admin_name AS admin,action,from_status AS `from`,to_status AS `to`,note FROM booking_log WHERE booking_id=? ORDER BY id DESC LIMIT 50');
  $l->execute([$id]);
  $requests=[];$proof=null;
  try{
   $rq=$db->prepare('SELECT id,type,reason,new_date,new_time,status,admin_note,created_at AS created FROM booking_requests WHERE booking_id=? ORDER BY id DESC LIMIT 20');$rq->execute([$id]);$requests=$rq->fetchAll();
   if($payment){$pf=$db->prepare('SELECT proof_at FROM checkouts WHERE reference=? AND proof_file IS NOT NULL');$pf->execute([$payment['checkout_ref']]);$proof=$pf->fetchColumn()?:null;}
  }catch(PDOException $e){error_log('CLEANTEE: run sql/04_improvements.sql ('.$e->getMessage().')');}
  if($payment){$payment['proof_at']=$proof;$payment['proof_requested_at']=null;$payment['proof_request_note']=null;
   try{$pr=$db->prepare('SELECT proof_requested_at,proof_request_note FROM checkouts WHERE reference=?');$pr->execute([$payment['checkout_ref']]);if($x=$pr->fetch()){$payment['proof_requested_at']=$x['proof_requested_at'];$payment['proof_request_note']=$x['proof_request_note'];}}catch(PDOException $e){}
  }
  respond(200,['ok'=>true,'requests'=>$requests,'booking'=>[
   'ref'=>$b['reference'],'map_link'=>$b['map_link']??null,'status'=>$b['status'],'created'=>$b['created_at'],'name'=>$b['customer_name'],'phone'=>$b['phone'],'email'=>$b['email'],
   'service'=>$b['service_name'],'date'=>$b['scheduled_date'],'time'=>$b['scheduled_time'],'city'=>$b['city'],'address'=>$b['address'],
   'property_type'=>$b['property_type'],'unit_number'=>$b['unit_number'],'sqm'=>$b['sqm'],'floors'=>$b['floors'],'hours'=>$b['hours'],
   'cleaners'=>$b['cleaners'],'notes'=>$b['notes'],'estimated_total'=>$b['estimated_total'],'reason'=>$b['status_reason']
  ],'payment'=>$payment,'crew'=>$crew,'staff'=>$staff,'log'=>$l->fetchAll()]);
 }catch(Throwable $e){error_log((string)$e);respond(500,['ok'=>false,'message'=>'Unable to load booking']);}
}

/* ---------------- POST: move a booking to its next stage ---------------- */
post_only();csrf_check();
$action=(string)($_POST['action']??'');
$ref=trim((string)($_POST['ref']??''));
$reason=trim((string)($_POST['reason']??''));
if($ref===''||!in_array($action,['accept','decline','assign','unassign','start','complete','cancel','request_approve','request_reject'],true))respond(422,['ok'=>false,'message'=>'Invalid request']);
if(in_array($action,['decline','cancel'],true)&&(mb_strlen($reason)<3||mb_strlen($reason)>255))respond(422,['ok'=>false,'message'=>'Please give a short reason (3-255 characters).']);
if(mb_strlen($reason)>255)respond(422,['ok'=>false,'message'=>'The note is too long (255 characters at most).']);

try{
 $db->beginTransaction();
 $fail=function(int $code,string $msg)use($db){if($db->inTransaction())$db->rollBack();respond($code,['ok'=>false,'message'=>$msg]);};
 $q=$db->prepare('SELECT id,status,scheduled_date,scheduled_time,hours FROM bookings WHERE reference=? FOR UPDATE');
 $q->execute([$ref]);$b=$q->fetch();
 if(!$b)$fail(404,'Booking not found');
 $id=(int)$b['id'];$from=(string)$b['status'];$to=$from;$note=null;$event=null;$noteForMail=null;

 switch($action){
  case 'accept':
   if($from!=='New')$fail(409,"Only new bookings can be accepted (this one is $from).");
   if(REQUIRE_VERIFIED_PAYMENT){
    $p=$db->prepare('SELECT c.payment_status FROM checkout_bookings cb JOIN checkouts c ON c.id=cb.checkout_id WHERE cb.booking_id=?');
    $p->execute([$id]);$ps=$p->fetchColumn();
    if($ps!==false&&$ps!=='Verified')$fail(409,"Verify the customer's down payment before accepting this booking.");
   }
   $db->prepare("UPDATE bookings SET status='Confirmed',confirmed_at=NOW(),status_reason=NULL WHERE id=?")->execute([$id]);
   $to='Confirmed';break;

  case 'decline':
   if($from!=='New')$fail(409,"Only new bookings can be declined (this one is $from).");
   $db->prepare("UPDATE bookings SET status='Declined',declined_at=NOW(),status_reason=? WHERE id=?")->execute([$reason,$id]);
   $to='Declined';$note=$reason;break;

  case 'assign':
   if(!in_array($from,['Confirmed','Assigned'],true))$fail(409,'Accept the booking before assigning staff.');
   $ids=array_values(array_unique(array_filter(array_map('intval',explode(',',(string)($_POST['staff_ids']??''))),fn($v)=>$v>0)));
   if(!$ids||count($ids)>20)$fail(422,'Select at least one staff member.');
   $in=implode(',',array_fill(0,count($ids),'?'));
   $s=$db->prepare("SELECT id,full_name,status FROM staff WHERE id IN ($in) ORDER BY id FOR UPDATE");$s->execute($ids);$rows=$s->fetchAll();
   if(count($rows)!==count($ids))$fail(404,'Staff member not found.');
   $busy=busy_staff($db,$b,$id);
   foreach($rows as $r){
    if($r['status']!=='Active')$fail(409,$r['full_name'].' is inactive and cannot be assigned.');
    $x=$busy[(int)$r['id']]??null;
    if($x)$fail(409,$r['full_name']." is already on {$x['ref']} ({$x['from']}-{$x['to']}) at that time.");
   }
   $ins=$db->prepare('INSERT IGNORE INTO booking_staff (booking_id,staff_id) VALUES (?,?)');
   foreach($rows as $r)$ins->execute([$id,$r['id']]);
   $note='Assigned: '.implode(', ',array_column($rows,'full_name'));
   if($from==='Confirmed'&&crew_count($db,$id)>0){
    $db->prepare("UPDATE bookings SET status='Assigned',assigned_at=NOW() WHERE id=?")->execute([$id]);$to='Assigned';
   }
   break;

  case 'unassign':
   if(!in_array($from,['Confirmed','Assigned'],true))$fail(409,'Crew can only be changed before the job starts.');
   $sid=filter_var($_POST['staff_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
   if(!$sid)$fail(422,'Select a staff member.');
   $n=$db->prepare('SELECT full_name FROM staff WHERE id=?');$n->execute([$sid]);$name=(string)($n->fetchColumn()?:'Staff');
   $db->prepare('DELETE FROM booking_staff WHERE booking_id=? AND staff_id=?')->execute([$id,$sid]);
   $note='Removed: '.$name;
   if($from==='Assigned'&&crew_count($db,$id)===0){
    $db->prepare("UPDATE bookings SET status='Confirmed',assigned_at=NULL WHERE id=?")->execute([$id]);$to='Confirmed';
   }
   break;

  case 'start':
   if($from!=='Assigned')$fail(409,'Assign staff before starting the job.');
   $db->prepare("UPDATE bookings SET status='In Progress',started_at=NOW() WHERE id=?")->execute([$id]);
   $to='In Progress';break;

  case 'complete':
   if($from!=='In Progress')$fail(409,'Only a job in progress can be marked done.');
   $db->prepare("UPDATE bookings SET status='Done',completed_at=NOW() WHERE id=?")->execute([$id]);
   $to='Done';break;

  case 'request_approve':
  case 'request_reject':
   $rid=filter_var($_POST['request_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
   if(!$rid)$fail(422,'Choose a request.');
   $rq=$db->prepare("SELECT * FROM booking_requests WHERE id=? AND booking_id=? AND status='Pending' FOR UPDATE");$rq->execute([$rid,$id]);$req=$rq->fetch();
   if(!$req)$fail(404,'That request was already handled.');
   $adminNote=$reason!==''?$reason:null;
   if($action==='request_reject'){
    $db->prepare("UPDATE booking_requests SET status='Rejected',admin_note=?,decided_at=NOW() WHERE id=?")->execute([$adminNote,$rid]);
    $note='Rejected '.$req['type'].' request'.($adminNote?': '.$adminNote:'');$event='request_'.$req['type'].'_rejected';
    $noteForMail=$adminNote;break;
   }
   if(!in_array($from,['New','Confirmed','Assigned'],true))$fail(409,"A $from booking can no longer be changed.");
   if($req['type']==='cancel'){
    $db->prepare('DELETE FROM booking_staff WHERE booking_id=?')->execute([$id]);
    $db->prepare("UPDATE bookings SET status='Cancelled',cancelled_at=NOW(),assigned_at=NULL,status_reason=? WHERE id=?")->execute([mb_substr('Customer request: '.$req['reason'],0,255),$id]);
    $to='Cancelled';
   }else{
    if(!$req['new_date']||!$req['new_time'])$fail(422,'This request has no new schedule.');
    $db->prepare('UPDATE bookings SET scheduled_date=?,scheduled_time=? WHERE id=?')->execute([$req['new_date'],$req['new_time'],$id]);
    if($from==='Assigned'){
     // The crew was picked for the old schedule, so it is cleared and must be assigned again.
     $db->prepare('DELETE FROM booking_staff WHERE booking_id=?')->execute([$id]);
     $db->prepare("UPDATE bookings SET status='Confirmed',assigned_at=NULL WHERE id=?")->execute([$id]);$to='Confirmed';
    }
   }
   $db->prepare("UPDATE booking_requests SET status='Approved',admin_note=?,decided_at=NOW() WHERE id=?")->execute([$adminNote,$rid]);
   $note='Approved '.$req['type'].' request'.($req['type']==='reschedule'?' ('.$req['new_date'].' '.substr((string)$req['new_time'],0,5).')':'').($adminNote?': '.$adminNote:'');
   $event='request_'.$req['type'].'_approved';$noteForMail=$adminNote;
   break;

  case 'cancel':
   if(!in_array($from,['Confirmed','Assigned'],true))$fail(409,"A $from booking cannot be cancelled here.");
   $db->prepare('DELETE FROM booking_staff WHERE booking_id=?')->execute([$id]);
   $db->prepare("UPDATE bookings SET status='Cancelled',cancelled_at=NOW(),assigned_at=NULL,status_reason=? WHERE id=?")->execute([$reason,$id]);
   $to='Cancelled';$note=$reason;break;
 }

 $db->prepare('INSERT INTO booking_log (booking_id,admin_id,admin_name,action,from_status,to_status,note) VALUES (?,?,?,?,?,?,?)')
    ->execute([$id,$adminId?:null,$adminName,$action,$from,$to,$note]);
 $db->commit();
 // Email the customer about what just happened (sent after the page has answered).
 $ev=$event;$mailNote=$noteForMail;
 if($ev===null){
  $map=['accept'=>'confirmed','decline'=>'declined','start'=>'started','complete'=>'done','cancel'=>'cancelled'];
  $ev=$map[$action]??null;
  if($action==='assign'&&$to==='Assigned'&&$from!=='Assigned')$ev='assigned';
  $mailNote=$reason!==''?$reason:null;
 }
 if($ev!==null)ct_after_response(function()use($db,$ref,$ev,$mailNote){ct_notify_customer($db,$ref,$ev,$mailNote);});
 respond(200,['ok'=>true,'status'=>$to]);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not update the booking']);}
