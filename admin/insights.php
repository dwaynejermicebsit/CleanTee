<?php
declare(strict_types=1);
/* Admin: numbers for the Insights tab (income, bookings per month, popular services, cancellations, ratings). */
require_once dirname(__DIR__).'/auth/require.php';
require_user('admin');
if($_SERVER['REQUEST_METHOD']!=='GET')respond(405,['ok'=>false,'message'=>'GET required']);
try{
 $db=cleantee_db();
 $tz=new DateTimeZone('Asia/Manila');
 $months=[];$d=new DateTimeImmutable('first day of this month',$tz);
 for($i=5;$i>=0;$i--){$m=$d->modify("-$i months");$months[$m->format('Y-m')]=['month'=>$m->format('Y-m'),'label'=>$m->format('M Y'),'bookings'=>0,'done'=>0,'lost'=>0,'revenue'=>0.0];}
 $first=array_key_first($months).'-01';
 $q=$db->prepare("SELECT DATE_FORMAT(scheduled_date,'%Y-%m') AS ym,COUNT(*) AS total,SUM(status='Done') AS done,SUM(status IN ('Cancelled','Declined')) AS lost,SUM(CASE WHEN status='Done' THEN estimated_total ELSE 0 END) AS revenue FROM bookings WHERE scheduled_date>=? GROUP BY ym");
 $q->execute([$first]);
 foreach($q->fetchAll() as $r){if(isset($months[$r['ym']])){$months[$r['ym']]['bookings']=(int)$r['total'];$months[$r['ym']]['done']=(int)$r['done'];$months[$r['ym']]['lost']=(int)$r['lost'];$months[$r['ym']]['revenue']=(float)$r['revenue'];}}
 $services=$db->query("SELECT s.name,COUNT(*) AS bookings,SUM(CASE WHEN b.status='Done' THEN b.estimated_total ELSE 0 END) AS revenue FROM bookings b JOIN services s ON s.id=b.service_id WHERE b.status NOT IN ('Declined','Cancelled') GROUP BY s.id,s.name ORDER BY bookings DESC,revenue DESC LIMIT 8")->fetchAll();
 $t=$db->query("SELECT COUNT(*) AS total,SUM(status='Done') AS done,SUM(status='Cancelled') AS cancelled,SUM(status='Declined') AS declined,SUM(status IN ('New','Confirmed','Assigned','In Progress')) AS open,SUM(CASE WHEN status='Done' THEN estimated_total ELSE 0 END) AS revenue FROM bookings")->fetch();
 $p=$db->query("SELECT SUM(payment_status='Verified') AS verified,SUM(payment_status<>'Verified') AS unverified,SUM(CASE WHEN payment_status='Verified' THEN down_payment_due ELSE 0 END) AS verified_amount FROM checkouts")->fetch();
 $rating=['average'=>null,'count'=>0];
 try{$r=$db->query("SELECT COUNT(*) AS n,AVG(rating) AS avg FROM reviews WHERE status<>'Hidden'")->fetch();$rating=['average'=>$r['n']?round((float)$r['avg'],1):null,'count'=>(int)$r['n']];}catch(PDOException $e){}
 $total=(int)$t['total'];
 respond(200,['ok'=>true,'months'=>array_values($months),'services'=>$services,
  'totals'=>['bookings'=>$total,'done'=>(int)$t['done'],'cancelled'=>(int)$t['cancelled'],'declined'=>(int)$t['declined'],'open'=>(int)$t['open'],'revenue'=>(float)$t['revenue'],
   'cancel_rate'=>$total?round((((int)$t['cancelled']+(int)$t['declined'])/$total)*100,1):0],
  'payments'=>['verified'=>(int)$p['verified'],'unverified'=>(int)$p['unverified'],'verified_amount'=>(float)$p['verified_amount']],
  'rating'=>$rating]);
}catch(Throwable $e){error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not load insights']);}
