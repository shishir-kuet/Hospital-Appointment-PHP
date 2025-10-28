<?php
// Check database structure and create missing tables
require_once 'config/database.php';

$db = Database::getInstance();

echo "<h2>Checking database structure...</h2>";

// Check if doctor_schedules table exists
try {
    $result = $db->fetchAll("SHOW TABLES LIKE 'doctor_schedules'");
    if (empty($result)) {
        echo "<p>Creating doctor_schedules table...</p>";
        
        $create_schedules_sql = "
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
        
        $db->executeQuery($create_schedules_sql);
        echo "<p style='color: green;'>✓ doctor_schedules table created successfully</p>";
    } else {
        echo "<p style='color: green;'>✓ doctor_schedules table exists</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error with doctor_schedules: " . $e->getMessage() . "</p>";
}

// Check if doctor_leaves table exists
try {
    $result = $db->fetchAll("SHOW TABLES LIKE 'doctor_leaves'");
    if (empty($result)) {
        echo "<p>Creating doctor_leaves table...</p>";
        
        $create_leaves_sql = "
        CREATE TABLE doctor_leaves (
            id INT PRIMARY KEY AUTO_INCREMENT,
            doctor_id INT NOT NULL,
            leave_date DATE NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
            UNIQUE KEY unique_doctor_leave (doctor_id, leave_date)
        )";
        
        $db->executeQuery($create_leaves_sql);
        echo "<p style='color: green;'>✓ doctor_leaves table created successfully</p>";
    } else {
        echo "<p style='color: green;'>✓ doctor_leaves table exists</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error with doctor_leaves: " . $e->getMessage() . "</p>";
}

// Check if doctor_reviews table exists (optional)
try {
    $result = $db->fetchAll("SHOW TABLES LIKE 'doctor_reviews'");
    if (empty($result)) {
        echo "<p>Creating doctor_reviews table...</p>";
        
        $create_reviews_sql = "
        CREATE TABLE doctor_reviews (
            id INT PRIMARY KEY AUTO_INCREMENT,
            doctor_id INT NOT NULL,
            patient_id INT NOT NULL,
            appointment_id INT DEFAULT NULL,
            rating INT CHECK (rating >= 1 AND rating <= 5),
            review_text TEXT DEFAULT NULL,
            patient_name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
        )";
        
        $db->executeQuery($create_reviews_sql);
        echo "<p style='color: green;'>✓ doctor_reviews table created successfully</p>";
    } else {
        echo "<p style='color: green;'>✓ doctor_reviews table exists</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error with doctor_reviews: " . $e->getMessage() . "</p>";
}

// Show all tables
echo "<h3>Current database tables:</h3><ul>";
try {
    $tables = $db->fetchAll("SHOW TABLES");
    foreach ($tables as $table) {
        $table_name = array_values($table)[0];
        echo "<li>$table_name</li>";
    }
} catch (Exception $e) {
    echo "<li style='color: red;'>Error showing tables: " . $e->getMessage() . "</li>";
}
echo "</ul>";

echo "<h3 style='color: green;'>Database setup completed!</h3>";
echo "<p><a href='doctors/dashboard.php'>Go to Doctor Dashboard</a></p>";
?>