<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth/require.php';
require_user('admin');
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id)respond(400,['ok'=>false,'message'=>'Invalid photo ID']);
$q=cleantee_db()->prepare('SELECT stored_filename,mime_type FROM report_photos WHERE id=?');$q->execute([$id]);$f=$q->fetch();
if(!$f||!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/',$f['stored_filename'])){http_response_code(404);exit;}
$file=dirname(__DIR__,2).'/uploads/report_photos/'.$f['stored_filename'];
if(!is_file($file)){http_response_code(404);exit;}
header('Content-Type: '.$f['mime_type']);header('X-Content-Type-Options: nosniff');header('Content-Disposition: inline; filename="report-photo"');header('Cache-Control: private, no-store');readfile($file);
