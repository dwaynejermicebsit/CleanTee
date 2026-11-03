<?php
declare(strict_types=1);
/* Public: each active cleaner with the average of their customer ratings (only from published reviews). */
require_once dirname(__DIR__).'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='GET')respond(405,['ok'=>false,'message'=>'GET required']);
try{
 $db=cleantee_db();
 try{
  $rows=$db->query("SELECT s.id,s.full_name AS name,s.position,COUNT(x.rating) AS n,AVG(x.rating) AS avg,
    SUM(x.rating=5) AS r5,SUM(x.rating=4) AS r4,SUM(x.rating=3) AS r3,SUM(x.rating=2) AS r2,SUM(x.rating=1) AS r1
   FROM staff s
   LEFT JOIN (SELECT sr.staff_id,sr.rating FROM staff_ratings sr JOIN reviews r ON r.booking_id=sr.booking_id WHERE r.status='Published') x ON x.staff_id=s.id
   WHERE s.status='Active' GROUP BY s.id,s.full_name,s.position ORDER BY (COUNT(x.rating)=0),AVG(x.rating) DESC,s.full_name")->fetchAll();
 }catch(PDOException $e){
  error_log('CLEANTEE staff ratings fallback (run sql/06): '.$e->getMessage());
  $rows=$db->query("SELECT id,full_name AS name,position,0 AS n,NULL AS avg,0 AS r5,0 AS r4,0 AS r3,0 AS r2,0 AS r1 FROM staff WHERE status='Active' ORDER BY full_name")->fetchAll();
 }
 $out=[];
 foreach($rows as $r)$out[]=['id'=>(int)$r['id'],'name'=>$r['name'],'position'=>$r['position'],'count'=>(int)$r['n'],'average'=>$r['n']?round((float)$r['avg'],1):null,
  'breakdown'=>['5'=>(int)$r['r5'],'4'=>(int)$r['r4'],'3'=>(int)$r['r3'],'2'=>(int)$r['r2'],'1'=>(int)$r['r1']]];
 header('Cache-Control: public, max-age=120');
 respond(200,['ok'=>true,'staff'=>$out]);
}catch(Throwable $e){error_log((string)$e);respond(200,['ok'=>true,'staff'=>[]]);}
