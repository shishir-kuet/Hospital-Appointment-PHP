<?php
// File: patient/dashboard.php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header('Location: ../auth.php');
    exit();
}

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

// Ensure session variables are properly set (for existing users)
if (!isset($_SESSION['user_age']) || !isset($_SESSION['user_blood_group'])) {
    try {
        $user_data_sql = "SELECT u.*, p.age, p.blood_group
                          FROM users u 
                          LEFT JOIN patients p ON u.id = p.user_id
                          WHERE u.id = ?";
        
        $user_data = $db->fetchOne($user_data_sql, [$user_id]);
        if ($user_data) {
            // Calculate age from date_of_birth if it exists, otherwise use age field
            $calculated_age = 25; // default
            if (!empty($user_data['date_of_birth'])) {
                $birthDate = new DateTime($user_data['date_of_birth']);
                $today = new DateTime('today');
                $calculated_age = $birthDate->diff($today)->y;
            } elseif (!empty($user_data['age'])) {
                $calculated_age = $user_data['age'];
            }
            
            $_SESSION['user_age'] = $calculated_age;
            $_SESSION['user_blood_group'] = $user_data['blood_group'] ?? 'O+';
        }
    } catch (Exception $e) {
        // Fallback if there are database issues
        $_SESSION['user_age'] = 25;
        $_SESSION['user_blood_group'] = 'O+';
    }
}

$user_age = $_SESSION['user_age'] ?? 25; // Fallback if session not properly set
$user_blood_group = $_SESSION['user_blood_group'] ?? 'O+'; // Fallback if session not properly set

// Get patient statistics
$stats = $db->getDashboardStats($user_id, 'patient');

// Get recent appointments
$recent_appointments_sql = "
    SELECT 
        a.*,
        u.first_name as doctor_first_name,
        u.last_name as doctor_last_name,
        d.specialization,
        dept.name as department_name
    FROM appointments a
    INNER JOIN doctors doc ON a.doctor_id = doc.id
    INNER JOIN users u ON doc.user_id = u.id
    INNER JOIN doctors d ON doc.id = d.id
    INNER JOIN departments dept ON d.department_id = dept.id
    WHERE a.patient_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
";
$recent_appointments = $db->fetchAll($recent_appointments_sql, [$user_id]);

// Get pending bills
$pending_bills_sql = "
    SELECT b.*, a.appointment_date, 
           u.first_name as doctor_first_name, u.last_name as doctor_last_name
    FROM bills b
    INNER JOIN appointments a ON b.appointment_id = a.id
    INNER JOIN doctors d ON a.doctor_id = d.id
    INNER JOIN users u ON d.user_id = u.id
    WHERE b.patient_id = ? AND b.payment_status = 'pending'
    ORDER BY b.due_date ASC
    LIMIT 3
";
$pending_bills = $db->fetchAll($pending_bills_sql, [$user_id]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - Hospital Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="medical-icon bg-primary text-white mr-3">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-800">Patient Portal</h1>
                </div>
                
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <span class="role-badge role-patient"><?php echo ucfirst($_SESSION['user_role']); ?></span>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
            <p class="text-gray-600">Manage your healthcare and appointments</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="card bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                <div class="px-6 py-8">
                    <div class="flex items-center">
                        <div class="medical-icon bg-white bg-opacity-20">
                            <i class="fas fa-calendar-check text-white"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-blue-100">Total Appointments</p>
                            <p class="text-3xl font-bold"><?php echo $stats['appointments']['total_appointments'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-gradient-to-r from-green-500 to-green-600 text-white">
                <div class="px-6 py-8">
                    <div class="flex items-center">
                        <div class="medical-icon bg-white bg-opacity-20">
                            <i class="fas fa-clock text-white"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-green-100">Upcoming</p>
                            <p class="text-3xl font-bold"><?php echo $stats['appointments']['upcoming_appointments'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-gradient-to-r from-purple-500 to-purple-600 text-white">
                <div class="px-6 py-8">
                    <div class="flex items-center">
                        <div class="medical-icon bg-white bg-opacity-20">
                            <i class="fas fa-check-circle text-white"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-purple-100">Completed</p>
                            <p class="text-3xl font-bold"><?php echo $stats['appointments']['completed_appointments'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-gradient-to-r from-orange-500 to-orange-600 text-white">
                <div class="px-6 py-8">
                    <div class="flex items-center">
                        <div class="medical-icon bg-white bg-opacity-20">
                            <i class="fas fa-file-invoice-dollar text-white"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-orange-100">Pending Bills</p>
                            <p class="text-3xl font-bold">৳<?php echo number_format($stats['bills']['pending_amount'] ?? 0, 0); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mb-8">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Quick Actions</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="find-doctors.php" class="btn btn-primary text-center">
                        <i class="fas fa-search mr-2"></i>Find Smart Doctors
                    </a>
                    <a href="appointments.php" class="btn btn-success text-center">
                        <i class="fas fa-calendar-plus mr-2"></i>My Appointments
                    </a>
                    <a href="bills.php" class="btn btn-outline text-center">
                        <i class="fas fa-file-invoice mr-2"></i>View Bills
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Appointments -->
            <div class="card">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Appointments</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($recent_appointments)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-times text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">No appointments yet</p>
                            <a href="find-doctors.php" class="btn btn-primary mt-4">
                                <i class="fas fa-plus mr-2"></i>Book Your First Appointment
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_appointments as $appointment): ?>
                                <div class="flex items-center justify-between p-4 border rounded-lg">
                                    <div>
                                        <p class="font-semibold text-gray-800">
                                            Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
                                        </p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($appointment['specialization']); ?></p>
                                        <p class="text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?> at 
                                            <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </p>
                                    </div>
                                    <span class="status-<?php echo $appointment['status']; ?> role-badge">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Bills -->
            <div class="card">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Pending Bills</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($pending_bills)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-check-circle text-green-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">No pending bills</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($pending_bills as $bill): ?>
                                <div class="flex items-center justify-between p-4 border rounded-lg">
                                    <div>
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($bill['bill_number']); ?></p>
                                        <p class="text-sm text-gray-600">
                                            Dr. <?php echo htmlspecialchars($bill['doctor_first_name'] . ' ' . $bill['doctor_last_name']); ?>
                                        </p>
                                        <p class="text-sm text-red-600">
                                            Due: <?php echo date('M j, Y', strtotime($bill['due_date'])); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-gray-800">৳<?php echo number_format($bill['total_amount'], 2); ?></p>
                                        <a href="pay-bill.php?id=<?php echo $bill['id']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-credit-card mr-1"></i>Pay Now
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Patient Profile Info -->
        <div class="card mt-8">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Your Profile Information</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                        <i class="fas fa-birthday-cake text-blue-600 text-2xl mb-2"></i>
                        <p class="text-sm text-gray-600">Age</p>
                        <p class="text-lg font-bold text-gray-800"><?php echo $user_age; ?> years old</p>
                    </div>
                    <div class="text-center p-4 bg-red-50 rounded-lg">
                        <i class="fas fa-tint text-red-600 text-2xl mb-2"></i>
                        <p class="text-sm text-gray-600">Blood Group</p>
                        <p class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($user_blood_group); ?></p>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-lg">
                        <i class="fas fa-user-md text-green-600 text-2xl mb-2"></i>
                        <p class="text-sm text-gray-600">Recommended Care</p>
                        <p class="text-lg font-bold text-gray-800">
                            <?php 
                            if ($user_age <= 18) echo "Pediatric Care";
                            elseif ($user_age >= 65) echo "Geriatric Care";
                            else echo "Adult Care";
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>