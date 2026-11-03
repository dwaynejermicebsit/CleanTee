<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth/require.php';
require_once dirname(__DIR__).'/ratelimit.php';
require_once dirname(__DIR__).'/notify.php';
require_once dirname(__DIR__).'/proofs.php';
post_only(); csrf_check(); $user=require_user('customer');
function invalid(string $message): never {respond(422,['ok'=>false,'error'=>'validation','message'=>$message]);}
function number_field(array $a,string $key,float $min,float $max,float $default=0,bool $integer=false): float {
 $v=$a[$key]??''; if($v==='')return $default;
 if(!is_scalar($v)||!is_numeric($v)||!is_finite((float)$v)||(float)$v<$min||(float)$v>$max||($integer&&floor((float)$v)!=(float)$v))invalid("Invalid $key");return (float)$v;
}
function text_field(array $a,string $key,int $min,int $max): string {
 $v=$a[$key]??'';if(!is_scalar($v))invalid("Invalid $key");$v=trim((string)$v);if(strlen($v)<$min||strlen($v)>$max)invalid("Please check $key");return $v;
}
function map_link_field(array $a): string {
 $v=text_field($a,'map_link',0,300);if($v==='')return '';
 $u=preg_match('/\s/',$v)?false:parse_url($v);
 $host=is_array($u)?strtolower((string)($u['host']??'')):'';
 $ok=is_array($u)&&strtolower((string)($u['scheme']??''))==='https'&&!isset($u['user'],$u['pass'])
  &&preg_match('/^(www\.)?google\.(com|com\.ph)$|^maps\.google\.(com|com\.ph)$|^maps\.app\.goo\.gl$|^goo\.gl$/',$host)===1
  &&(preg_match('/^(www\.)?google\./',$host)!==1||str_starts_with((string)($u['path']??''),'/maps'));
 if(!$ok)invalid('Use a Google Maps link (for example https://maps.app.goo.gl/... or https://www.google.com/maps/...), or leave the map link empty.');
 return $v;
}
function duplicate_reference(string $message): never {respond(409,['ok'=>false,'error'=>'duplicate_reference','message'=>$message]);}
$items=json_decode((string)($_POST['items']??'null'),true);
if(!is_array($items)||!array_is_list($items)||count($items)<1||count($items)>20)invalid('Choose between 1 and 20 bookings');
$method=(string)($_POST['payment_method']??'');
if(!in_array($method,['gcash','bank','cash'],true))invalid('Choose a payment method');
$paymentRef=trim((string)($_POST['payment_reference']??''));
if(strlen($paymentRef)>100)invalid('Payment reference is too long');
if($method==='cash'&&$paymentRef!=='')invalid('A transfer reference does not apply to cash');
/* Reference rules. Keep in sync with $reference in payment-options.php (the booking form reads them from there). */
if($method==='gcash'){
 if(!preg_match('/^[0-9]{10,13}$/',$paymentRef))invalid('GCash / Maya reference number must be 10 to 13 digits, numbers only.');
}elseif($method==='bank'){
 if(!preg_match('/^[A-Za-z0-9]{6,30}$/',$paymentRef))invalid('Bank reference number must be 6 to 30 letters and numbers, no spaces or symbols.');
}
/* A payment screenshot is required for GCash / Maya and bank transfers (not for cash). */
$proofExt=null;
if($method!=='cash')$proofExt=ct_proof_check($_FILES['proof']??null);
$key=(string)($_POST['checkout_key']??'');if(!preg_match('/^[a-f0-9-]{32,40}$/',$key))invalid('Refresh and try again');
$db=null;
try {
 $db=cleantee_db();ct_rate_limit($db,'booking',10,600,(string)$user['id']);
 $existing=$db->prepare('SELECT id,reference,user_id,estimated_total,down_payment_due FROM checkouts WHERE request_key=?');$existing->execute([$key]);$old=$existing->fetch();
 if($old){if((int)$old['user_id']!==$user['id'])invalid('Invalid checkout key');$q=$db->prepare('SELECT b.reference FROM checkout_bookings cb JOIN bookings b ON b.id=cb.booking_id WHERE cb.checkout_id=?');$q->execute([$old['id']]);respond(200,['ok'=>true,'ref'=>$old['reference'],'refs'=>$q->fetchAll(PDO::FETCH_COLUMN),'estimated_total'=>$old['estimated_total'],'down_payment_due'=>$old['down_payment_due']]);}
 $serviceQuery=$db->prepare('SELECT * FROM services WHERE code=? AND is_active=1');$rows=[];$grand=0;
 $transport=['Pateros'=>250,'Pasig'=>150,'Pasay'=>150,'Mandaluyong'=>150,'South Caloocan'=>150,'North Caloocan'=>250,'Valenzuela'=>200,'San Juan'=>150,'Marikina'=>175,'Quezon City'=>150];
 foreach($items as $a){
  if(!is_array($a))invalid('Invalid booking');
  $code=text_field($a,'service',2,40);$serviceQuery->execute([$code]);$s=$serviceQuery->fetch();if(!$s)invalid('Service unavailable');
  $name=text_field($a,'name',2,150);$phone=text_field($a,'phone',11,11);if(!preg_match('/^09[0-9]{9}$/',$phone))invalid('Mobile number must have 11 digits and start with 09');
  $city=text_field($a,'city',2,120);$address=text_field($a,'address',5,500);
  $date=text_field($a,'date',10,10);$time=text_field($a,'time',5,5);$tz=new DateTimeZone('Asia/Manila');$dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i',"$date $time",$tz);
  if(!$dt||$dt->format('Y-m-d H:i')!=="$date $time"||$dt<new DateTimeImmutable('+24 hours',$tz))invalid('Please book at least 24 hours ahead. Choose a later date or time.');
  $hours=number_field($a,'hours',0,168);$cleaners=(int)number_field($a,'cleaners',1,20,1,true);
  $sqm=number_field($a,'sqm',0,100000);$floors=number_field($a,'floors',0,200,0,true);$traps=number_field($a,'grease_trap_quantity',0,100,0,true);$aircons=number_field($a,'aircon_quantity',0,100,0,true);$parking=number_field($a,'parking_fee',0,100000);
  $notes=text_field($a,'notes',0,5000);$mapLink=map_link_field($a);$property=text_field($a,'property_type',0,80);$unit=text_field($a,'unit_number',0,80);$airType=text_field($a,'aircon_type',0,40);$rate=(float)$s['price'];
  if($s['billing_unit']==='hour'){if($hours<(float)$s['min_hours'])invalid('Please meet the minimum hours');$subtotal=$rate*$hours*$cleaners;}
  elseif($s['billing_unit']==='trap'){if($traps<1)invalid('Enter at least one grease trap');$subtotal=$rate*$traps;}
  elseif($code==='aircon'){$rates=['window'=>750,'split'=>1250,'checkup'=>499,'repair'=>0];if(!isset($rates[$airType])||$aircons<1)invalid('Choose aircon type and quantity');$cleaners=2;$rate=$rates[$airType];$subtotal=$rate*$aircons;}
  else{$subtotal=$rate;}
  if($city!=='Other'&&!isset($transport[$city]))invalid('Select a supported city');
  if($city==='Other')$notes=trim($notes."\nTransportation fee pending staff confirmation; this estimate excludes transportation.");
  $travel=$city==='Other'?0:($code==='aircon'?max(250,$transport[$city]):$transport[$city]);$parking=$code==='aircon'?$parking:0;$total=round($subtotal+$travel+$parking,2);$grand+=$total;
  $ref='CT-'.strtoupper(bin2hex(random_bytes(6)));
  $rows[]=[$ref,$user['id'],$s['id'],$name,$phone,$user['email'],$date,$time,$city,$address,$property?:null,$unit?:null,$sqm?:null,$floors?:null,$traps?:null,$cleaners,$hours?:null,$airType?:null,$aircons?:null,$parking,$notes?:null,$rate,round($subtotal,2),$travel,$total,'New',$mapLink?:null];
 }
 $config=require dirname(__DIR__).'/config.php';$percent=(int)($config['down_payment_percent']??30);if($percent<0||$percent>100)throw new RuntimeException('Invalid down-payment percentage');$due=round($grand*$percent/100,2);
 if($paymentRef!==''){
  // A receipt number can pay for one checkout only; this stops the same reference being reused.
  $dup=$db->prepare('SELECT reference,user_id FROM checkouts WHERE payment_reference=? LIMIT 1');$dup->execute([$paymentRef]);$used=$dup->fetch();
  if($used)duplicate_reference((int)$used['user_id']===(int)$user['id']
   ?'You already used this reference number for checkout '.$used['reference'].'. Open My Bookings to see it.'
   :'This reference number was already used on another booking. Enter the reference from your own payment receipt, or call CLEANTEE at +63 998 577 5917 if you think this is a mistake.');
 }
 $checkoutRef='CO-'.strtoupper(bin2hex(random_bytes(6)));
 $proofName=$proofExt?ct_proof_save($_FILES['proof'],$proofExt):null;
 $db->beginTransaction();
 $q=$db->prepare('INSERT INTO checkouts (reference,request_key,user_id,estimated_total,down_payment_due,payment_method,payment_reference,payment_status,proof_file,proof_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
 $q->execute([$checkoutRef,$key,$user['id'],$grand,$due,$method,$paymentRef?:null,$paymentRef!==''?'Awaiting verification':'Awaiting payment',$proofName,$proofName?date('Y-m-d H:i:s'):null]);$checkoutId=$db->lastInsertId();
 $insert=$db->prepare('INSERT INTO bookings (reference,user_id,service_id,customer_name,phone,email,scheduled_date,scheduled_time,city,address,property_type,unit_number,sqm,floors,grease_trap_quantity,cleaners,hours,aircon_type,aircon_quantity,parking_fee,notes,service_price_snapshot,service_subtotal,transport_estimate,estimated_total,status,map_link) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 $link=$db->prepare('INSERT INTO checkout_bookings (checkout_id,booking_id) VALUES (?,?)');$refs=[];
 foreach($rows as $row){$insert->execute($row);$link->execute([$checkoutId,$db->lastInsertId()]);$refs[]=$row[0];}
 $db->commit();
 $firstName=$rows[0][3];$count=count($refs);
 ct_after_response(function()use($db,$refs,$checkoutRef,$firstName,$count,$grand,$due,$method){
  foreach($refs as $r)ct_notify_customer($db,$r,'received');
  ct_notify_admin("New booking $checkoutRef","$firstName sent $count booking request(s).\nEstimated total: ".ct_money($grand)."\nDown payment due: ".ct_money($due)."\nPayment method: $method");
 });
 respond(201,['ok'=>true,'ref'=>$checkoutRef,'refs'=>$refs,'estimated_total'=>$grand,'down_payment_due'=>$due,'message'=>'Requests received; staff must confirm availability and payment.']);
}catch(PDOException $e){
 if($db&&$db->inTransaction())$db->rollBack();
 if(!empty($proofName))ct_proof_delete($proofName);
 // Two customers sending the same reference at the same moment: the unique index in sql/04 stops the second one.
 if((int)($e->errorInfo[1]??0)===1062&&str_contains($e->getMessage(),'payment_reference'))duplicate_reference('This reference number was already used on another booking. Enter the reference from your own payment receipt.');
 error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not save your booking. If you have not run the database upgrades (sql/03 and sql/04) yet, do that first, then try again.']);
}catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();if(!empty($proofName))ct_proof_delete($proofName);error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not save your booking. Please try again, or call CLEANTEE at +63 998 577 5917.']);}
