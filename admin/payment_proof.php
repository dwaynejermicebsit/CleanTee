<?php
declare(strict_types=1);
/* Admin-only: shows the receipt image a customer uploaded for a checkout (?ref=CO-XXXXXXXXXXXX). */
require_once dirname(__DIR__).'/auth/require.php';
require_user('admin');
$ref=trim((string)($_GET['ref']??''));
if(!preg_match('/^CO-[A-F0-9]{12}$/',$ref)){http_response_code(422);exit('Invalid reference');}
try{
 $q=cleantee_db()->prepare('SELECT proof_file FROM checkouts WHERE reference=?');$q->execute([$ref]);$file=(string)$q->fetchColumn();
}catch(Throwable $e){error_log((string)$e);http_response_code(500);exit('Unavailable');}
$path=dirname(__DIR__).'/uploads/proofs/'.basename($file);
if($file===''||!is_file($path)){http_response_code(404);exit('No receipt uploaded');}
$types=['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
header('Content-Type: '.($types[$ext]??'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
header('Content-Length: '.filesize($path));
readfile($path);
