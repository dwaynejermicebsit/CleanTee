<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth/require.php';
post_only();csrf_check();
$user=require_user('customer');
function reject_report(string $m): never {respond(422,['ok'=>false,'message'=>$m]);}
$name=trim((string)($_POST['name']??''));$contact=trim((string)($_POST['contact']??''));
$bookingRef=trim((string)($_POST['booking_ref']??''));$date=(string)($_POST['service_date']??'');
$type=(string)($_POST['type']??'');$severity=(string)($_POST['severity']??'Low');$details=trim((string)($_POST['details']??''));
if(mb_strlen($name)<2||mb_strlen($name)>150||mb_strlen($contact)<5||mb_strlen($contact)>254)reject_report('Enter your name and contact details');
if(!in_array($type,['missed','damage','missing','staff','other'],true)||!in_array($severity,['Low','Medium','High'],true))reject_report('Invalid report category');
if(mb_strlen($details)<10||mb_strlen($details)>10000)reject_report('Please describe the issue (10–10000 characters)');
// Use the same timezone for both dates
$timezone = new DateTimeZone('Asia/Manila');

$d = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
$today = new DateTimeImmutable('today', $timezone);

// Check if the date is valid or in the future
if (
    !$d ||
    $d->format('Y-m-d') !== $date ||
    $d > $today
) {
    reject_report('Invalid service date');
}
if(strlen($bookingRef)>32)reject_report('Invalid booking reference');
$files=$_FILES['photos']??null;$uploads=[];$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if($files&&is_array($files['name'])){
 if(count($files['name'])>5)reject_report('Maximum 5 photos');
 $finfo=new finfo(FILEINFO_MIME_TYPE);
 foreach($files['name'] as $i=>$orig){
  if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
  if($files['error'][$i]!==UPLOAD_ERR_OK||$files['size'][$i]>5*1024*1024||$files['size'][$i]<1)reject_report('Each photo must be 5 MB or less');
  $tmp=$files['tmp_name'][$i];$mime=$finfo->file($tmp);if(!isset($allowed[$mime])||!getimagesize($tmp))reject_report('Only valid JPG, PNG, and WebP photos are accepted');
  $uploads[]=['tmp'=>$tmp,'name'=>mb_substr(basename((string)$orig),0,255),'mime'=>$mime,'size'=>(int)$files['size'][$i],'ext'=>$allowed[$mime]];
 }
}
$dir=dirname(__DIR__,2).'/uploads/report_photos';if(!is_dir($dir)&&!mkdir($dir,0750,true))respond(500,['ok'=>false,'message'=>'Upload directory unavailable']);
$saved=[];$db=null;
try{
 $db=cleantee_db();$bookingId=null;
 if($bookingRef!==''){
  $s=$db->prepare('SELECT id FROM bookings WHERE reference=? AND user_id=?');$s->execute([$bookingRef,$user['id']]);$bookingId=$s->fetchColumn();
  if(!$bookingId)reject_report('Booking reference not found in your account');
 }
 $db->beginTransaction();$ref='RP-'.strtoupper(bin2hex(random_bytes(6)));
 $q=$db->prepare('INSERT INTO reports (reference,user_id,booking_id,customer_name,contact,booking_reference_entered,service_date,issue_type,severity,details,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
 $q->execute([$ref,$user['id'],$bookingId?:null,$name,$contact,$bookingRef?:null,$date,$type,$severity,$details,'New']);$id=$db->lastInsertId();
 $p=$db->prepare('INSERT INTO report_photos (report_id,stored_filename,original_filename,mime_type,file_size_bytes) VALUES (?,?,?,?,?)');
 foreach($uploads as $f){$stored=bin2hex(random_bytes(16)).'.'.$f['ext'];$dest=$dir.'/'.$stored;
  if(!move_uploaded_file($f['tmp'],$dest))throw new RuntimeException('Photo upload failed');$saved[]=$dest;
  $p->execute([$id,$stored,$f['name'],$f['mime'],$f['size']]);
 }
 $db->commit();respond(201,['ok'=>true,'ref'=>$ref,'status'=>'New','message'=>'Report received']);
}catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();foreach($saved as $f)@unlink($f);error_log($e);respond(500,['ok'=>false,'message'=>'Unable to save report']);}
