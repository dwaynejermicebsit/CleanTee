-- Compatible schema without CHECK constraints for older phpMyAdmin/MariaDB parsers.
-- CLEANTEE Phase 1 schema. Non-destructive: no DROP or TRUNCATE statements.
CREATE DATABASE IF NOT EXISTS cleantee_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cleantee_db;

CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(150) NOT NULL,
 email VARCHAR(254) NOT NULL,
 phone VARCHAR(30) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
 status ENUM('Active','Suspended') NOT NULL DEFAULT 'Active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_users_email (email),
 KEY idx_users_role_status (role,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(40) NOT NULL,
 name VARCHAR(150) NOT NULL,
 description TEXT,
 icon VARCHAR(20),
 note VARCHAR(255),
 price DECIMAL(12,2) NOT NULL,
 billing_unit ENUM('hour','request','trap','starting') NOT NULL,
 min_hours DECIMAL(5,2) DEFAULT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_services_code (code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference VARCHAR(32) NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 service_id BIGINT UNSIGNED NOT NULL,
 customer_name VARCHAR(150) NOT NULL,
 phone VARCHAR(30) NOT NULL,
 email VARCHAR(254),
 scheduled_date DATE NOT NULL,
 scheduled_time TIME NOT NULL,
 city VARCHAR(120) NOT NULL,
 address VARCHAR(500) NOT NULL,
 property_type VARCHAR(80),
 unit_number VARCHAR(80),
 sqm DECIMAL(10,2),
 floors SMALLINT UNSIGNED,
 grease_trap_quantity SMALLINT UNSIGNED,
 cleaners SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 hours DECIMAL(5,2),
 aircon_type VARCHAR(40),
 aircon_quantity SMALLINT UNSIGNED,
 parking_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
 notes TEXT,
 service_price_snapshot DECIMAL(12,2) NOT NULL,
 service_subtotal DECIMAL(12,2) NOT NULL,
 transport_estimate DECIMAL(12,2) NOT NULL DEFAULT 0,
 estimated_total DECIMAL(12,2) NOT NULL,
 status ENUM('New','Confirmed','Done','Cancelled') NOT NULL DEFAULT 'New',
 confirmed_at DATETIME DEFAULT NULL,
 completed_at DATETIME DEFAULT NULL,
 cancelled_at DATETIME DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_booking_reference (reference),
 KEY idx_booking_user_created (user_id,created_at),
 KEY idx_booking_schedule_status (scheduled_date,status),
 KEY idx_booking_service (service_id),
 CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_booking_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference VARCHAR(32) NOT NULL,
 user_id BIGINT UNSIGNED DEFAULT NULL,
 booking_id BIGINT UNSIGNED DEFAULT NULL,
 customer_name VARCHAR(150) NOT NULL,
 contact VARCHAR(254) NOT NULL,
 booking_reference_entered VARCHAR(32),
 service_date DATE NOT NULL,
 issue_type ENUM('missed','damage','missing','staff','other') NOT NULL,
 severity ENUM('Low','Medium','High') NOT NULL DEFAULT 'Low',
 details TEXT NOT NULL,
 status ENUM('New','Investigating','Resolved') NOT NULL DEFAULT 'New',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_report_reference (reference),
 KEY idx_report_user (user_id),
 KEY idx_report_booking (booking_id),
 KEY idx_report_status_created (status,created_at),
 CONSTRAINT fk_report_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_report_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS report_photos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 report_id BIGINT UNSIGNED NOT NULL,
 stored_filename VARCHAR(255) NOT NULL,
 original_filename VARCHAR(255) NOT NULL,
 mime_type VARCHAR(80) NOT NULL,
 file_size_bytes BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_photo_report (report_id),
 CONSTRAINT fk_photo_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(150) NOT NULL,
 position VARCHAR(100) NOT NULL,
 phone VARCHAR(30),
 email VARCHAR(254),
 photo_path VARCHAR(255),
 status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_staff_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS booking_staff (
 booking_id BIGINT UNSIGNED NOT NULL,
 staff_id BIGINT UNSIGNED NOT NULL,
 assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (booking_id,staff_id),
 KEY idx_booking_staff_staff (staff_id),
 CONSTRAINT fk_assignment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
 CONSTRAINT fk_assignment_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Safe seed: insert missing service codes; leave existing records and prices unchanged.
INSERT IGNORE INTO services (code,name,description,icon,note,price,billing_unit,min_hours) VALUES
('residential','Residential Cleaning','Everyday home cleaning, room by room.','🏠',NULL,250.00,'hour',3),
('commercial','Commercial Cleaning','Offices, shops and workspaces kept pristine.','🏢',NULL,250.00,'hour',3),
('deep','Deep Cleaning','Top-to-bottom detailed clean.','✨','2 cleaners per 20–30 sqm',450.00,'hour',4),
('post','Post Construction Cleaning','Dust, debris and residue removed after renovation.','🏗️',NULL,250.00,'hour',4),
('fogging','Fogging / Disinfection','Sanitize spaces against germs and pests.','🛡️','Minimum ₱300 per request',300.00,'request',NULL),
('move','Move In / Move Out Cleaning','Leave it spotless or start fresh.','📦',NULL,250.00,'hour',3),
('grease','Grease Trap Cleaning','Keep your kitchen drains flowing and compliant.','🍳',NULL,499.00,'trap',NULL),
('aircon','Aircon Cleaning','Cooler air, lower bills, cleaner breathing.','❄️',NULL,750.00,'starting',NULL);
