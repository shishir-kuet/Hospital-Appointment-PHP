<?php
// Fix doctor_schedules table structure
require_once 'config/database.php';

$db = Database::getInstance();

echo "<h2>Fixing doctor_schedules table</h2>";

try {
    // Drop the existing table if it has issues
    echo "<p>Dropping existing doctor_schedules table...</p>";
    $db->executeQuery("DROP TABLE IF EXISTS doctor_schedules");
    echo "<p style='color: green;'>✓ Existing table dropped</p>";
    
    // Create the table with correct structure
    echo "<p>Creating new doctor_schedules table...</p>";
    $create_sql = "
    CREATE TABLE doctor_schedules (
        id INT PRIMARY KEY AUTO_INCREMENT,
        doctor_id INT NOT NULL,
        day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        is_available TINYINT(1) DEFAULT 1,
        slot_duration_minutes INT DEFAULT 30,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_doctor_id (doctor_id),
        UNIQUE KEY unique_doctor_day (doctor_id, day_of_week)
    )";
    
    $db->executeQuery($create_sql);
    echo "<p style='color: green;'>✓ New doctor_schedules table created successfully!</p>";
    
    // Test the query
    echo "<p>Testing the query...</p>";
    $test_sql = "SELECT * FROM doctor_schedules ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
    $result = $db->fetchAll($test_sql);
    echo "<p style='color: green;'>✓ Query test successful! Table is ready.</p>";
    
    // Show table structure
    echo "<h3>New table structure:</h3>";
    $structure = $db->fetchAll("DESCRIBE doctor_schedules");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($structure as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h3 style='color: green;'>Table fix completed!</h3>";
echo "<p><a href='doctors/schedule.php'>Try Schedule Page Now</a></p>";
?>