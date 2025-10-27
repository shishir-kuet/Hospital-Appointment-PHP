<?php
// File: doctors/schedule.php
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
$doctor_sql = "
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
$doctor = $db->fetchOne($doctor_sql, [$user_id]);

if (!$doctor) {
    // Debug: Log the issue instead of redirecting
    error_log("Doctor schedule: No doctor record found for user_id: $user_id");
    
    // Destroy session to prevent redirect loop
    session_destroy();
    
    // Redirect with error message
    header('Location: ../auth.php?error=no_doctor_record');
    exit();
}

// Get upcoming appointments grouped by date
$upcoming_sql = "
    SELECT 
        DATE(appointment_date) as date,
        COUNT(*) as count,
        GROUP_CONCAT(CONCAT(appointment_time, '|', 
            (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = patient_id)
        ) ORDER BY appointment_time SEPARATOR '||') as appointments
    FROM appointments 
    WHERE doctor_id = ? 
    AND appointment_date >= CURDATE()
    AND appointment_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
    AND status = 'scheduled'
    GROUP BY DATE(appointment_date)
    ORDER BY appointment_date
";
$upcoming_schedule = $db->fetchAll($upcoming_sql, [$doctor['id']]);

// Parse available days
$available_days = explode(',', $doctor['available_days']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></title>
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
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                    </a>
                    <a href="appointments.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-calendar-check mr-1"></i>Appointments
                    </a>
                    <a href="patients.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-users mr-1"></i>Patients
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
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Schedule Management</h1>
                    <p class="text-gray-600">Manage your availability and working hours</p>
                </div>
                <a href="dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-medium transition duration-300 hover:shadow-lg transform hover:scale-105 flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Current Schedule Info -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 mb-8">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-blue-50 to-indigo-50 rounded-t-xl">
                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                    <div class="bg-blue-500 p-2 rounded-full mr-3">
                        <i class="fas fa-cog text-white"></i>
                    </div>
                    Current Schedule Settings
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center p-6 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border border-blue-200">
                        <div class="bg-blue-500 p-4 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                            <i class="fas fa-clock text-white text-2xl"></i>
                        </div>
                        <p class="text-sm text-gray-600 mb-2 font-medium">Working Hours</p>
                        <p class="text-lg font-bold text-gray-800">
                            <?php echo date('g:i A', strtotime($doctor['start_time'])); ?> - 
                            <?php echo date('g:i A', strtotime($doctor['end_time'])); ?>
                        </p>
                    </div>
                    
                    <div class="text-center p-6 bg-gradient-to-br from-green-50 to-green-100 rounded-xl border border-green-200">
                        <div class="bg-green-500 p-4 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                            <i class="fas fa-calendar-week text-white text-2xl"></i>
                        </div>
                        <p class="text-sm text-gray-600 mb-2 font-medium">Available Days</p>
                        <div class="flex flex-wrap justify-center gap-1">
                            <?php 
                            $work_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                            foreach ($work_days as $day): ?>
                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded font-medium">
                                    <?php echo $day; ?>
                                </span>
                            <?php endforeach; ?>
                            <span class="px-2 py-1 bg-gray-100 text-gray-500 text-xs rounded">
                                Sun
                            </span>
                        </div>
                    </div>
                    
                    <div class="text-center p-6 bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl border border-purple-200">
                        <div class="bg-purple-500 p-4 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                            <i class="fas fa-money-bill text-white text-2xl"></i>
                        </div>
                        <p class="text-sm text-gray-600 mb-2 font-medium">Consultation Fee</p>
                        <p class="text-lg font-bold text-gray-800">BDT <?php echo number_format($doctor['consultation_fee'], 0); ?></p>
                    </div>
                </div>
                
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600 mb-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        To update your schedule settings, please contact the administrator
                    </p>
                </div>
            </div>
        </div>

        <!-- Upcoming Schedule (Next 14 Days) -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-green-50 to-emerald-50 rounded-t-xl">
                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                    <div class="bg-green-500 p-2 rounded-full mr-3">
                        <i class="fas fa-calendar-alt text-white"></i>
                    </div>
                    Upcoming Appointments (Next 14 Days)
                </h3>
            </div>
            <div class="p-6">
                <?php if (empty($upcoming_schedule)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-alt text-gray-400 text-5xl mb-4"></i>
                        <p class="text-gray-500 text-lg">No appointments scheduled for the next 14 days</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($upcoming_schedule as $day): ?>
                            <?php
                            $date = $day['date'];
                            $day_name = date('l', strtotime($date));
                            $appointments_data = explode('||', $day['appointments']);
                            ?>
                            <div class="border rounded-lg overflow-hidden">
                                <div class="bg-blue-50 px-6 py-4 border-b">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="text-lg font-semibold text-gray-800">
                                                <?php echo date('F j, Y', strtotime($date)); ?>
                                            </h4>
                                            <p class="text-sm text-gray-600"><?php echo $day_name; ?></p>
                                        </div>
                                        <div class="text-right">
                                            <span class="px-4 py-2 bg-blue-100 text-blue-800 rounded-full font-medium">
                                                <?php echo $day['count']; ?> Appointments
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="p-6">
                                    <div class="space-y-3">
                                        <?php foreach ($appointments_data as $apt_data): ?>
                                            <?php 
                                            list($time, $patient_name) = explode('|', $apt_data);
                                            ?>
                                            <div class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                                <div class="flex-shrink-0 w-20">
                                                    <span class="text-sm font-medium text-blue-600">
                                                        <?php echo date('g:i A', strtotime($time)); ?>
                                                    </span>
                                                </div>
                                                <div class="flex-1 ml-4">
                                                    <span class="text-gray-800 font-medium">
                                                        <?php echo htmlspecialchars($patient_name); ?>
                                                    </span>
                                                </div>
                                                <div class="flex-shrink-0">
                                                    <span class="role-badge status-scheduled">Scheduled</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-6 text-center">
                        <a href="appointments.php" class="bg-blue-500 hover:bg-blue-600 text-white px-8 py-3 rounded-lg font-medium transition duration-300 hover:shadow-lg transform hover:scale-105 flex items-center justify-center inline-flex">
                            <i class="fas fa-calendar-alt mr-2"></i>View All Appointments
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Weekly Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
            <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                <div class="px-6 py-4 border-b bg-gradient-to-r from-purple-50 to-indigo-50 rounded-t-xl">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <div class="bg-purple-500 p-2 rounded-full mr-3">
                            <i class="fas fa-calendar-week text-white"></i>
                        </div>
                        Weekly Schedule
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-3">
                        <?php 
                        $all_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                        $available_work_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']; // Monday to Saturday available
                        foreach ($all_days as $day): 
                            $is_available = in_array($day, $available_work_days);
                        ?>
                            <div class="flex items-center p-3 rounded-lg <?php echo $is_available ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200'; ?>">
                                <i class="fas fa-<?php echo $is_available ? 'check-circle text-green-600' : 'times-circle text-gray-400'; ?> mr-3"></i>
                                <span class="<?php echo $is_available ? 'text-gray-800 font-medium' : 'text-gray-400'; ?>">
                                    <?php echo ucfirst($day); ?>
                                </span>
                                <?php if ($is_available): ?>
                                    <span class="ml-auto text-xs text-green-600 font-medium">Available</span>
                                <?php else: ?>
                                    <span class="ml-auto text-xs text-gray-400">Off</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                <div class="px-6 py-4 border-b bg-gradient-to-r from-orange-50 to-yellow-50 rounded-t-xl">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <div class="bg-orange-500 p-2 rounded-full mr-3">
                            <i class="fas fa-chart-bar text-white"></i>
                        </div>
                        Quick Actions
                    </h3>
                </div>
                <div class="p-6">
                    <?php
                    $today_count = 0;
                    $tomorrow_count = 0;
                    foreach ($upcoming_schedule as $day) {
                        if ($day['date'] === date('Y-m-d')) $today_count = $day['count'];
                        if ($day['date'] === date('Y-m-d', strtotime('+1 day'))) $tomorrow_count = $day['count'];
                    }
                    ?>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg border border-blue-200">
                            <div class="flex items-center">
                                <div class="bg-blue-500 p-2 rounded-full mr-3">
                                    <i class="fas fa-calendar-day text-white"></i>
                                </div>
                                <span class="text-gray-800 font-medium">Today's Appointments</span>
                            </div>
                            <span class="text-2xl font-bold text-blue-600"><?php echo $today_count; ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-lg border border-green-200">
                            <div class="flex items-center">
                                <div class="bg-green-500 p-2 rounded-full mr-3">
                                    <i class="fas fa-calendar-plus text-white"></i>
                                </div>
                                <span class="text-gray-800 font-medium">Tomorrow's Appointments</span>
                            </div>
                            <span class="text-2xl font-bold text-green-600"><?php echo $tomorrow_count; ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center p-4 bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg border border-purple-200">
                            <div class="flex items-center">
                                <div class="bg-purple-500 p-2 rounded-full mr-3">
                                    <i class="fas fa-calendar-week text-white"></i>
                                </div>
                                <span class="text-gray-800 font-medium">Next 14 Days</span>
                            </div>
                            <span class="text-2xl font-bold text-purple-600">
                                <?php echo array_sum(array_column($upcoming_schedule, 'count')); ?>
                            </span>
                        </div>
                        
                        <div class="pt-4 border-t border-gray-200">
                            <div class="grid grid-cols-2 gap-3">
                                <a href="appointments.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-200 text-center flex items-center justify-center">
                                    <i class="fas fa-calendar mr-2"></i>Set Leave Day 
                                </a>
                                <a href="dashboard.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-200 text-center flex items-center justify-center">
                                    <i class="fas fa-copy mr-2"></i>Copy Schedule 
                                </a>
                                <a href="appointments.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-200 text-center flex items-center justify-center col-span-2">
                                    <i class="fas fa-eye mr-2"></i>View Appointments
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        </div> <!-- End relative z-10 -->
    </div>

    <script>
        // Auto-refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>