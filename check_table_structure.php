<?php
// Check the actual structure of doctor_schedules table
require_once 'config/database.php';

$db = Database::getInstance();

echo "<h2>Checking doctor_schedules table structure</h2>";

try {
    // Show the actual structure of the table
    $structure = $db->fetchAll("DESCRIBE doctor_schedules");
    echo "<h3>Current table structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($structure as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if day_of_week column exists
    $columns = array_column($structure, 'Field');
    if (!in_array('day_of_week', $columns)) {
        echo "<p style='color: red;'>❌ day_of_week column is missing!</p>";
        echo "<p>Attempting to add missing column...</p>";
        
        // Add the missing column
        $add_column_sql = "ALTER TABLE doctor_schedules ADD COLUMN day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL AFTER doctor_id";
        $db->executeQuery($add_column_sql);
        echo "<p style='color: green;'>✓ day_of_week column added successfully!</p>";
        
        // Also add unique constraint
        try {
            $add_unique_sql = "ALTER TABLE doctor_schedules ADD UNIQUE KEY unique_doctor_day (doctor_id, day_of_week)";
            $db->executeQuery($add_unique_sql);
            echo "<p style='color: green;'>✓ Unique constraint added!</p>";
        } catch (Exception $e) {
            echo "<p style='color: orange;'>Note: Unique constraint may already exist or failed: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p style='color: green;'>✓ day_of_week column exists!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    
    // If table doesn't exist, create it properly
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo "<p>Table doesn't exist. Creating it now...</p>";
        
        $create_table_sql = "
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
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
            UNIQUE KEY unique_doctor_day (doctor_id, day_of_week)
        )";
        
        try {
            $db->executeQuery($create_table_sql);
            echo "<p style='color: green;'>✓ doctor_schedules table created successfully!</p>";
        } catch (Exception $create_error) {
            echo "<p style='color: red;'>Failed to create table: " . $create_error->getMessage() . "</p>";
        }
    }
}

// Test the query that was failing
try {
    echo "<h3>Testing the problematic query:</h3>";
    $test_query = "SELECT * FROM doctor_schedules WHERE doctor_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
    $result = $db->fetchAll($test_query, [1]); // Test with doctor_id = 1
    echo "<p style='color: green;'>✓ Query executed successfully! Found " . count($result) . " records.</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Query failed: " . $e->getMessage() . "</p>";
}

echo "<p><a href='doctors/schedule.php'>Try Schedule Page Again</a></p>";
?>