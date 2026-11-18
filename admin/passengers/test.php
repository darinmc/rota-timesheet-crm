<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...<br>";

require_once __DIR__ . '/../../config/database.php';

if (isset($db) && $db) {
    echo "✅ Database connection successful!<br>";
    
    // Test query
    $stmt = $db->query("SELECT COUNT(*) FROM passengers");
    $count = $stmt->fetchColumn();
    echo "Total passengers in database: " . $count;
} else {
    echo "❌ Database connection failed!";
}

// Check if passengers table exists
$stmt = $db->query("SHOW TABLES LIKE 'passengers'");
if ($stmt->rowCount() > 0) {
    echo "✅ 'passengers' table exists<br>";
    $count = $db->query("SELECT COUNT(*) FROM passengers")->fetchColumn();
    echo "Records: $count";
} else {
    echo "❌ 'passengers' table does NOT exist<br>";
    
    // Check for taxi_passengers
    $stmt = $db->query("SHOW TABLES LIKE 'taxi_passengers'");
    if ($stmt->rowCount() > 0) {
        echo "✅ 'taxi_passengers' table exists<br>";
        $count = $db->query("SELECT COUNT(*) FROM taxi_passengers")->fetchColumn();
        echo "Records: $count";
    } else {
        echo "❌ Neither table found!";
    }
}