<?php
require_once dirname(__DIR__).'/bootstrap.php';
try { respond(200,['ok'=>true,'user'=>current_user(),'csrf'=>csrf_token()]); }
catch (Throwable $e) { error_log($e); respond(500,['ok'=>false,'message'=>'Session unavailable. Check MySQL and backend/config.php.']); }
