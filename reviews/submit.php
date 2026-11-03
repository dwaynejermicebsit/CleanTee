<?php
declare(strict_types=1);
/* A customer rates a finished booking. The review stays hidden until the admin publishes it. */
require_once dirname(__DIR__).'/auth/require.php';
require_once dirname(__DIR__).'/ratelimit.php';
post_only(); csrf_check(); $user=require_user('customer');
$ref=trim((string)($_POST['ref']??''));
$rating=filter_var($_POST['rating']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>5]]);
$comment=trim((string)($_POST['comment']??''));
if($ref===''||!$rating)respond(422,['ok'=>false,'message'=>'Choose a rating from 1 to 5 stars.']);
if(mb_strlen($comment)>600)respond(422,['ok'=>false,'message'=>'Please keep your comment under 600 characters.']);
try{
 $db=cleantee_db();
 ct_rate_limit($db,'review',10,3600,(string)$user['id']);
 $q=$db->prepare('SELECT b.id,b.status,b.customer_name,s.name AS service FROM bookings b JOIN services s ON s.id=b.service_id WHERE b.reference=? AND b.user_id=?');$q->execute([$ref,$user['id']]);$b=$q->fetch();
 if(!$b)respond(404,['ok'=>false,'message'=>'Booking not found.']);
 if($b['status']!=='Done')respond(409,['ok'=>false,'message'=>'You can rate a booking after the cleaning is done.']);
 // Show only the first name and the last initial, e.g. "Maria S."
 $parts=preg_split('/\s+/',trim((string)$b['customer_name']))?:[];$first=$parts[0]??'Customer';
 $display=mb_substr($first,0,40).(count($parts)>1?' '.mb_strtoupper(mb_substr(end($parts),0,1)).'.':'');
 $db->prepare('INSERT INTO reviews (booking_id,user_id,display_name,service_name,rating,comment) VALUES (?,?,?,?,?,?)')->execute([$b['id'],$user['id'],$display,$b['service'],$rating,$comment!==''?$comment:null]);
 // Optional star rating for each cleaner who worked on this booking. A problem here never loses the review above.
 $raw=json_decode((string)($_POST['staff_ratings']??''),true);
 if(is_array($raw)&&$raw){
  try{
   $c=$db->prepare('SELECT staff_id FROM booking_staff WHERE booking_id=?');$c->execute([$b['id']]);$crew=array_map('intval',$c->fetchAll(PDO::FETCH_COLUMN));
   $ins=$db->prepare('INSERT IGNORE INTO staff_ratings (booking_id,staff_id,user_id,rating) VALUES (?,?,?,?)');
   foreach($raw as $sid=>$val){
    $sid=(int)$sid;$val=filter_var($val,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>5]]);
    if($val&&in_array($sid,$crew,true))$ins->execute([$b['id'],$sid,$user['id'],$val]);
   }
  }catch(Throwable $e){error_log('CLEANTEE staff rating skipped: '.$e->getMessage());}
 }
 respond(200,['ok'=>true,'message'=>'Thank you for your review!']);
}catch(PDOException $e){
 if($e->getCode()==='23000')respond(409,['ok'=>false,'message'=>'You already rated this booking.']);
 error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not save your review.']);
}catch(Throwable $e){error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not save your review.']);}
