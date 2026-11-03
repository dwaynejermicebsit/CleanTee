<?php
declare(strict_types=1);
/* Admin: one combined activity feed (booking steps + admin actions), newest first. */
require_once dirname(__DIR__).'/auth/require.php';
require_user('admin');
if($_SERVER['REQUEST_METHOD']!=='GET')respond(405,['ok'=>false,'message'=>'GET required']);
try{
 $db=cleantee_db();$rows=[];
 foreach($db->query("SELECT l.created_at AS created,l.admin_name AS admin,l.action,b.reference AS target,CONCAT_WS(' ',IF(l.from_status<>l.to_status,CONCAT(l.from_status,' to ',l.to_status),NULL),l.note) AS detail FROM booking_log l JOIN bookings b ON b.id=l.booking_id ORDER BY l.id DESC LIMIT 200") as $r){$r['area']='Booking';$rows[]=$r;}
 try{
  foreach($db->query("SELECT created_at AS created,admin_name AS admin,action,target,detail FROM admin_log ORDER BY id DESC LIMIT 200") as $r){$r['area']='Admin';$rows[]=$r;}
 }catch(PDOException $e){error_log('CLEANTEE: run sql/04_improvements.sql ('.$e->getMessage().')');}
 usort($rows,fn($a,$b)=>strcmp((string)$b['created'],(string)$a['created']));
 respond(200,['ok'=>true,'activity'=>array_slice($rows,0,300)]);
}catch(Throwable $e){error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not load the activity log']);}
