<?php
declare(strict_types=1);
/* Records an admin action in admin_log. Never stops the main action if logging fails. */
function ct_admin_log(PDO $db,$admin,string $action,?string $target=null,?string $detail=null): void {
 try{
  $id=is_array($admin)?(int)($admin['id']??0):0;
  $name=is_array($admin)?(string)($admin['name']??$admin['full_name']??'Admin'):'Admin';
  $db->prepare('INSERT INTO admin_log (admin_id,admin_name,action,target,detail) VALUES (?,?,?,?,?)')
     ->execute([$id?:null,mb_substr($name,0,150),mb_substr($action,0,40),$target!==null?mb_substr($target,0,80):null,$detail!==null?mb_substr($detail,0,255):null]);
 }catch(Throwable $e){error_log('CLEANTEE admin log failed: '.$e->getMessage());}
}
