<?php
declare(strict_types=1);
// Phase 2 local-only settings. Change these if your XAMPP MySQL credentials differ.
return [
    'app_url' => 'http://localhost/CLEANTEE',
    // --- Email (password reset links) ---
    // Gmail: turn on 2-Step Verification, create an App Password, and paste it below (16 letters, no spaces).
    'mail_from'      => 'dp.jermice.bsit@gmail.com',
    'mail_from_name' => 'CLEANTEE',
    'smtp_host'      => 'smtp.gmail.com',
    'smtp_port'      => 465,
    'smtp_secure'    => 'ssl',
    'smtp_user'      => 'dp.jermice.bsit@gmail.com',
    'smtp_pass'      => 'qktlvuhueycuszgh',   // <-- paste the Gmail App Password here
    'down_payment_percent' => 30,
    'payment_instructions' => [
        'gcash' => 'Contact CLEANTEE at +63 998 577 5917 for the verified GCash account before sending payment.',
        'bank' => 'Contact CLEANTEE at +63 998 577 5917 for verified bank details before sending payment.',
        'cash' => 'Arrange your cash down payment directly with CLEANTEE. Your request remains pending until confirmed.'
    ],
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'cleantee_db',
    'username' => 'root',
    'password' => '',
];