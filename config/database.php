<?php
// File: config/database.php
class Database {
    private static $instance = null;
    private $connection;
    
    // XAMPP Default Settings
    private $host = 'localhost';
    private $username = 'root';    
    private $password = '';              
    private $database = 'hospital_appointment_system';
    private $port = 3306;              

    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";
            $this->connection = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    // Smart doctor matching with complex joins
    public function findMatchingDoctors($patientAge, $bloodGroup, $symptoms = '') {
        $sql = "
            SELECT 
                d.id,
                u.first_name,
                u.last_name,
                d.specialization,
                d.consultation_fee,
                d.experience_years,
                d.bio,
                d.rating,
                dept.name as department_name,
                dept.description as department_description,
                d.available_days,
                d.start_time,
                d.end_time,
                COUNT(a.id) as total_appointments
            FROM doctors d
            INNER JOIN users u ON d.user_id = u.id
            INNER JOIN departments dept ON d.department_id = dept.id
            LEFT JOIN appointments a ON d.id = a.doctor_id AND a.status = 'completed'
            WHERE 
                d.is_available = 1 
                AND u.is_active = 1
                AND dept.is_active = 1
                AND (
                    (dept.age_group = 'pediatric' AND ? <= 18) OR
                    (dept.age_group = 'adult' AND ? BETWEEN 19 AND 64) OR
                    (dept.age_group = 'geriatric' AND ? >= 65) OR
                    dept.age_group = 'all_ages'
                )
                AND (d.min_age <= ? AND d.max_age >= ?)
                AND (
                    d.preferred_blood_groups LIKE '%all%' OR
                    d.preferred_blood_groups LIKE ?
                )
            GROUP BY d.id, u.first_name, u.last_name, d.specialization, d.consultation_fee, 
                     d.experience_years, d.bio, d.rating, dept.name, dept.description, 
                     d.available_days, d.start_time, d.end_time
            ORDER BY 
                CASE 
                    WHEN ? <= 18 AND dept.age_group = 'pediatric' THEN 1
                    WHEN ? BETWEEN 19 AND 64 AND dept.age_group = 'adult' THEN 1
                    WHEN ? >= 65 AND dept.age_group = 'geriatric' THEN 1
                    ELSE 2
                END,
                d.rating DESC,
                d.experience_years DESC
        ";
        
        $params = [
            $patientAge, $patientAge, $patientAge,  // Age checks for department
            $patientAge, $patientAge,                // Min/max age checks
            "%{$bloodGroup}%",                       // Blood group check
            $patientAge, $patientAge, $patientAge    // Priority ordering
        ];
        
        return $this->fetchAll($sql, $params);
    }

    // Advanced appointment analytics with multiple joins
    public function getDashboardStats($userId, $role) {
        $stats = [];
        
        if ($role === 'patient') {
            $sql = "
                SELECT 
                    COUNT(*) as total_appointments,
                    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as upcoming_appointments,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments
                FROM appointments 
                WHERE patient_id = ?
            ";
            $stats['appointments'] = $this->fetchOne($sql, [$userId]);
            
            $sql = "
                SELECT 
                    COUNT(*) as total_bills,
                    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_bills,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_bills,
                    SUM(total_amount) as total_amount,
                    SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN payment_status = 'pending' THEN total_amount ELSE 0 END) as pending_amount
                FROM bills 
                WHERE patient_id = ?
            ";
            $stats['bills'] = $this->fetchOne($sql, [$userId]);
            
        } elseif ($role === 'doctor') {
            $sql = "
                SELECT 
                    COUNT(*) as total_appointments,
                    SUM(CASE WHEN appointment_date = CURDATE() THEN 1 ELSE 0 END) as today_appointments,
                    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as upcoming_appointments,
                    COUNT(DISTINCT patient_id) as unique_patients
                FROM appointments a
                INNER JOIN doctors d ON a.doctor_id = d.id
                WHERE d.user_id = ?
            ";
            $stats['appointments'] = $this->fetchOne($sql, [$userId]);
            
        } elseif ($role === 'admin') {
            $sql = "
                SELECT 
                    (SELECT COUNT(*) FROM users WHERE role = 'patient' AND is_active = 1) as total_patients,
                    (SELECT COUNT(*) FROM doctors d INNER JOIN users u ON d.user_id = u.id WHERE d.is_available = 1 AND u.is_active = 1) as total_doctors,
                    (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()) as today_appointments,
                    (SELECT COUNT(*) FROM appointments WHERE status = 'scheduled') as pending_appointments,
                    (SELECT SUM(total_amount) FROM bills WHERE payment_status = 'paid') as total_revenue,
                    (SELECT SUM(total_amount) FROM bills WHERE payment_status = 'pending') as pending_revenue
            ";
            $stats = $this->fetchOne($sql);
        }
        
        return $stats;
    }

    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            throw $e;
        }
    }

    public function fetchAll($sql, $params = []) {
        return $this->executeQuery($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        return $this->executeQuery($sql, $params)->fetch();
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    public function commit() {
        return $this->connection->commit();
    }

    public function rollback() {
        return $this->connection->rollback();
    }
}
?>