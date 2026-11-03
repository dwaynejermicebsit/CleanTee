<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth/require.php';
require_user('admin');
$ref=trim((string)($_GET['ref']??''));if($ref==='')respond(422,['ok'=>false,'message'=>'Missing report reference']);
$q=cleantee_db()->prepare('SELECT p.id,p.original_filename FROM report_photos p JOIN reports r ON r.id=p.report_id WHERE r.reference=? ORDER BY p.id');$q->execute([$ref]);respond(200,['ok'=>true,'photos'=>$q->fetchAll()]);
