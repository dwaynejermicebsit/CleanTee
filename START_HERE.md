# CLEANTEE website update

## Install in XAMPP

1. Back up your current website folder and export your database in phpMyAdmin.
2. Extract the ZIP and merge its `CLEANTEE` folder into `C:\xampp\htdocs\CLEANTEE`. Replace the matching files, while keeping your existing `images` folder and uploaded report photos. Image files were not among the attachments, so this ZIP uses your existing images.
3. Start Apache and MySQL. Use PHP 8.1 or later with PDO MySQL, mbstring and fileinfo enabled.
4. In phpMyAdmin, import `sql/01_schema.sql`, then `sql/02_upgrade.sql`. Both scripts use `CREATE TABLE IF NOT EXISTS`; they do not drop your tables. The first script inserts only missing service codes.
5. Edit `backend/config.php`: match your actual MySQL host, port, database, username and password. Port 3306 and an empty root password are defaults, not guaranteed settings. Preserve your working credentials if they differ.
6. Open `http://localhost/CLEANTEE/backend/test_connection.php`. It should return `"ok": true`. This diagnostic is restricted to localhost.
7. Open `http://localhost/CLEANTEE/auth.html`, create an account and test login. Do not open the HTML using `file://` or a static Live Server: the PHP backend must run.

The folders matter. For example, login must be at `backend/auth/login.php`; the booking endpoint is `backend/bookings/create.php`. Uploaded filenames such as `create(1).php` have been restored to their intended paths. Do not place the uploaded `download` files as `.htaccess` in the root or auth directory: some contain `Require all denied`. The supplied `.htaccess` files are already placed correctly.

## Why login and registration could fail

The uploaded frontend sends login and registration requests to PHP even though its old config claimed it was in demo mode. Apache/PHP, the correct backend paths and MySQL are required. The old frontend also reported server, CSRF and connection errors as an incorrect password; it now preserves the actual server error.

Navigation previously read the session before the asynchronous server check finished. That could hide Logout, or redirect authenticated users away from booking. It now waits. Logout now ends the PHP session. A new cookie name avoids conflicts with the old path-specific session cookie.

The original browser-only admin shortcut did not establish the PHP admin session required by the dashboard. Login now authenticates both roles through PHP and redirects according to the stored database role.

These are issues found in the uploaded source. The exact cause on your computer still requires the diagnostic result or Apache/MySQL logs. This update cannot start or reconfigure XAMPP on your computer.

## Admin login

Log in with the admin account's email and password. The old hardcoded `admin` username/password shortcut has been removed.

If you do not have a database admin account, register normally first. In phpMyAdmin, run this with your actual email:

```sql
UPDATE users SET role='admin' WHERE email='your-admin-email@example.com';
```

Log out and log in again. No existing password is changed by the SQL imports. Accounts created only in the old browser demo must be registered in MySQL before they can use the PHP backend.

## Booking several services

1. Open a service, complete its details and schedule, then choose **Add current service**.
2. Use **Next service** to choose another service. Complete or change its details and schedule, then add it too.
3. Review the cart. You can remove items. Submit the cart once you are ready.
4. Each item receives a separate booking reference; all items share a checkout reference and payment record. One checkout supports up to 20 items. The server validates every item before saving the complete checkout in a database transaction.

When the cart contains items, **Submit cart** submits those items only. A changed draft is included only after you add it. With an empty cart, **Confirm Booking** submits the current service. The cart stays in the open page while you choose services; reloading the page clears the draft cart.

A booking request does not reserve staff or confirm availability. Admins review it as before. Repeating the same checkout request uses its request key to return the existing result rather than create another completed checkout.

Pricing now includes hourly rate × hours × cleaners, aircon subtype prices, quantities, parking where relevant and the displayed city's minimum transport estimate. It uses server-side values rather than trusting the posted estimate. Final transport and repair costs remain subject to confirmation.

## Down payments

Methods: GCash, bank transfer or a cash arrangement. The initial down-payment policy is **30%**, configurable through `down_payment_percent` in `backend/config.php`.

Replace the GCash and bank text in `payment_instructions` with your verified receiving details. Until then, clients are asked to contact your published CLEANTEE number. No account number or QR code was invented.

Clients may include a transfer reference during checkout. The status is **Awaiting verification** when a reference is supplied, otherwise **Awaiting payment**. The admin's **Down payments** section has a **Verify received** action after the actual transfer or cash has been checked. Clients can see the checkout, method, payment status and whole-checkout deposit in History. Do not add the same checkout deposit repeatedly when several history cards belong to one checkout.

This is a manual payment workflow. It does not automatically charge a card, open a merchant checkout or verify a GCash/bank transfer. Automated collection needs a payment provider account, credentials and server-side payment confirmation integration. If clients pay later, staff can check the transfer directly and verify the existing checkout.

## Forgot password

Client login now links to password recovery. Reset links expire after one hour, are single-use and are stored only as hashes. Changing the password invalidates prior authenticated sessions.

Set `app_url` in `backend/config.php` to the site's real base URL. Configure PHP's email/sendmail transport in your XAMPP/PHP environment using your email service. The implementation uses PHP `mail()`; email delivery will not work on an unconfigured local XAMPP installation. Check the PHP error log if mail does not arrive. There is no SMTP password embedded in this package.

The public request response is deliberately the same whether or not an active client email exists. A failed mail send is logged and its unusable reset token is removed. Admin accounts do not use client password recovery.

## Phone fields and effects

Registration, booking and staff mobile fields accept at most 11 digits and validate the `09XXXXXXXXX` format. Registration and booking also enforce this on the server; optional staff phone values must match when provided.

The update includes animated password-eye icons, pastel gradients, button ripples, card hover effects, modal entrances, focus rings and animated date/time selection feedback. Reduced-motion preferences are respected. Native calendar/time pickers retain the browser's own controls; selection feedback appears around the field and below it.

## Verify on your computer

- Register a new account, log out, log in, refresh, and confirm Logout and History remain visible.
- Try a wrong password and a stopped MySQL service separately; the errors should differ.
- Check phone input with letters, more than 11 digits and a number that does not start with 09.
- Check the eye controls for all password fields.
- Add two services with different schedules and submit once. Confirm two booking references and one checkout in History and the admin dashboard.
- Check each payment method and independently verify a received test payment from the admin view.
- Send a reset email using your configured mail transport, use its link once, and confirm reuse fails and the previous password no longer works.
- Check both desktop and mobile layout and reduced-motion mode.

## Checks completed for this package

JavaScript syntax checks passed for the external scripts and inline page scripts. Automated JavaScript behavior checks passed for authentication state, real logout, error reporting, CSRF renewal, customer history requests, unauthenticated access, invalid PHP responses and waiting for session readiness before rendering navigation.

PHP/MySQL and a browser runtime were unavailable in the editing environment. Live registration, database writes, email delivery, PHP lint and visual behavior still need verification in your XAMPP environment. The local diagnostic and checklist above are provided for that purpose.
