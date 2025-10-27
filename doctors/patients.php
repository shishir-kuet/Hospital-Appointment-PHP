<?php
// File: doctors/patients.php
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
    error_log("Doctor patients: No doctor record found for user_id: $user_id");
    
    // Destroy session to prevent redirect loop
    session_destroy();
    
    // Redirect with error message
    header('Location: ../auth.php?error=no_doctor_record');
    exit();
}

// Handle filters
$search_patient = $_GET['search'] ?? '';
$blood_group_filter = $_GET['blood_group'] ?? '';
$age_range = $_GET['age_range'] ?? '';

// Build WHERE clause
$where_conditions = ["a.doctor_id = ?"];
$params = [$doctor['id']];

if ($search_patient) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search_patient%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($blood_group_filter) {
    $where_conditions[] = "p.blood_group = ?";
    $params[] = $blood_group_filter;
}

if ($age_range) {
    switch ($age_range) {
        case '0-18':
            $where_conditions[] = "p.age BETWEEN 0 AND 18";
            break;
        case '19-35':
            $where_conditions[] = "p.age BETWEEN 19 AND 35";
            break;
        case '36-60':
            $where_conditions[] = "p.age BETWEEN 36 AND 60";
            break;
        case '60+':
            $where_conditions[] = "p.age > 60";
            break;
    }
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Count total patients
$count_sql = "
    SELECT COUNT(DISTINCT u.id) as total
    FROM appointments a
    INNER JOIN users u ON a.patient_id = u.id
    LEFT JOIN patients p ON a.patient_id = p.user_id
    WHERE " . implode(' AND ', $where_conditions);
$total_result = $db->fetchOne($count_sql, $params);
$total_patients = $total_result['total'];
$total_pages = ceil($total_patients / $limit);

// Get patients with their appointment history
$patients_sql = "
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.date_of_birth,
        p.age,
        p.blood_group,
        p.address,
        p.emergency_contact,
        COUNT(a.id) as total_appointments,
        MAX(a.appointment_date) as last_appointment,
        MIN(a.appointment_date) as first_appointment,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
        SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
        SUM(CASE WHEN a.status = 'no-show' THEN 1 ELSE 0 END) as no_show_appointments
    FROM appointments a
    INNER JOIN users u ON a.patient_id = u.id
    LEFT JOIN patients p ON a.patient_id = p.user_id
    WHERE " . implode(' AND ', $where_conditions) . "
    GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone, u.date_of_birth, 
             p.age, p.blood_group, p.address, p.emergency_contact
    ORDER BY MAX(a.appointment_date) DESC
    LIMIT $limit OFFSET $offset
";
$patients = $db->fetchAll($patients_sql, $params);

// Get blood groups for filter
$blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// Get patient statistics
$stats_sql = "
    SELECT 
        COUNT(DISTINCT a.patient_id) as total_patients,
        AVG(p.age) as avg_age,
        COUNT(DISTINCT CASE WHEN a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN a.patient_id END) as active_patients
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.user_id
    WHERE a.doctor_id = ?
";
$stats = $db->fetchOne($stats_sql, [$doctor['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients - Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></title>
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
                    <a href="schedule.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-clock mr-1"></i>Schedule
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
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">My Patients</h1>
                    <p class="text-gray-600">View and manage your patient records</p>
                </div>
                <a href="dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-medium transition duration-300 hover:shadow-lg transform hover:scale-105 flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 border border-gray-100">
                <div class="px-6 py-8 text-center">
                    <div class="bg-blue-500 p-4 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                        <i class="fas fa-users text-white text-2xl"></i>
                    </div>
                    <div class="text-3xl font-bold text-gray-800 mb-2"><?php echo intval($stats['total_patients']); ?></div>
                    <div class="text-gray-600 font-medium">Total Patients</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 border border-gray-100">
                <div class="px-6 py-8 text-center">
                    <div class="bg-green-500 p-4 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                        <i class="fas fa-heartbeat text-white text-2xl"></i>
                    </div>
                    <div class="text-3xl font-bold text-gray-800 mb-2"><?php echo intval($stats['active_patients']); ?></div>
                    <div class="text-gray-600 font-medium">Active (30 days)</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 border border-gray-100">
                <div class="px-6 py-8 text-center">
                    <div class="bg-purple-500 p-4 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                        <i class="fas fa-birthday-cake text-white text-2xl"></i>
                    </div>
                    <div class="text-3xl font-bold text-gray-800 mb-2"><?php echo $stats['avg_age'] ? intval($stats['avg_age']) : 'N/A'; ?></div>
                    <div class="text-gray-600 font-medium">Average Age</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 mb-8">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-purple-50 to-indigo-50 rounded-t-xl">
                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                    <div class="bg-purple-500 p-2 rounded-full mr-3">
                        <i class="fas fa-filter text-white"></i>
                    </div>
                    Filter Patients
                </h3>
            </div>
            <div class="p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search Patient</label>
                        <input type="text" name="search" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="Name, phone or email..." value="<?php echo htmlspecialchars($search_patient); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Blood Group</label>
                        <select name="blood_group" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Blood Groups</option>
                            <?php foreach ($blood_groups as $bg): ?>
                                <option value="<?php echo $bg; ?>" <?php echo $blood_group_filter === $bg ? 'selected' : ''; ?>>
                                    <?php echo $bg; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Age Range</label>
                        <select name="age_range" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Ages</option>
                            <option value="0-18" <?php echo $age_range === '0-18' ? 'selected' : ''; ?>>0-18 years</option>
                            <option value="19-35" <?php echo $age_range === '19-35' ? 'selected' : ''; ?>>19-35 years</option>
                            <option value="36-60" <?php echo $age_range === '36-60' ? 'selected' : ''; ?>>36-60 years</option>
                            <option value="60+" <?php echo $age_range === '60+' ? 'selected' : ''; ?>>60+ years</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-medium transition duration-300 hover:shadow-lg transform hover:scale-105 w-full flex items-center justify-center">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Patients List -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-green-50 to-emerald-50 rounded-t-xl">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <div class="bg-green-500 p-2 rounded-full mr-3">
                            <i class="fas fa-users text-white"></i>
                        </div>
                        Patients
                    </h3>
                    <div class="text-sm text-gray-600 font-medium">
                        Showing <?php echo min($limit, $total_patients); ?> of <?php echo $total_patients; ?> patients
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <?php if (empty($patients)): ?>
                    <div class="p-12 text-center">
                        <i class="fas fa-users text-gray-400 text-6xl mb-4"></i>
                        <h3 class="text-xl font-medium text-gray-600 mb-2">No Patients Found</h3>
                        <p class="text-gray-500">No patients match your current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6">
                        <?php foreach ($patients as $patient): ?>
                            <div class="border border-gray-200 rounded-xl p-6 hover:shadow-md transition-all duration-300 hover:border-blue-300 bg-gradient-to-br from-white via-blue-50 to-indigo-50">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center">
                                        <div class="bg-blue-500 p-3 rounded-full mr-4">
                                            <i class="fas fa-user text-white text-lg"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-lg font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                            </h4>
                                            <p class="text-sm text-gray-500 font-medium">
                                                Patient ID: #<?php echo str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <button onclick="viewPatientDetails(<?php echo $patient['id']; ?>)" 
                                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium transition duration-200 flex items-center">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </button>
                                </div>

                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <div class="text-sm text-gray-500 font-medium mb-1">Contact</div>
                                        <div class="text-sm font-medium text-gray-900 flex items-center mb-1">
                                            <i class="fas fa-phone mr-2 text-green-500"></i><?php echo htmlspecialchars($patient['phone']); ?>
                                        </div>
                                        <div class="text-sm text-gray-600 flex items-center">
                                            <i class="fas fa-envelope mr-2 text-blue-500"></i><?php echo htmlspecialchars($patient['email']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500 font-medium mb-1">Details</div>
                                        <?php if ($patient['age']): ?>
                                            <div class="text-sm font-medium text-gray-900 flex items-center mb-1">
                                                <i class="fas fa-birthday-cake mr-2 text-purple-500"></i><?php echo $patient['age']; ?> years old
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($patient['blood_group']): ?>
                                            <div class="text-sm text-gray-600 flex items-center">
                                                <i class="fas fa-tint text-red-500 mr-2"></i><?php echo $patient['blood_group']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-4 pt-4 border-t border-gray-200">
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-blue-600"><?php echo $patient['total_appointments']; ?></div>
                                        <div class="text-xs text-gray-500 font-medium">Total Visits</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-green-600"><?php echo $patient['completed_appointments']; ?></div>
                                        <div class="text-xs text-gray-500 font-medium">Completed</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-gray-600">
                                            <?php echo $patient['last_appointment'] ? date('M j', strtotime($patient['last_appointment'])) : 'Never'; ?>
                                        </div>
                                        <div class="text-xs text-gray-500 font-medium">Last Visit</div>
                                    </div>
                                </div>

                                <?php if ($patient['address']): ?>
                                    <div class="mt-4 pt-4 border-t border-gray-200">
                                        <div class="text-sm text-gray-500 font-medium mb-1">Address</div>
                                        <div class="text-sm text-gray-700 flex items-center">
                                            <i class="fas fa-map-marker-alt mr-2 text-red-500"></i>
                                            <?php echo htmlspecialchars($patient['address']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-4 flex space-x-2">
                                    <button onclick="viewAppointmentHistory(<?php echo $patient['id']; ?>)" 
                                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition duration-200 flex-1 flex items-center justify-center">
                                        <i class="fas fa-history mr-2"></i>History
                                    </button>
                                    <button onclick="scheduleAppointment(<?php echo $patient['id']; ?>)" 
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-200 flex-1 flex items-center justify-center">
                                        <i class="fas fa-plus mr-2"></i>Schedule
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search_patient; ?>&blood_group=<?php echo $blood_group_filter; ?>&age_range=<?php echo $age_range; ?>" 
                           class="px-4 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg transition duration-200">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search_patient; ?>&blood_group=<?php echo $blood_group_filter; ?>&age_range=<?php echo $age_range; ?>" 
                           class="px-4 py-2 <?php echo $i === $page ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 rounded-lg transition duration-200 font-medium">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search_patient; ?>&blood_group=<?php echo $blood_group_filter; ?>&age_range=<?php echo $age_range; ?>" 
                           class="px-4 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg transition duration-200">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

        </div> <!-- End relative z-10 -->
    </div>

    <!-- Patient Details Modal -->
    <div id="patientModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl max-w-2xl w-full max-h-screen overflow-y-auto shadow-2xl">
                <div class="px-6 py-4 border-b bg-gradient-to-r from-blue-50 to-indigo-50 rounded-t-xl">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <div class="bg-blue-500 p-2 rounded-full mr-3">
                            <i class="fas fa-user text-white"></i>
                        </div>
                        Patient Details
                    </h3>
                </div>
                <div id="patientDetails" class="p-6">
                    <!-- Patient details will be loaded here -->
                </div>
                <div class="px-6 py-4 border-t bg-gray-50 rounded-b-xl flex justify-end">
                    <button onclick="closePatientModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium transition duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewPatientDetails(patientId) {
            // You can implement this to show detailed patient information
            document.getElementById('patientDetails').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-blue-500 text-2xl mb-4"></i><p class="text-gray-600">Loading patient details...</p></div>';
            document.getElementById('patientModal').classList.remove('hidden');
            
            // Simulate loading - you would fetch real data here
            setTimeout(() => {
                document.getElementById('patientDetails').innerHTML = `
                    <div class="text-center py-8">
                        <div class="bg-blue-100 p-4 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                            <i class="fas fa-user text-blue-600 text-2xl"></i>
                        </div>
                        <p class="text-gray-600 mb-4">
                            Detailed patient information would be displayed here.
                        </p>
                        <div class="text-left bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">This could include:</p>
                            <ul class="list-disc list-inside text-sm text-gray-600 mt-2 space-y-1">
                                <li>Medical history and conditions</li>
                                <li>Previous treatments and procedures</li>
                                <li>Medications and allergies</li>
                                <li>Doctor's notes and observations</li>
                                <li>Emergency contact information</li>
                            </ul>
                        </div>
                    </div>
                `;
            }, 1000);
        }

        function closePatientModal() {
            document.getElementById('patientModal').classList.add('hidden');
        }

        function viewAppointmentHistory(patientId) {
            // Redirect to appointments page with patient filter
            window.location.href = `appointments.php?patient_id=${patientId}`;
        }

        function scheduleAppointment(patientId) {
            // You can implement this to open a scheduling modal or redirect
            alert('Schedule appointment functionality can be implemented here.\n\nThis would typically open a scheduling form or redirect to the appointment booking page with the patient pre-selected.');
        }

        // Close modal when clicking outside
        document.getElementById('patientModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePatientModal();
            }
        });
    </script>
</body>
</html>