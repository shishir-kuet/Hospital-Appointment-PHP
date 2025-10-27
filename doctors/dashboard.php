<?php
// File: doctors/dashboard.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header('Location: ../auth.php');
    exit();
}

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

// Get doctor information with department details
$doctor_info_sql = "
    SELECT 
        d.*,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        dept.name as department_name,
        dept.description as department_description
    FROM doctors d
    INNER JOIN users u ON d.user_id = u.id
    INNER JOIN departments dept ON d.department_id = dept.id
    WHERE d.user_id = ?
";
$doctor = $db->fetchOne($doctor_info_sql, [$user_id]);

if (!$doctor) {
    // Debug: Log the issue instead of redirecting
    error_log("Doctor dashboard: No doctor record found for user_id: $user_id");
    
    // Destroy session to prevent redirect loop
    session_destroy();
    
    // Redirect with error message
    header('Location: ../auth.php?error=no_doctor_record');
    exit();
}

// Get doctor statistics
$stats = $db->getDashboardStats($user_id, 'doctor');

// Get today's appointments
$today_appointments_sql = "
    SELECT 
        a.*,
        u.first_name as patient_first_name,
        u.last_name as patient_last_name,
        u.phone as patient_phone,
        p.age as patient_age,
        p.blood_group as patient_blood_group
    FROM appointments a
    INNER JOIN users u ON a.patient_id = u.id
    LEFT JOIN patients p ON a.patient_id = p.user_id
    WHERE a.doctor_id = ? AND a.appointment_date = CURDATE()
    ORDER BY a.appointment_time ASC
";
$today_appointments = $db->fetchAll($today_appointments_sql, [$doctor['id']]);

// Get upcoming appointments (next 7 days)
$upcoming_appointments_sql = "
    SELECT 
        a.*,
        u.first_name as patient_first_name,
        u.last_name as patient_last_name,
        u.phone as patient_phone
    FROM appointments a
    INNER JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ? 
    AND a.appointment_date BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL 7 DAY
    AND a.status = 'scheduled'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
";
$upcoming_appointments = $db->fetchAll($upcoming_appointments_sql, [$doctor['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gradient-to-br from-blue-100 via-slate-100 to-indigo-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b-2 border-medical-light relative z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="medical-icon bg-primary text-white mr-3">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-800">Doctor Portal</h1>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="appointments.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-calendar-alt mr-1"></i>All Appointments
                    </a>
                    <a href="schedule.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-clock mr-1"></i>My Schedule
                    </a>
                    <a href="patients.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-users mr-1"></i>Patients
                    </a>
                    <a href="profile.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-user mr-1"></i>Profile
                    </a>
                    <span class="text-gray-600">Welcome, Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></span>
                    <span class="role-badge role-doctor"><?php echo ucfirst($_SESSION['user_role']); ?></span>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 relative">
        <!-- Background Pattern -->
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-white to-indigo-50 opacity-60 pointer-events-none"></div>
        <div class="relative z-10">
        <!-- Header -->
        <div class="mb-8">
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                <div class="bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg p-6 text-white">
                    <h1 class="text-3xl font-bold mb-2">Welcome Back, Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>!</h1>
                    <p class="text-blue-100 flex items-center">
                        <i class="fas fa-stethoscope mr-2"></i>
                        <?php echo htmlspecialchars($doctor['specialization']); ?> • <?php echo htmlspecialchars($doctor['department_name']); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Date Display -->
        <div class="mb-6">
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-4">
                <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                    <div class="bg-blue-500 p-2 rounded-full mr-3">
                        <i class="fas fa-calendar-day text-white"></i>
                    </div>
                    <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                        <?php echo date('l, F j, Y'); ?>
                    </span>
                </h2>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                <div class="px-6 py-4">
                    <div class="flex items-center">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-3 rounded-full">
                            <i class="fas fa-calendar-check text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600 font-medium">Today's Appointments</p>
                            <p class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent"><?php echo count($today_appointments); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                <div class="px-6 py-4">
                    <div class="flex items-center">
                        <div class="bg-gradient-to-r from-yellow-500 to-orange-500 p-3 rounded-full">
                            <i class="fas fa-clock text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600 font-medium">Upcoming</p>
                            <p class="text-2xl font-bold bg-gradient-to-r from-yellow-600 to-orange-600 bg-clip-text text-transparent"><?php echo count($upcoming_appointments); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                <div class="px-6 py-4">
                    <div class="flex items-center">
                        <div class="bg-gradient-to-r from-green-500 to-emerald-500 p-3 rounded-full">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600 font-medium">Total Patients</p>
                            <p class="text-2xl font-bold bg-gradient-to-r from-green-600 to-emerald-600 bg-clip-text text-transparent"><?php echo $stats['appointments']['unique_patients'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                <div class="px-6 py-4">
                    <div class="flex items-center">
                        <div class="bg-gradient-to-r from-purple-500 to-pink-500 p-3 rounded-full">
                            <i class="fas fa-star text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600 font-medium">Rating</p>
                            <p class="text-2xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent"><?php echo number_format($doctor['rating'], 1); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Today's Appointments -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                <div class="px-6 py-4 border-b bg-gradient-to-r from-blue-50 to-indigo-50 rounded-t-xl">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <div class="bg-blue-500 p-2 rounded-full mr-3">
                            <i class="fas fa-calendar-day text-white"></i>
                        </div>
                        Today's Appointments
                    </h3>
                    <p class="text-sm text-gray-600 ml-12"><?php echo date('M j, Y'); ?></p>
                </div>
                <div class="p-6">
                    <?php if (empty($today_appointments)): ?>
                        <div class="text-center py-8">
                            <div class="bg-gray-100 p-6 rounded-full w-20 h-20 mx-auto mb-4 flex items-center justify-center">
                                <i class="fas fa-calendar-times text-gray-400 text-2xl"></i>
                            </div>
                            <p class="text-gray-500 font-medium">No appointments scheduled for today</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($today_appointments as $appointment): ?>
                                <div class="p-4 border border-gray-200 rounded-lg hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 transition-all duration-300 hover:shadow-md">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-semibold text-gray-800 flex items-center">
                                                <i class="fas fa-user-circle text-blue-500 mr-2"></i>
                                                <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                            </h4>
                                            <p class="text-sm text-gray-600 ml-6 mt-1">
                                                <i class="fas fa-clock mr-1 text-green-500"></i>
                                                <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 ml-6">
                                                <i class="fas fa-phone mr-1 text-purple-500"></i>
                                                <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                            </p>
                                        </div>
                                        <span class="status-badge status-<?php echo $appointment['status']; ?> px-3 py-1 rounded-full text-xs font-medium">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Appointments -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                <div class="px-6 py-4 border-b bg-gradient-to-r from-green-50 to-emerald-50 rounded-t-xl">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <div class="bg-green-500 p-2 rounded-full mr-3">
                            <i class="fas fa-calendar-plus text-white"></i>
                        </div>
                        Upcoming Appointments
                    </h3>
                </div>
                <div class="p-6">
                    <?php if (empty($upcoming_appointments)): ?>
                        <div class="text-center py-8">
                            <div class="bg-gray-100 p-6 rounded-full w-20 h-20 mx-auto mb-4 flex items-center justify-center">
                                <i class="fas fa-calendar-plus text-gray-400 text-2xl"></i>
                            </div>
                            <p class="text-gray-500 font-medium">No upcoming appointments</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <div class="p-4 border border-gray-200 rounded-lg hover:bg-gradient-to-r hover:from-green-50 hover:to-emerald-50 transition-all duration-300 hover:shadow-md">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-semibold text-gray-800 flex items-center">
                                                <i class="fas fa-user-circle text-green-500 mr-2"></i>
                                                <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                            </h4>
                                            <p class="text-sm text-gray-600 ml-6 mt-1">
                                                <i class="fas fa-calendar mr-1 text-blue-500"></i>
                                                <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                                <i class="fas fa-clock ml-2 mr-1 text-purple-500"></i>
                                                <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 ml-6">
                                                <i class="fas fa-phone mr-1 text-orange-500"></i>
                                                <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                            </p>
                                        </div>
                                        <span class="status-badge status-<?php echo $appointment['status']; ?> px-3 py-1 rounded-full text-xs font-medium">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-6 text-center">
                            <a href="appointments.php" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-500 text-white rounded-lg hover:from-green-600 hover:to-emerald-600 transition-all duration-300 font-medium">
                                <i class="fas fa-calendar-alt mr-2"></i>View All Appointments
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8">
            <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                <div class="px-6 py-4 border-b bg-gradient-to-r from-purple-50 to-pink-50 rounded-t-xl">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <div class="bg-purple-500 p-2 rounded-full mr-3">
                            <i class="fas fa-bolt text-white"></i>
                        </div>
                        Quick Actions
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <a href="appointments.php" class="group flex items-center justify-center px-6 py-4 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-lg hover:from-blue-600 hover:to-indigo-600 transition-all duration-300 transform hover:scale-105 hover:shadow-lg font-medium">
                            <i class="fas fa-calendar-alt mr-2"></i>View All Appointments
                        </a>
                        <a href="schedule.php" class="group flex items-center justify-center px-6 py-4 bg-gradient-to-r from-green-500 to-emerald-500 text-white rounded-lg hover:from-green-600 hover:to-emerald-600 transition-all duration-300 transform hover:scale-105 hover:shadow-lg font-medium">
                            <i class="fas fa-clock mr-2"></i>Manage Schedule
                        </a>
                        <a href="patients.php" class="group flex items-center justify-center px-6 py-4 bg-gradient-to-r from-orange-500 to-red-500 text-white rounded-lg hover:from-orange-600 hover:to-red-600 transition-all duration-300 transform hover:scale-105 hover:shadow-lg font-medium">
                            <i class="fas fa-users mr-2"></i>My Patients
                        </a>
                        <a href="profile.php" class="group flex items-center justify-center px-6 py-4 bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-lg hover:from-purple-600 hover:to-pink-600 transition-all duration-300 transform hover:scale-105 hover:shadow-lg font-medium">
                            <i class="fas fa-user mr-2"></i>Update Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>

        </div> <!-- End relative z-10 -->
    </div>
</body>
</html>