<?php
// File: patient/appointments.php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header('Location: ../auth.php');
    exit();
}

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];
$message = '';

// Handle appointment cancellation
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = intval($_POST['appointment_id']);
    
    // Verify the appointment belongs to the current patient
    $verify_sql = "SELECT id FROM appointments WHERE id = ? AND patient_id = ?";
    $appointment = $db->fetchOne($verify_sql, [$appointment_id, $user_id]);
    
    if ($appointment) {
        $cancel_sql = "UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
        try {
            $db->executeQuery($cancel_sql, [$appointment_id]);
            $message = '<div class="alert alert-success">Appointment cancelled successfully!</div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-error">Failed to cancel appointment. Please try again.</div>';
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$sort_order = $_GET['sort'] ?? 'desc';

// Build the WHERE clause based on filters
$where_conditions = "a.patient_id = ?";
$params = [$user_id];

if ($status_filter !== 'all') {
    $where_conditions .= " AND a.status = ?";
    $params[] = $status_filter;
}

// Get all appointments with pagination
$page = intval($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Count total appointments for pagination
$count_sql = "
    SELECT COUNT(*) as total
    FROM appointments a
    WHERE $where_conditions
";
$total_result = $db->fetchOne($count_sql, $params);
$total_appointments = $total_result['total'];
$total_pages = ceil($total_appointments / $limit);

// Get appointments with doctor information
$appointments_sql = "
    SELECT 
        a.*,
        u.first_name as doctor_first_name,
        u.last_name as doctor_last_name,
        d.specialization,
        d.consultation_fee,
        dept.name as department_name,
        dept.description as department_description
    FROM appointments a
    INNER JOIN doctors doc ON a.doctor_id = doc.id
    INNER JOIN users u ON doc.user_id = u.id
    INNER JOIN doctors d ON doc.id = d.id
    INNER JOIN departments dept ON d.department_id = dept.id
    WHERE $where_conditions
    ORDER BY a.appointment_date " . ($sort_order === 'asc' ? 'ASC' : 'DESC') . ", a.appointment_time
    LIMIT $limit OFFSET $offset
";
$appointments = $db->fetchAll($appointments_sql, $params);

// Get appointment statistics for the current patient
$stats_sql = "
    SELECT 
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as upcoming_appointments,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments
    FROM appointments 
    WHERE patient_id = ?
";
$stats = $db->fetchOne($stats_sql, [$user_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Hospital Management System</title>
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
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">My Appointments</h1>
                    <p class="text-gray-600">Manage and view all your medical appointments</p>
                </div>
                <div class="flex space-x-4">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                    </a>
                    <a href="find-doctors.php" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i>Book New Appointment
                    </a>
                </div>
            </div>
        </div>

        <?php echo $message; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="card bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                <div class="px-6 py-8">
                    <div class="flex items-center">
                        <div class="medical-icon bg-white bg-opacity-20">
                            <i class="fas fa-calendar-check text-white"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-blue-100">Total</p>
                            <p class="text-3xl font-bold"><?php echo $stats['total_appointments'] ?? 0; ?></p>
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
                            <p class="text-3xl font-bold"><?php echo $stats['upcoming_appointments'] ?? 0; ?></p>
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
                            <p class="text-3xl font-bold"><?php echo $stats['completed_appointments'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-gradient-to-r from-red-500 to-red-600 text-white">
                <div class="px-6 py-8">
                    <div class="flex items-center">
                        <div class="medical-icon bg-white bg-opacity-20">
                            <i class="fas fa-times-circle text-white"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-red-100">Cancelled</p>
                            <p class="text-3xl font-bold"><?php echo $stats['cancelled_appointments'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card mb-8">
            <div class="px-6 py-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center space-x-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Status</label>
                            <select id="statusFilter" class="form-select">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Appointments</option>
                                <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort by Date</label>
                            <select id="sortOrder" class="form-select">
                                <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Oldest First</option>
                            </select>
                        </div>
                    </div>
                    <div class="text-gray-600">
                        Showing <?php echo count($appointments); ?> of <?php echo $total_appointments; ?> appointments
                    </div>
                </div>
            </div>
        </div>

        <!-- Appointments List -->
        <div class="space-y-4">
            <?php if (empty($appointments)): ?>
                <div class="card">
                    <div class="px-6 py-12 text-center">
                        <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-medium text-gray-900 mb-2">No Appointments Found</h3>
                        <p class="text-gray-600 mb-6">
                            <?php if ($status_filter !== 'all'): ?>
                                No <?php echo $status_filter; ?> appointments found. Try changing the filter or book a new appointment.
                            <?php else: ?>
                                You haven't booked any appointments yet. Start by finding a doctor and booking your first appointment.
                            <?php endif; ?>
                        </p>
                        <a href="find-doctors.php" class="btn btn-primary">
                            <i class="fas fa-search mr-2"></i>Find Doctors
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($appointments as $appointment): ?>
                    <div class="card hover:shadow-lg transition-shadow">
                        <div class="px-6 py-6">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center mb-4">
                                        <div class="medical-icon bg-primary text-white mr-4">
                                            <i class="fas fa-user-md"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-xl font-semibold text-gray-900">
                                                Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
                                            </h3>
                                            <p class="text-gray-600"><?php echo htmlspecialchars($appointment['specialization']); ?></p>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($appointment['department_name']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                        <div>
                                            <p class="text-sm font-medium text-gray-700">Date & Time</p>
                                            <p class="text-gray-900">
                                                <i class="fas fa-calendar mr-1"></i>
                                                <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                            </p>
                                            <p class="text-gray-900">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700">Appointment ID</p>
                                            <p class="text-gray-900 font-mono">#<?php echo str_pad($appointment['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700">Consultation Fee</p>
                                            <p class="text-gray-900 font-semibold">৳<?php echo number_format($appointment['consultation_fee'], 0); ?></p>
                                        </div>
                                    </div>

                                    <?php if (!empty($appointment['notes'])): ?>
                                        <div class="mb-4">
                                            <p class="text-sm font-medium text-gray-700">Notes</p>
                                            <p class="text-gray-600"><?php echo htmlspecialchars($appointment['notes']); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="flex items-center justify-between">
                                        <div>
                                            <?php
                                            $status = $appointment['status'];
                                            $status_class = '';
                                            $status_icon = '';
                                            switch ($status) {
                                                case 'scheduled':
                                                    $status_class = 'status-scheduled';
                                                    $status_icon = 'fas fa-clock';
                                                    break;
                                                case 'completed':
                                                    $status_class = 'status-completed';
                                                    $status_icon = 'fas fa-check-circle';
                                                    break;
                                                case 'cancelled':
                                                    $status_class = 'status-cancelled';
                                                    $status_icon = 'fas fa-times-circle';
                                                    break;
                                                default:
                                                    $status_class = 'status-pending';
                                                    $status_icon = 'fas fa-hourglass-half';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <i class="<?php echo $status_icon; ?> mr-1"></i>
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="flex space-x-2">
                                            <?php if ($status === 'scheduled'): ?>
                                                <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                    <button type="submit" name="cancel_appointment" class="btn-sm btn-danger">
                                                        <i class="fas fa-times mr-1"></i>Cancel
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($status === 'completed'): ?>
                                                <button class="btn-sm btn-secondary" onclick="alert('Prescription and report features coming soon!')">
                                                    <i class="fas fa-file-medical mr-1"></i>View Report
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_order; ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_order; ?>" 
                           class="px-3 py-2 <?php echo $i === $page ? 'bg-primary text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 rounded">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_order; ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Handle filter changes
        document.getElementById('statusFilter').addEventListener('change', function() {
            updateFilters();
        });

        document.getElementById('sortOrder').addEventListener('change', function() {
            updateFilters();
        });

        function updateFilters() {
            const status = document.getElementById('statusFilter').value;
            const sort = document.getElementById('sortOrder').value;
            const url = new URL(window.location);
            url.searchParams.set('status', status);
            url.searchParams.set('sort', sort);
            url.searchParams.set('page', '1'); // Reset to first page
            window.location = url;
        }

        // Auto-hide alert messages
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>