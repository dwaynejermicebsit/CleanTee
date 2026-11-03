<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth/require.php';
require_user('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') respond(405,['ok'=>false,'message'=>'GET required']);
try {
 $db=cleantee_db();
 $bsql="SELECT b.reference AS ref,b.created_at AS created,b.customer_name AS name,b.phone,s.name AS service,b.hours,b.cleaners,b.scheduled_date AS date,b.scheduled_time AS time,CONCAT(b.city,', ',b.address) AS address,b.notes,b.status,b.status_reason AS reason,b.estimated_total,
   %EXTRA%c.reference AS checkout_ref,c.payment_method,c.payment_status,c.down_payment_due,
   (SELECT COUNT(*) FROM booking_staff bs WHERE bs.booking_id=b.id) AS crew_count,
   (SELECT GROUP_CONCAT(st.full_name ORDER BY st.full_name SEPARATOR ', ') FROM booking_staff bs JOIN staff st ON st.id=bs.staff_id WHERE bs.booking_id=b.id) AS crew
  FROM bookings b JOIN services s ON s.id=b.service_id
  LEFT JOIN checkout_bookings cb ON cb.booking_id=b.id LEFT JOIN checkouts c ON c.id=cb.checkout_id
  ORDER BY b.created_at DESC,b.id DESC LIMIT 1000";
 // pending_request needs sql/04_improvements.sql; without it the list still loads.
 try{$b=$db->query(str_replace('%EXTRA%',"(SELECT COUNT(*) FROM booking_requests q WHERE q.booking_id=b.id AND q.status='Pending') AS pending_request,\n   (c.proof_file IS NOT NULL) AS has_proof,",$bsql))->fetchAll();}
 catch(PDOException $e){error_log('CLEANTEE list.php fallback: '.$e->getMessage());$b=$db->query(str_replace('%EXTRA%','',$bsql))->fetchAll();}
 $r=$db->query("SELECT r.reference AS ref,r.created_at AS created,r.customer_name AS name,r.contact,r.booking_reference_entered AS booking_ref,r.service_date,r.issue_type AS type,r.severity,r.details,r.status,(SELECT COUNT(*) FROM report_photos p WHERE p.report_id=r.id) AS photos FROM reports r ORDER BY r.created_at DESC,r.id DESC LIMIT 1000")->fetchAll();
 $c=$db->query("SELECT id,created_at AS created,full_name AS name,email,phone,status,role FROM users WHERE role='customer' ORDER BY created_at DESC,id DESC LIMIT 1000")->fetchAll();
 respond(200,['ok'=>true,'booking'=>$b,'report'=>$r,'customer'=>$c]);
} catch(Throwable $e){error_log((string)$e);respond(500,['ok'=>false,'message'=>'Unable to load dashboard data']);}
