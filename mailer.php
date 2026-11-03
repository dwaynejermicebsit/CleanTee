<?php
declare(strict_types=1);
/*
 * Small SMTP mail sender (no extra libraries needed).
 * Settings live in config.php: smtp_host, smtp_port, smtp_secure ('ssl' or 'tls' or 'none'),
 * smtp_user, smtp_pass, mail_from, mail_from_name.
 * Returns true when the server accepted the message, false otherwise (reason goes to the PHP error log).
 */
function ct_send_mail(string $to, string $subject, string $body): bool {
 $cfg=require __DIR__.'/config.php';
 $host=(string)($cfg['smtp_host']??'');$user=(string)($cfg['smtp_user']??'');$pass=(string)($cfg['smtp_pass']??'');
 $from=(string)($cfg['mail_from']??$user);$fromName=(string)($cfg['mail_from_name']??'CLEANTEE');
 $secure=(string)($cfg['smtp_secure']??'ssl');$port=(int)($cfg['smtp_port']??($secure==='tls'?587:465));
 if($host===''||($secure!=='none'&&$pass==='')){error_log('CLEANTEE mail: SMTP is not configured (set smtp_host, smtp_user and smtp_pass in config.php).');return false;}
 if(preg_match('/[\r\n]/',$to.$subject.$from.$fromName)){error_log('CLEANTEE mail: header injection blocked.');return false;}
 $fp=null;
 try{
  $ctx=stream_context_create(['ssl'=>['verify_peer'=>($cfg['smtp_verify_peer']??true)!==false,'verify_peer_name'=>($cfg['smtp_verify_peer']??true)!==false]]);
  $fp=@stream_socket_client(($secure==='ssl'?'ssl://':'tcp://').$host.':'.$port,$errno,$errstr,15,STREAM_CLIENT_CONNECT,$ctx);
  if(!$fp)throw new RuntimeException("cannot connect to $host:$port ($errstr)");
  stream_set_timeout($fp,15);
  $read=function()use($fp):array{$text='';while(($l=fgets($fp,1024))!==false){$text.=$l;if(strlen($l)<4||$l[3]===' ')break;}return [(int)substr($text,0,3),trim($text)];};
  $cmd=function(string $c,array $ok)use($fp,$read):string{if($c!=='')fwrite($fp,$c."\r\n");[$code,$text]=$read();if(!in_array($code,$ok,true))throw new RuntimeException("SMTP said: $text");return $text;};
  $cmd('',[220]);
  $me=$_SERVER['SERVER_NAME']??'localhost';
  $cmd("EHLO $me",[250]);
  if($secure==='tls'){$cmd('STARTTLS',[220]);if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('TLS handshake failed');$cmd("EHLO $me",[250]);}
  if($user!==''){$cmd('AUTH LOGIN',[334]);$cmd(base64_encode($user),[334]);$cmd(base64_encode($pass),[235]);}
  $cmd("MAIL FROM:<$from>",[250]);
  $cmd("RCPT TO:<$to>",[250,251]);
  $cmd('DATA',[354]);
  $enc=fn(string $s)=>'=?UTF-8?B?'.base64_encode($s).'?=';
  $headers=['Date: '.date('r'),'From: '.$enc($fromName)." <$from>","To: <$to>",'Subject: '.$enc($subject),'Message-ID: <'.bin2hex(random_bytes(12)).'@cleantee>','MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: base64'];
  $msg=implode("\r\n",$headers)."\r\n\r\n".chunk_split(base64_encode($body),76,"\r\n");
  fwrite($fp,$msg."\r\n.\r\n");[$code,$text]=$read();
  if($code!==250)throw new RuntimeException("SMTP said: $text");
  @fwrite($fp,"QUIT\r\n");fclose($fp);
  return true;
 }catch(Throwable $e){
  error_log('CLEANTEE mail: '.$e->getMessage());
  if(is_resource($fp))@fclose($fp);
  return false;
 }
}
