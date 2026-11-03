<?php
declare(strict_types=1);
/* Shared by bookings/create.php and bookings/proof.php: checks and stores a payment screenshot. */

/* Returns the file extension (jpg/png/webp) or stops with HTTP 422. */
function ct_proof_check($f): string {
 if(!$f||!is_array($f)||($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)$f['tmp_name']))
  respond(422,['ok'=>false,'error'=>'proof','message'=>'Please attach a screenshot of your payment (JPG, PNG or WebP, up to 4 MB).']);
 if((int)$f['size']>4*1024*1024)respond(422,['ok'=>false,'error'=>'proof','message'=>'The screenshot is too large. Please use an image under 4 MB.']);
 $info=@getimagesize((string)$f['tmp_name']);
 $types=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp'];
 if(!$info||!isset($types[$info[2]])||$info[0]>8000||$info[1]>8000)respond(422,['ok'=>false,'error'=>'proof','message'=>'Only JPG, PNG or WebP images are accepted for the payment screenshot.']);
 return $types[$info[2]];
}

/* Moves the checked upload into backend/uploads/proofs and returns the stored file name. */
function ct_proof_save($f,string $ext): string {
 $dir=__DIR__.'/uploads/proofs';
 if(!is_dir($dir)&&!@mkdir($dir,0755,true))throw new RuntimeException('Cannot create the proofs folder');
 $name=bin2hex(random_bytes(16)).'.'.$ext;
 if(!move_uploaded_file((string)$f['tmp_name'],$dir.'/'.$name))throw new RuntimeException('Cannot save the file');
 return $name;
}

function ct_proof_delete(?string $name): void {
 if($name)@unlink(__DIR__.'/uploads/proofs/'.basename($name));
}
