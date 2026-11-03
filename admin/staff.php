<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth/require.php';
require_once dirname(__DIR__).'/adminlog.php';
$admin=require_user('admin');
/* Staff records only. Assigning staff to bookings now happens in booking_action.php
   (with availability checks and automatic status changes). */
$db=cleantee_db();
if($_SERVER['REQUEST_METHOD']==='GET'){
 try{
  try{$staff=$db->query('SELECT s.id,s.full_name,s.position,s.phone,s.email,s.status,s.created_at,s.user_id,u.email AS login_email FROM staff s LEFT JOIN users u ON u.id=s.user_id ORDER BY s.id DESC')->fetchAll();}
  catch(PDOException $e){$staff=$db->query('SELECT id,full_name,position,phone,email,status,created_at FROM staff ORDER BY id DESC')->fetchAll();} // sql/05 not run yet
  $assigned=$db->query("SELECT bs.booking_id,bs.staff_id,b.reference AS booking_ref,b.status AS booking_status,b.scheduled_date AS date,b.scheduled_time AS time,s.full_name AS staff_name FROM booking_staff bs JOIN bookings b ON b.id=bs.booking_id JOIN staff s ON s.id=bs.staff_id WHERE b.status IN ('Confirmed','Assigned','In Progress') ORDER BY b.scheduled_date,b.scheduled_time,s.full_name")->fetchAll();
  respond(200,['ok'=>true,'staff'=>$staff,'assignments'=>$assigned]);
 }catch(Throwable $e){error_log((string)$e);respond(500,['ok'=>false,'message'=>'Unable to load staff']);}
}
post_only();csrf_check();
$action=(string)($_POST['action']??'');
try{
 if($action==='save'){
  $id=filter_var($_POST['id']??'0',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]])?:0;
  $name=trim((string)($_POST['name']??''));$position=trim((string)($_POST['position']??''));$phone=trim((string)($_POST['phone']??''));$email=trim((string)($_POST['email']??''));$status=(string)($_POST['status']??'Active');
  if(mb_strlen($name)<2||mb_strlen($name)>150||mb_strlen($position)<2||mb_strlen($position)>100||($phone!==''&&!preg_match('/^09[0-9]{9}$/',$phone))||($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))||!in_array($status,['Active','Inactive'],true))respond(422,['ok'=>false,'message'=>'Invalid staff details']);
  if($id&&$status==='Inactive'){
   $c=$db->prepare("SELECT COUNT(*) FROM booking_staff bs JOIN bookings b ON b.id=bs.booking_id WHERE bs.staff_id=? AND b.status IN ('Confirmed','Assigned','In Progress')");
   $c->execute([$id]);
   if((int)$c->fetchColumn()>0)respond(409,['ok'=>false,'message'=>'This staff member is on active bookings. Remove them from those bookings before deactivating.']);
  }
  if($id){$q=$db->prepare('UPDATE staff SET full_name=?,position=?,phone=?,email=?,status=? WHERE id=?');$q->execute([$name,$position,$phone?:null,$email?:null,$status,$id]);}
  else{$q=$db->prepare('INSERT INTO staff (full_name,position,phone,email,status) VALUES (?,?,?,?,?)');$q->execute([$name,$position,$phone?:null,$email?:null,$status]);$id=(int)$db->lastInsertId();}
  ct_admin_log($db,$admin,$status==='Inactive'?'staff_inactive':'staff_save','staff #'.$id,$name.' - '.$position);
  respond(200,['ok'=>true,'id'=>$id]);
 }
 if($action==='create_login'){
  $id=filter_var($_POST['id']??'0',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!$id)respond(422,['ok'=>false,'message'=>'Choose a staff member']);
  $q=$db->prepare('SELECT id,full_name,phone,email,user_id FROM staff WHERE id=?');$q->execute([$id]);$st=$q->fetch();
  if(!$st)respond(404,['ok'=>false,'message'=>'Staff member not found']);
  $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';$temp='';
  for($i=0;$i<10;$i++)$temp.=$alphabet[random_int(0,strlen($alphabet)-1)];
  $hash=password_hash($temp,PASSWORD_DEFAULT);
  if($st['user_id']){
   $u=$db->prepare("SELECT id,email FROM users WHERE id=? AND role='staff'");$u->execute([$st['user_id']]);$ux=$u->fetch();
   if(!$ux)respond(404,['ok'=>false,'message'=>'The linked login was not found']);
   $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash,$ux['id']]);
   ct_admin_log($db,$admin,'staff_login_reset',$ux['email'],'Temporary password created for '.$st['full_name']);
   respond(200,['ok'=>true,'name'=>$st['full_name'],'email'=>$ux['email'],'temporary_password'=>$temp,'created'=>false]);
  }
  $email=strtolower(trim((string)($_POST['email']??'')));if($email==='')$email=strtolower((string)$st['email']);
  if(!filter_var($email,FILTER_VALIDATE_EMAIL))respond(422,['ok'=>false,'message'=>'Add a valid email to this staff member first. It becomes their login.']);
  if(!preg_match('/^09[0-9]{9}$/',(string)$st['phone']))respond(422,['ok'=>false,'message'=>'Add a valid mobile number (09XXXXXXXXX) to this staff member first.']);
  try{
   $db->beginTransaction();
   $db->prepare("INSERT INTO users (full_name,email,phone,password_hash,role,status) VALUES (?,?,?,?,'staff','Active')")->execute([$st['full_name'],$email,$st['phone'],$hash]);
   $uid=(int)$db->lastInsertId();
   $db->prepare('UPDATE staff SET user_id=?,email=? WHERE id=?')->execute([$uid,$email,$id]);
   $db->commit();
  }catch(PDOException $e){
   if($db->inTransaction())$db->rollBack();
   if($e->getCode()==='23000')respond(409,['ok'=>false,'message'=>'That email already has an account. Use a different email for this staff member.']);
   throw $e;
  }
  ct_admin_log($db,$admin,'staff_login_create',$email,'Staff login created for '.$st['full_name']);
  respond(200,['ok'=>true,'name'=>$st['full_name'],'email'=>$email,'temporary_password'=>$temp,'created'=>true]);
 }
 respond(422,['ok'=>false,'message'=>'Unknown action']);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log((string)$e);respond(500,['ok'=>false,'message'=>'Unable to save staff changes']);}
