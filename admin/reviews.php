<?php
declare(strict_types=1);
/* Admin: list all reviews (GET) and publish or hide one (POST id + status). */
require_once dirname(__DIR__).'/auth/require.php';
require_once dirname(__DIR__).'/adminlog.php';
$admin=require_user('admin');
try{
 $db=cleantee_db();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  post_only();csrf_check();
  $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$st=(string)($_POST['status']??'');
  if(!$id||!in_array($st,['Pending','Published','Hidden'],true))respond(422,['ok'=>false,'message'=>'Invalid request']);
  $q=$db->prepare('UPDATE reviews SET status=? WHERE id=?');$q->execute([$st,$id]);
  ct_admin_log($db,$admin,'review_'.strtolower($st),'review #'.$id);
 }
 $rows=$db->query("SELECT r.id,r.created_at AS created,r.display_name AS name,r.service_name AS service,r.rating,r.comment,r.status,b.reference AS booking_ref FROM reviews r JOIN bookings b ON b.id=r.booking_id ORDER BY r.created_at DESC,r.id DESC LIMIT 500")->fetchAll();
 respond(200,['ok'=>true,'reviews'=>$rows]);
}catch(Throwable $e){error_log((string)$e);respond(500,['ok'=>false,'message'=>'Could not load reviews. Run sql/04_improvements.sql.']);}
