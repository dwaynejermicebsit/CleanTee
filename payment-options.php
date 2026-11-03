<?php
require_once __DIR__.'/bootstrap.php';
$config=require __DIR__.'/config.php';

/* ------------------------------------------------------------------
   Edit this block to change what customers see on the booking form.
   Each payment method can have several channels (shown as tabs).
   A QR image is only shown if the file exists in /images/qr/ on the site,
   so to add the PNB QR later, just upload images/qr/pnb.png.
------------------------------------------------------------------- */
$siteRoot=dirname(__DIR__);
$qr=function(string $file,string $caption)use($siteRoot):?array{
 return is_file($siteRoot.'/images/qr/'.$file)?['src'=>'images/qr/'.$file,'caption'=>$caption]:null;
};

$mobile=[['label'=>'Mobile number','value'=>'0998 577 5917','copy'=>'09985775917']];

$accounts=[
 'gcash'=>[
  'title'=>'GCash / Maya',
  'channels'=>[
   ['key'=>'gcash','label'=>'GCash','holder'=>'Ailene Donna Tee','rows'=>$mobile,
    'qr'=>$qr('gcash.png','Scan with GCash or any InstaPay-enabled app'),
    'note'=>'Check the account name before you confirm the transfer.'],
   ['key'=>'maya','label'=>'Maya','holder'=>'Ailene Donna Tee','rows'=>$mobile,
    'qr'=>$qr('maya.png','Scan with Maya or any InstaPay-enabled app'),
    'note'=>'Check the account name before you confirm the transfer.'],
  ],
 ],
 'bank'=>[
  'title'=>'Bank transfer',
  'channels'=>[
   ['key'=>'bpi','label'=>'BPI','holder'=>'Cleantee','rows'=>[],
    'qr'=>$qr('bank-instapay.png','BPI InstaPay QR. Scan with any InstaPay-enabled banking or e-wallet app.'),
    'note'=>'Transfer fees may apply. Send the exact down payment amount.'],
   ['key'=>'pnb','label'=>'PNB','holder'=>'ICETEE','rows'=>[['label'=>'PNB Savings Account','value'=>'1706 1010 8888','copy'=>'170610108888']],
    'qr'=>$qr('pnb.png','PNB QR'),
    'note'=>'Transfer fees may apply. Send the exact down payment amount.'],
  ],
 ],
];

/* Reference-number rules. Keep these in sync with the check in bookings/create.php. */
$reference=[
 'gcash'=>['min'=>10,'max'=>13,'digits_only'=>true,'placeholder'=>'e.g. 1234567890123',
           'hint'=>'10 to 13 digits, numbers only. Find it on your GCash or Maya receipt.'],
 'bank' =>['min'=>6,'max'=>30,'digits_only'=>false,'placeholder'=>'Reference or trace number',
           'hint'=>'6 to 30 letters and numbers, no spaces. Find it on your bank transfer receipt.'],
];

$instructions=$config['payment_instructions'];
$instructions['gcash']='Choose GCash or Maya below, send the down payment, then enter the reference number from your receipt.';
$instructions['bank']='Choose BPI or PNB below, transfer the down payment, then enter the reference number from your receipt.';

respond(200,['ok'=>true,'percent'=>$config['down_payment_percent'],'instructions'=>$instructions,'accounts'=>$accounts,'reference'=>$reference]);
