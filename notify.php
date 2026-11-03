<?php
declare(strict_types=1);
require_once __DIR__.'/mailer.php';

/* Runs $fn after the response has been sent, so a slow mail server never makes the page wait. */
function ct_after_response(callable $fn): void {
 ignore_user_abort(true);
 register_shutdown_function(function() use ($fn){
  if(function_exists('fastcgi_finish_request'))@fastcgi_finish_request();
  try{$fn();}catch(Throwable $e){error_log('CLEANTEE notify failed: '.$e->getMessage());}
 });
}

function ct_money($n): string { return 'PHP '.number_format((float)$n,2); }
function ct_when(string $date,string $time): string {
 $d=DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$date.' '.(strlen($time)===5?$time.':00':$time),new DateTimeZone('Asia/Manila'));
 return $d?$d->format('D, M j, Y \a\t g:i A'):"$date $time";
}

/*
 * Emails the customer about a booking event. Events:
 * received, confirmed, declined, assigned, started, done, cancelled, payment_verified,
 * request_cancel_approved, request_cancel_rejected, request_reschedule_approved, request_reschedule_rejected
 * Call it with the booking reference; $note is an optional reason or message.
 */
function ct_notify_customer(PDO $db,string $ref,string $event,?string $note=null): void {
 /* The customer gets only three emails per booking: confirmed, started and done (kept short on purpose). */
 if(!in_array($event,['confirmed','started','done'],true))return;
 $q=$db->prepare('SELECT b.id,b.reference,b.customer_name,b.email,b.scheduled_date,b.scheduled_time,b.city,b.address,b.estimated_total,s.name AS service,u.email AS account_email FROM bookings b JOIN services s ON s.id=b.service_id JOIN users u ON u.id=b.user_id WHERE b.reference=?');
 $q->execute([$ref]);$b=$q->fetch();if(!$b)return;
 $to=filter_var((string)$b['email'],FILTER_VALIDATE_EMAIL)?(string)$b['email']:(string)$b['account_email'];
 if(!filter_var($to,FILTER_VALIDATE_EMAIL))return;
 $when=ct_when((string)$b['scheduled_date'],(string)$b['scheduled_time']);
 $crew='';
 if($event==='assigned'){
  $c=$db->prepare('SELECT st.full_name FROM booking_staff bs JOIN staff st ON st.id=bs.staff_id WHERE bs.booking_id=? ORDER BY st.full_name');$c->execute([$b['id']]);
  $crew=implode(', ',$c->fetchAll(PDO::FETCH_COLUMN));
 }
 $subjects=[
  'received'=>'We received your booking',
  'payment_verified'=>'Your payment was verified',
  'confirmed'=>'Your booking is confirmed',
  'declined'=>'Update on your booking request',
  'assigned'=>'Your cleaning crew is assigned',
  'started'=>'Your cleaning has started',
  'done'=>'Your cleaning is complete',
  'cancelled'=>'Your booking was cancelled',
  'request_cancel_approved'=>'Your cancellation request was approved',
  'request_cancel_rejected'=>'Your cancellation request was not approved',
  'request_reschedule_approved'=>'Your new schedule is confirmed',
  'request_reschedule_rejected'=>'Your reschedule request was not approved',
 ];
 if(!isset($subjects[$event]))return;
 $lines=[
  'received'=>"We received your booking request. Our team will check your down payment and confirm your schedule.",
  'payment_verified'=>"We have verified your down payment. We will confirm your schedule shortly.",
  'confirmed'=>"Good news! Your booking is confirmed. We will assign your cleaning crew next.",
  'declined'=>"Sorry, we could not accept this booking request.".($note?"\nReason: $note":'')."\nPlease contact us if you would like to book another schedule.",
  'assigned'=>"Your cleaning crew is ready.".($crew?"\nCrew: $crew":''),
  'started'=>"Our crew has started your cleaning service.",
  'done'=>"Your cleaning is complete. Thank you for choosing CLEANTEE! You can rate our service in My Bookings.",
  'cancelled'=>"Your booking was cancelled.".($note?"\nReason: $note":''),
  'request_cancel_approved'=>"We approved your cancellation request. Your booking is now cancelled.".($note?"\nNote: $note":''),
  'request_cancel_rejected'=>"We could not approve your cancellation request.".($note?"\nNote: $note":'')."\nPlease contact us if you have questions.",
  'request_reschedule_approved'=>"We approved your reschedule request. Your new schedule is shown below.".($note?"\nNote: $note":''),
  'request_reschedule_rejected'=>"We could not approve your reschedule request. Your original schedule stays.".($note?"\nNote: $note":''),
 ];
 $cfg=require __DIR__.'/config.php';
 $site=rtrim((string)($cfg['app_url']??''),'/');
 $body="Hello {$b['customer_name']},\n\n{$lines[$event]}\n\n".
  "Booking: {$b['reference']}\nService: {$b['service']}\nWhen: $when\nAddress: {$b['address']}, {$b['city']}\nEstimated total: ".ct_money($b['estimated_total'])."\n\n".
  ($site?"See all your bookings: $site/history.html\n\n":'').
  "Questions? Call or Viber +63 998 577 5917.\nCLEANTEE Cleaning Services";
 ct_send_mail($to,$subjects[$event].' - '.$b['reference'],$body);
}

/* Emails the CLEANTEE admin inbox about something that needs attention. */
function ct_notify_admin(string $subject,string $body): void {
 $cfg=require __DIR__.'/config.php';
 $to=(string)($cfg['admin_notify_email']??$cfg['mail_from']??'');
 if(!filter_var($to,FILTER_VALIDATE_EMAIL))return;
 $site=rtrim((string)($cfg['app_url']??''),'/');
 ct_send_mail($to,$subject,$body.($site?"\n\nOpen the admin page: $site/admin.html":''));
}
