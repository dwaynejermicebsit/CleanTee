CLEANTEE update - how to install
================================
1. Copy everything in this folder into C:\xampp\htdocs\CLEANTEE\ and choose "Replace" when asked.
   (config.php and the QR images are NOT included, so your settings are kept.)
2. In phpMyAdmin open cleantee_db > SQL tab and run, in this order (each only once):
      sql/03_booking_workflow.sql   (skip if you already ran it)
      sql/04_improvements.sql
3. In backend/config.php make sure these lines exist (add any that are missing):
      'smtp_host' => 'smtp.gmail.com', 'smtp_port' => 465, 'smtp_secure' => 'ssl',
      'smtp_user' => 'your gmail', 'smtp_pass' => 'your 16-letter app password', 'mail_from' => 'your gmail',
      'admin_notify_email' => 'the email that should receive new-booking alerts',   (optional)
4. Press Ctrl+F5 on the website.

=== UPDATE 3: STAFF LOGIN ===
1. phpMyAdmin > cleantee_db > Import > choose sql/05_staff_login.sql > Go.
2. Admin page > Staff tab. Each staff member needs a valid email and a 09XXXXXXXXX mobile number.
3. Click "Create login" beside the staff member. A temporary password pops up ONCE. Send it to them.
4. Staff log in at auth.html with their email and that password. They land on staff.html (My jobs).
5. Login now allows 8 attempts per 10 minutes per email, then shows "Too many attempts".

=== UPDATE 4: FEWER EMAILS + REQUIRED PAYMENT SCREENSHOT ===
- Customers now get only 3 emails per booking: confirmed, started, done.
- GCash/Maya and bank bookings must include a payment screenshot (JPG/PNG/WebP). Cash bookings do not need one.
- No new SQL. Needs sql/04 (already required). If the site still looks old, press Ctrl+F5.

=== UPDATE 5 ===
- Bookings must be made at least 24 hours ahead (form and server).
- Payment screenshot box restyled. IMPORTANT: replace services.html, book.html, js/booking.js and css/booking.css together, then Ctrl+F5.

=== UPDATE 6: STAFF RATINGS + HISTORY BUTTON FIX ===
1. phpMyAdmin > Import > sql/06_staff_ratings.sql
2. Replace about.html: the kumpartners now show as 3 cards per row with their ratings (no extra script line needed).
3. Replace history.html, then press Ctrl+F5.

=== UPDATE 7 ===
- about.html: kumpartners are cards, 3 per row. Edit the list inside about.html (const kumpartners) to add staff or locations. Names must match the staff names in the admin Staff tab for ratings to appear.
