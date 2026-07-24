<?php
require_once __DIR__ . '/../includes/config.php';

$existing = db()->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);

$columnsToAdd = [
    'booking_for'   => "ADD COLUMN booking_for ENUM('self','other') NOT NULL DEFAULT 'self' AFTER notes",
    'guest_name'    => "ADD COLUMN guest_name VARCHAR(255) NULL AFTER booking_for",
    'guest_email'   => "ADD COLUMN guest_email VARCHAR(255) NULL AFTER guest_name",
    'guest_phone'   => "ADD COLUMN guest_phone VARCHAR(50) NULL AFTER guest_email",
];

foreach ($columnsToAdd as $col => $ddl) {
    if (!in_array($col, $existing)) {
        db()->exec("ALTER TABLE bookings $ddl");
        echo "Added column: $col\n";
    } else {
        echo "Column already exists: $col\n";
    }
}

echo "Migration complete.\n";
