<?php
// File: test_connection.php
require_once 'config/database.php';

try {
    $db = Database::getInstance();
    echo "<h2>✅ Database Connected Successfully!</h2>";
    
    // Test query
    $users = $db->fetchAll("SELECT COUNT(*) as count FROM users");
    echo "<p>Total users in database: " . $users[0]['count'] . "</p>";
    
    $departments = $db->fetchAll("SELECT name FROM departments WHERE is_active = 1");
    echo "<h3>Available Departments:</h3><ul>";
    foreach($departments as $dept) {
        echo "<li>" . $dept['name'] . "</li>";
    }
    echo "</ul>";
    
} catch(Exception $e) {
    echo "<h2>❌ Database Connection Failed!</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>