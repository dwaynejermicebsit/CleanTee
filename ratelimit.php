<?php
declare(strict_types=1);
/*
 * ct_rate_limit($db,'booking',5,600) allows 5 calls per 10 minutes for each visitor address.
 * Above the limit the request stops with HTTP 429. Needs the rate_hits table (sql/04_improvements.sql).
 * If the table is missing the check is skipped so the site keeps working.
 */
function ct_rate_limit(PDO $db,string $name,int $max,int $seconds,?string $who=null): void {
 try{
  $bucket=substr($name.':'.($who??($_SERVER['REMOTE_ADDR']??'unknown')),0,120);
  $seconds=max(1,$seconds);
  if(random_int(1,50)===1)$db->exec('DELETE FROM rate_hits WHERE hit_at<DATE_SUB(NOW(),INTERVAL 1 DAY)');
  $q=$db->prepare('SELECT COUNT(*) FROM rate_hits WHERE bucket=? AND hit_at>DATE_SUB(NOW(),INTERVAL '.$seconds.' SECOND)');
  $q->execute([$bucket]);
  if((int)$q->fetchColumn()>=$max)respond(429,['ok'=>false,'message'=>'Too many attempts. Please wait a few minutes and try again.']);
  $db->prepare('INSERT INTO rate_hits (bucket) VALUES (?)')->execute([$bucket]);
 }catch(PDOException $e){error_log('CLEANTEE rate limit skipped: '.$e->getMessage());}
}
