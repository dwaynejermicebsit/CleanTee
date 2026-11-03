<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth/require.php';
require_once dirname(__DIR__).'/notify.php';
$user=require_user('staff');
$db=cleantee_db();
date_default_timezone_set('Asia/Manila');

/* The staff record that belongs to this login. */
$q=$db->prepare('SELECT id,full_name,status FROM staff WHERE user_id=?');$q->execute([$user['id']]);
$me=$q->fetch();
if(!$me)respond(403,['ok'=>false,'message'=>'Your login is not linked to a staff record. Please contact the admin.']);
if($me['status']!=='Active')respond(403,['ok'=>false,'message'=>'Your staff account is inactive. Please contact the admin.']);
$staffId=(int)$me['id'];

if($_SERVER['REQUEST_METHOD']==='GET'){
 try{
  $s=$db->prepare("SELECT b.*,sv.name AS service_name FROM bookings b JOIN services sv ON sv.id=b.service_id JOIN booking_staff bs ON bs.booking_id=b.id
   WHERE bs.staff_id=? AND (b.status IN ('Assigned','In Progress') OR (b.status='Done' AND b.completed_at>=DATE_SUB(NOW(),INTERVAL 14 DAY)))
   ORDER BY FIELD(b.status,'In Progress','Assigned','Done'),b.scheduled_date,b.scheduled_time");
  $s->execute([$staffId]);$jobs=[];
  $crew=$db->prepare('SELECT st.full_name FROM booking_staff bs JOIN staff st ON st.id=bs.staff_id WHERE bs.booking_id=? AND st.id<>? ORDER BY st.full_name');
  foreach($s->fetchAll() as $b){
   $crew->execute([$b['id'],$staffId]);
   $jobs[]=[
    'ref'=>$b['reference'],'status'=>$b['status'],'service'=>$b['service_name'],
    'date'=>$b['scheduled_date'],'time'=>substr((string)$b['scheduled_time'],0,5),'hours'=>$b['hours'],
    'customer'=>$b['customer_name'],'phone'=>$b['phone'],'address'=>trim(($b['unit_number']?$b['unit_number'].', ':'').$b['address'].', '.$b['city']),
    'map_link'=>$b['map_link']??null,'notes'=>$b['notes'],'teammates'=>$crew->fetchAll(PDO::FETCH_COLUMN),
    'can_start'=>$b['status']==='Assigned'&&$b['scheduled_date']<=date('Y-m-d'),
   ];
  }
  respond(200,['ok'=>true,'staff'=>$me['full_name'],'today'=>date('Y-m-d'),'jobs'=>$jobs]);
 }catch(Throwable $e){error_log((string)$e);respond(500,['ok'=>false,'message'=>'Unable to load your jobs']);}
}

post_only();csrf_check();
$ref=trim((string)($_POST['ref']??''));$action=(string)($_POST['action']??'');
if($ref===''||!in_array($action,['start','done'],true))respond(422,['ok'=>false,'message'=>'Invalid request']);
try{
 $db->beginTransaction();
 $q=$db->prepare('SELECT b.id,b.status,b.scheduled_date FROM bookings b JOIN booking_staff bs ON bs.booking_id=b.id WHERE b.reference=? AND bs.staff_id=? FOR UPDATE');
 $q->execute([$ref,$staffId]);$b=$q->fetch();
 if(!$b){$db->rollBack();respond(404,['ok'=>false,'message'=>'That job is not assigned to you.']);}
 $from=(string)$b['status'];
 if($action==='start'){
  if($from!=='Assigned'){$db->rollBack();respond(409,['ok'=>false,'message'=>"This job is $from and cannot be started."]);}
  if($b['scheduled_date']>date('Y-m-d')){$db->rollBack();respond(409,['ok'=>false,'message'=>'You can start this job on its scheduled date.']);}
  $db->prepare("UPDATE bookings SET status='In Progress',started_at=NOW() WHERE id=?")->execute([$b['id']]);$to='In Progress';$ev='started';
 }else{
  if($from!=='In Progress'){$db->rollBack();respond(409,['ok'=>false,'message'=>'Start the job before marking it done.']);}
  $db->prepare("UPDATE bookings SET status='Done',completed_at=NOW() WHERE id=?")->execute([$b['id']]);$to='Done';$ev='done';
 }
 $db->prepare('INSERT INTO booking_log (booking_id,admin_id,admin_name,action,from_status,to_status,note) VALUES (?,?,?,?,?,?,?)')
    ->execute([$b['id'],$user['id'],$me['full_name'].' (staff)',$action==='done'?'complete':'start',$from,$to,'Updated by staff']);
 $db->commit();
 ct_after_response(function()use($db,$ref,$ev){ct_notify_customer($db,$ref,$ev,null);});
 respond(200,['ok'=>true,'status'=>$to]);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not update the job']);}
