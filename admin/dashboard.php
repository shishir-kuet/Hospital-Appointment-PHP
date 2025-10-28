<?php
// File: admin/dashboard.php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth.php');
    exit();
}

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

// Handle simple admin search: typing 'patients' or 'doctors' in the dashboard searchbar
$search_results = [];
$search_type = '';
if (isset($_GET['admin_search']) && trim($_GET['admin_search']) !== '') {
    $term = strtolower(trim($_GET['admin_search']));
    if ($term === 'patients' || stripos($term, 'patient') !== false) {
        $search_type = 'patients';
        $search_sql = "
            SELECT u.*, p.age, p.blood_group, COUNT(a.id) as total_appointments
            FROM users u
            INNER JOIN patients p ON u.id = p.user_id
            LEFT JOIN appointments a ON u.id = a.patient_id
            WHERE u.role = 'patient'
            GROUP BY u.id, p.age, p.blood_group
        		ORDER BY u.first_name ASC, u.last_name ASC
        ";
        $search_results = $db->fetchAll($search_sql);
    } elseif ($term === 'doctors' || stripos($term, 'doctor') !== false) {
        $search_type = 'doctors';
        $search_sql = "
            SELECT d.*, u.first_name, u.last_name, dept.name as department_name, COUNT(a.id) as total_appointments
            FROM doctors d
            INNER JOIN users u ON d.user_id = u.id
            LEFT JOIN appointments a ON d.id = a.doctor_id
            LEFT JOIN departments dept ON d.department_id = dept.id
            WHERE u.is_active = 1
            GROUP BY d.id, u.first_name, u.last_name, dept.name
            ORDER BY total_appointments DESC, u.last_name ASC
        ";
        $search_results = $db->fetchAll($search_sql);
    }
}

// Get admin statistics
$stats = $db->getDashboardStats($user_id, 'admin');

// Get recent appointments with patient and doctor details
$recent_appointments_sql = "
    SELECT 
        a.*,
        p_user.first_name as patient_first_name,
        p_user.last_name as patient_last_name,
        d_user.first_name as doctor_first_name,
        d_user.last_name as doctor_last_name,
        doc.specialization,
        dept.name as department_name
    FROM appointments a
    INNER JOIN patients pat ON a.patient_id = pat.user_id
    INNER JOIN users p_user ON pat.user_id = p_user.id
    INNER JOIN doctors doc ON a.doctor_id = doc.id
    INNER JOIN users d_user ON doc.user_id = d_user.id
    INNER JOIN departments dept ON doc.department_id = dept.id
    ORDER BY a.created_at DESC
    LIMIT 10
";
$recent_appointments = $db->fetchAll($recent_appointments_sql);

// Get active doctors
$active_doctors_sql = "
    SELECT 
        d.*,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        dept.name as department_name,
        COUNT(DISTINCT a.id) as total_appointments,
        COUNT(DISTINCT CASE WHEN a.appointment_date = CURDATE() THEN a.id END) as today_appointments
    FROM doctors d
    INNER JOIN users u ON d.user_id = u.id
    INNER JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN appointments a ON d.id = a.doctor_id
    WHERE d.is_available = 1 AND u.is_active = 1
    GROUP BY d.id, u.first_name, u.last_name, u.email, u.phone, dept.name
    ORDER BY total_appointments DESC
    LIMIT 5
";
$active_doctors = $db->fetchAll($active_doctors_sql);

// Get recent patients
$recent_patients_sql = "
    SELECT 
        u.*,
        p.age,
        p.blood_group,
        COUNT(a.id) as total_appointments
    FROM users u
    INNER JOIN patients p ON u.id = p.user_id
    LEFT JOIN appointments a ON u.id = a.patient_id
    WHERE u.role = 'patient' AND u.is_active = 1
    GROUP BY u.id, u.first_name, u.last_name, u.email, p.age, p.blood_group
    ORDER BY u.created_at DESC
    LIMIT 5
";
$recent_patients = $db->fetchAll($recent_patients_sql);

// Get pending bills
$pending_bills_sql = "
    SELECT 
        b.*,
        p_user.first_name as patient_first_name,
        p_user.last_name as patient_last_name,
        d_user.first_name as doctor_first_name,
        d_user.last_name as doctor_last_name
    FROM bills b
    INNER JOIN appointments a ON b.appointment_id = a.id
    INNER JOIN patients pat ON b.patient_id = pat.user_id
    INNER JOIN users p_user ON pat.user_id = p_user.id
    INNER JOIN doctors doc ON a.doctor_id = doc.id
    INNER JOIN users d_user ON doc.user_id = d_user.id
    WHERE b.payment_status = 'pending'
    ORDER BY b.due_date ASC
    LIMIT 5
";
$pending_bills = $db->fetchAll($pending_bills_sql);

// Get department statistics
$dept_stats_sql = "
    SELECT 
        dept.id,
        dept.name,
        dept.description,
        COUNT(DISTINCT d.id) as doctor_count,
        COUNT(DISTINCT a.id) as appointment_count,
        AVG(d.rating) as avg_rating
    FROM departments dept
    LEFT JOIN doctors d ON dept.id = d.department_id AND d.is_available = 1
    LEFT JOIN appointments a ON d.id = a.doctor_id
    WHERE dept.is_active = 1
    GROUP BY dept.id, dept.name, dept.description
    ORDER BY appointment_count DESC
";
$dept_stats = $db->fetchAll($dept_stats_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hospital Management</title>
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
                    <div class="medical-icon bg-purple-600 text-white mr-3">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-800">Admin Portal</h1>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Admin quick search: type 'patients' or 'doctors' -->
                    <form method="get" class="flex items-center" style="max-width:320px;">
                        <input type="text" name="admin_search" placeholder="Search (type 'patients' or 'doctors')" value="<?php echo isset($_GET['admin_search'])?htmlspecialchars($_GET['admin_search']):''; ?>" class="px-3 py-1 border rounded-l-md" />
                        <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded-r-md hover:bg-blue-700">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                    <a href="departments.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-building mr-1"></i>Departments
                    </a>
                    <a href="manage-doctors.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-user-md mr-1"></i>Doctors
                    </a>
                    <a href="manage-patients.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-users mr-1"></i>Patients
                    </a>
                    <a href="all-appointments.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-calendar mr-1"></i>Appointments
                    </a>
                    <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <span class="role-badge role-admin"><?php echo ucfirst($_SESSION['user_role']); ?></span>
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
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Admin Dashboard</h1>
            <p class="text-gray-600">Monitor and manage your hospital operations</p>
        </div>

        <!-- Search Results Panel -->
        <?php if (!empty($search_type)): ?>
            <div class="mb-6 card">
                <div class="px-6 py-4 border-b flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Search Results: <?php echo ucfirst(htmlspecialchars($search_type)); ?></h3>
                    <a href="dashboard.php" class="text-sm text-gray-600">Clear</a>
                </div>
                <div class="p-6">
                    <?php if (empty($search_results)): ?>
                        <p class="text-gray-500">No <?php echo htmlspecialchars($search_type); ?> found.</p>
                    <?php else: ?>
                        <div class="space-y-4 max-h-72 overflow-y-auto custom-scrollbar">
                            <?php if ($search_type === 'patients'): ?>
                                <?php foreach ($search_results as $p): ?>
                                    <div class="p-3 border rounded flex justify-between items-center">
                                        <div>
                                            <p class="font-semibold"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></p>
                                            <p class="text-sm text-gray-600">Age: <?php echo htmlspecialchars($p['age']); ?> • Blood: <?php echo htmlspecialchars($p['blood_group']); ?></p>
                                        </div>
                                        <div class="text-right text-sm text-gray-600">
                                            Appointments: <?php echo $p['total_appointments']; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($search_results as $d): ?>
                                    <div class="p-3 border rounded flex justify-between items-center">
                                        <div>
                                            <p class="font-semibold">Dr. <?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name']); ?></p>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($d['department_name']); ?></p>
                                        </div>
                                        <div class="text-right text-sm text-gray-600">
                                            Appointments: <?php echo $d['total_appointments']; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="card bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                <div class="px-6 py-8">
                    <div class="flex items-center">
                        <div class="medical-icon bg-white bg-opacity-20">
                            <i class="fas fa-users text-white"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-blue-100">Total Patients</p>
                            <p class="text-3xl font-bold"><?php echo number_format($stats['total_patients'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-gradient-to-r from-green-500 to-green-600 text-white">
                <div class="px-6 py-8">
                    <div class="flex items-center">
                        <div class="medical-icon bg-white bg-opacity-20">
                            <i class="fas fa-user-md text-white"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-green-100">Active Doctors</p>
                            <p class="text-3xl font-bold"><?php echo number_format($stats['total_doctors'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-gradient-to-r from-purple-500 to-purple-600 text-white">
                <div class="px-6 py-8">
                    <div class="flex items-center">
                        <div class="medical-icon bg-white bg-opacity-20">
                            <i class="fas fa-calendar-check text-white"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-purple-100">Today's Appointments</p>
                            <p class="text-3xl font-bold"><?php echo number_format($stats['today_appointments'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-gradient-to-r from-orange-500 to-orange-600 text-white">
                <div class="px-6 py-8">
                    <div class="flex items-center">
                        <div class="medical-icon bg-white bg-opacity-20">
                            <i class="fas fa-dollar-sign text-white"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-orange-100">Total Revenue</p>
                            <p class="text-3xl font-bold">৳<?php echo number_format($stats['total_revenue'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="card">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Financial Overview</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Pending Appointments</span>
                            <span class="text-xl font-bold text-blue-600"><?php echo number_format($stats['pending_appointments'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Pending Revenue</span>
                            <span class="text-xl font-bold text-orange-600">৳<?php echo number_format($stats['pending_revenue'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Collection Rate</span>
                            <span class="text-xl font-bold text-green-600">
                                <?php 
                                $total = ($stats['total_revenue'] ?? 0) + ($stats['pending_revenue'] ?? 0);
                                $rate = $total > 0 ? (($stats['total_revenue'] ?? 0) / $total * 100) : 0;
                                echo number_format($rate, 1) . '%';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Quick Actions</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <a href="add-doctor.php" class="btn btn-primary text-center">
                            <i class="fas fa-user-plus mr-2"></i>Add Doctor
                        </a>
                        <a href="add-department.php" class="btn btn-success text-center">
                            <i class="fas fa-building mr-2"></i>Add Department
                        </a>
                        <a href="all-appointments.php" class="btn btn-outline text-center">
                            <i class="fas fa-calendar mr-2"></i>View All Appointments
                        </a>
                        <a href="reports.php" class="btn btn-outline text-center">
                            <i class="fas fa-chart-bar mr-2"></i>View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Recent Appointments -->
            <div class="card">
                <div class="px-6 py-4 border-b flex justify-between items-center">
                    <div class="flex items-center">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Appointments</h3>
                        <button onclick="showSqlQuery()" class="ml-2 text-blue-500 hover:text-blue-700" title="Show SQL Query">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                    <a href="all-appointments.php" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                </div>
                <!-- SQL Query Modal -->
                <div id="sqlModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
                    <div class="bg-white p-6 rounded-lg shadow-xl max-w-4xl w-full mx-4">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-lg font-semibold">SQL Query</h4>
                            <button onclick="hideSqlQuery()" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <pre class="bg-gray-50 p-4 rounded overflow-x-auto text-sm">SELECT 
    a.*,
    p_user.first_name as patient_first_name,
    p_user.last_name as patient_last_name,
    d_user.first_name as doctor_first_name,
    d_user.last_name as doctor_last_name,
    doc.specialization,
    dept.name as department_name
FROM appointments a
INNER JOIN patients pat ON a.patient_id = pat.user_id
INNER JOIN users p_user ON pat.user_id = p_user.id
INNER JOIN doctors doc ON a.doctor_id = doc.id
INNER JOIN users d_user ON doc.user_id = d_user.id
INNER JOIN departments dept ON doc.department_id = dept.id
ORDER BY a.created_at DESC
LIMIT 10</pre>
                    </div>
                </div>
                <div class="p-6">
                    <?php if (empty($recent_appointments)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-times text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">No appointments yet</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 max-h-96 overflow-y-auto custom-scrollbar">
                            <?php foreach ($recent_appointments as $appointment): ?>
                                <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50">
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?> at 
                                            <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </p>
                                        <p class="text-xs text-gray-400"><?php echo htmlspecialchars($appointment['department_name']); ?></p>
                                    </div>
                                    <span class="status-<?php echo $appointment['status']; ?> role-badge ml-4">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Department Statistics -->
            <div class="card">
                <div class="px-6 py-4 border-b flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Department Performance</h3>
                    <a href="departments.php" class="text-sm text-blue-600 hover:text-blue-800">Manage</a>
                </div>
                <div class="p-6">
                    <?php if (empty($dept_stats)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-building text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">No departments configured</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 max-h-96 overflow-y-auto custom-scrollbar">
                            <?php foreach ($dept_stats as $dept): ?>
                                <div class="p-4 border rounded-lg hover:bg-gray-50">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($dept['name']); ?></h4>
                                        <span class="text-yellow-500">
                                            <i class="fas fa-star"></i>
                                            <?php echo number_format($dept['avg_rating'] ?? 0, 1); ?>
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <span class="text-gray-600">Doctors: </span>
                                            <span class="font-semibold text-blue-600"><?php echo $dept['doctor_count']; ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600">Appointments: </span>
                                            <span class="font-semibold text-green-600"><?php echo $dept['appointment_count']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Top Doctors -->
            <div class="card">
                <div class="px-6 py-4 border-b flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Top Performing Doctors</h3>
                    <a href="manage-doctors.php" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                </div>
                <div class="p-6">
                    <?php if (empty($active_doctors)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-user-md text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">No doctors registered</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($active_doctors as $doctor): ?>
                                <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50">
                                    <div>
                                        <p class="font-semibold text-gray-800">
                                            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                        </p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($doctor['department_name']); ?></p>
                                        <div class="flex items-center mt-1 space-x-3 text-xs">
                                            <span class="text-blue-600">
                                                <i class="fas fa-calendar-check mr-1"></i><?php echo $doctor['total_appointments']; ?> appointments
                                            </span>
                                            <span class="text-green-600">
                                                <i class="fas fa-clock mr-1"></i><?php echo $doctor['today_appointments']; ?> today
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-yellow-500 mb-1">
                                            <i class="fas fa-star"></i> <?php echo number_format($doctor['rating'], 1); ?>
                                        </div>
                                        <span class="text-xs text-gray-500"><?php echo $doctor['experience_years']; ?> yrs exp</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Bills -->
            <div class="card">
                <div class="px-6 py-4 border-b flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Pending Bills</h3>
                    <a href="manage-bills.php" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                </div>
                <div class="p-6">
                    <?php if (empty($pending_bills)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-check-circle text-green-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">All bills are paid!</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 max-h-96 overflow-y-auto custom-scrollbar">
                            <?php foreach ($pending_bills as $bill): ?>
                                <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50">
                                    <div>
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($bill['bill_number']); ?></p>
                                        <p class="text-sm text-gray-600">
                                            Patient: <?php echo htmlspecialchars($bill['patient_first_name'] . ' ' . $bill['patient_last_name']); ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            Dr. <?php echo htmlspecialchars($bill['doctor_first_name'] . ' ' . $bill['doctor_last_name']); ?>
                                        </p>
                                        <p class="text-sm text-red-600">
                                            Due: <?php echo date('M j, Y', strtotime($bill['due_date'])); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-gray-800">৳<?php echo number_format($bill['total_amount'], 2); ?></p>
                                        <?php if (strtotime($bill['due_date']) < time()): ?>
                                            <span class="role-badge bg-red-100 text-red-800">Overdue</span>
                                        <?php else: ?>
                                            <span class="role-badge status-pending">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // SQL Query Modal Functions
        function showSqlQuery() {
            const modal = document.getElementById('sqlModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function hideSqlQuery() {
            const modal = document.getElementById('sqlModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Close modal when clicking outside
        document.getElementById('sqlModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideSqlQuery();
            }
        });

        // Add real-time clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            const dateString = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            // You can add a clock element if needed
        }
        
        setInterval(updateClock, 1000);
        updateClock();

        // Smooth scroll for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '0';
                    entry.target.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        entry.target.style.transition = 'all 0.6s ease';
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, 100);
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.card').forEach(card => {
            observer.observe(card);
        });
    </script>
</body>
</html>