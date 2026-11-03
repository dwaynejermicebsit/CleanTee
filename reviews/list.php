<?php
declare(strict_types=1);
/* Public list of published reviews (no login needed). */
require_once dirname(__DIR__).'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='GET')respond(405,['ok'=>false,'message'=>'GET required']);
try{
 $db=cleantee_db();
 $rows=$db->query("SELECT display_name AS name,service_name AS service,rating,comment,created_at AS created FROM reviews WHERE status='Published' ORDER BY created_at DESC,id DESC LIMIT 30")->fetchAll();
 $a=$db->query("SELECT COUNT(*) AS n,AVG(rating) AS avg FROM reviews WHERE status='Published'")->fetch();
 header('Cache-Control: public, max-age=120');
 respond(200,['ok'=>true,'count'=>(int)$a['n'],'average'=>$a['n']?round((float)$a['avg'],1):null,'reviews'=>$rows]);
}catch(Throwable $e){error_log((string)$e);respond(200,['ok'=>true,'count'=>0,'average'=>null,'reviews'=>[]]);}
