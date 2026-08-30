--TEST--
DateTime Built-in Class - Creating objects, calling methods, reading/writing properties
--FILE--
<?php

// Test DateTime class basic functionality
$date = new DateTime('2023-06-15 10:30:45');
echo $date->format('Y-m-d H:i:s') . "\n";

// Test modifying date
$date->modify('+1 day');
echo $date->format('Y-m-d H:i:s') . "\n";

// Test setting date and time
$date->setDate(2024, 1, 1);
$date->setTime(12, 0, 0);
echo $date->format('Y-m-d H:i:s') . "\n";

// Test timestamp
$timestamp = $date->getTimestamp();
echo $timestamp . "\n";

// Create from timestamp
$date2 = new DateTime();
$date2->setTimestamp($timestamp);
echo $date2->format('Y-m-d H:i:s') . "\n";

// Test timezone
$date->setTimezone(new DateTimeZone('UTC'));
echo $date->format('Y-m-d H:i:s T') . "\n";

// Test diff method
$date3 = new DateTime('2023-01-01');
$date4 = new DateTime('2023-12-31');
$interval = $date3->diff($date4);
echo $interval->days . " days\n";

// Test parsing a datetime string with an explicit format.
$parsed = DateTime::createFromFormat('Y-m-d H:i:s', '2024-02-03 04:05:06');
echo $parsed->format('Y-m-d H:i:s') . "\n";

// Test add/subtract intervals
$date->add(new DateInterval('P1D')); // Add 1 day
echo $date->format('Y-m-d H:i:s') . "\n";
$date->sub(new DateInterval('P1D')); // Subtract 1 day
echo $date->format('Y-m-d H:i:s') . "\n";

// Test formatting options
echo $date->format('c') . "\n"; // ISO 8601 format
echo $date->format('r') . "\n"; // RFC 2822 format
?>
--EXPECTF--
2023-06-15 10:30:45
2023-06-16 10:30:45
2024-01-01 12:00:00
1704110400
2024-01-01 12:00:00
2024-01-01 12:00:00 UTC
364 days
2024-02-03 04:05:06
2024-01-02 12:00:00
2024-01-01 12:00:00
2024-01-01T12:00:00+00:00
Mon, 01 Jan 2024 12:00:00 +0000
