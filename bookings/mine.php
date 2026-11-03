<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

// Ensure user is logged in
$user = current_user();
if (!$user || $user['role'] !== 'customer') {
    respond(401, ['ok' => false, 'message' => 'Unauthorized']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, ['ok' => false, 'message' => 'GET method required']);
}

try {
    $db = cleantee_db();

    // Customer's own bookings, with the booking stage, any decline/cancel reason,
    // the assigned crew (names only) and the down-payment status.
    $sql = "
        SELECT
            b.reference AS ref,
            b.created_at AS created,
            b.scheduled_date AS date,
            b.scheduled_time AS time,
            b.hours,
            b.cleaners,
            CONCAT(b.city, ', ', b.address) AS address,
            b.notes,
            b.status,
            b.status_reason AS reason,
            b.estimated_total,
            s.name AS service,
            c.reference AS checkout_ref,
            c.payment_method,
            c.payment_status,
            c.down_payment_due,
            c.payment_reference,
            (SELECT GROUP_CONCAT(st.full_name ORDER BY st.full_name SEPARATOR ', ')
               FROM booking_staff bs JOIN staff st ON st.id = bs.staff_id
              WHERE bs.booking_id = b.id) AS crew,
            (SELECT GROUP_CONCAT(st.id ORDER BY st.full_name SEPARATOR ',')
               FROM booking_staff bs JOIN staff st ON st.id = bs.staff_id
              WHERE bs.booking_id = b.id) AS crew_ids
            %EXTRA%
        FROM bookings b
        JOIN services s ON s.id = b.service_id
        LEFT JOIN checkout_bookings cb ON cb.booking_id = b.id
        LEFT JOIN checkouts c ON c.id = cb.checkout_id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC, b.id DESC
    ";
    // Newer columns (receipt upload, change requests, reviews) come from sql/04_improvements.sql.
    // If that upgrade has not been run yet, fall back to the basic list so the page still works.
    $extra = ",
            b.map_link,
            (c.proof_file IS NOT NULL) AS has_proof,
            (SELECT COUNT(*) FROM booking_requests q WHERE q.booking_id = b.id AND q.status = 'Pending') AS pending_request,
            (SELECT r.rating FROM reviews r WHERE r.booking_id = b.id) AS review_rating,
            (SELECT r.status FROM reviews r WHERE r.booking_id = b.id) AS review_status";
    try {
        $stmt = $db->prepare(str_replace('%EXTRA%', $extra, $sql));
        $stmt->execute([$user['id']]);
    } catch (PDOException $e) {
        error_log('CLEANTEE mine.php fallback: ' . $e->getMessage());
        $stmt = $db->prepare(str_replace('%EXTRA%', '', $sql));
        $stmt->execute([$user['id']]);
    }
    $bookings = $stmt->fetchAll();

    // Receipt re-send requests from the admin (sql/07). Kept separate so the page still works before that upgrade.
    $asked = [];
    try {
        $rq = $db->prepare('SELECT reference, proof_request_note FROM checkouts WHERE user_id = ? AND proof_requested_at IS NOT NULL');
        $rq->execute([$user['id']]);
        foreach ($rq->fetchAll() as $x) { $asked[$x['reference']] = (string)($x['proof_request_note'] ?? ''); }
    } catch (PDOException $e) { /* sql/07 not run yet */ }

    // Crew is only shown once the job is assigned or under way, and never for closed bookings.
    foreach ($bookings as &$row) {
        $ck = $row['checkout_ref'] ?? null;
        $row['proof_requested'] = ($ck !== null && isset($asked[$ck])) ? 1 : 0;
        $row['proof_request_note'] = $row['proof_requested'] ? $asked[$ck] : null;
        if (!in_array($row['status'], ['Assigned', 'In Progress', 'Done'], true)) {
            $row['crew'] = null;
            $row['crew_ids'] = null;
        }
        if (!in_array($row['status'], ['Declined', 'Cancelled'], true)) {
            $row['reason'] = null;
        }
    }
    unset($row);

    respond(200, ['ok' => true, 'bookings' => $bookings]);

} catch (Throwable $e) {
    error_log($e->getMessage());
    respond(500, ['ok' => false, 'message' => 'Unable to fetch booking history']);
}
