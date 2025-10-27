<?php
// File: admin/all-appointments.php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth.php');
    exit();
}

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'cancel':
                $appointment_id = intval($_POST['appointment_id']);
                if ($appointment_id > 0) {
                    try {
                        $db->beginTransaction();
                        
                        // Get appointment details
                        $appointment = $db->fetchOne("SELECT * FROM appointments WHERE id = ?", [$appointment_id]);
                        if ($appointment) {
                            // 1. Delete associated bills
                            $db->executeQuery("DELETE FROM bills WHERE appointment_id = ?", [$appointment_id]);
                            
                            // 2. Delete associated medical records
                            $db->executeQuery("DELETE FROM medical_records WHERE appointment_id = ?", [$appointment_id]);
                            
                            // 3. Update appointment status to cancelled
                            $db->executeQuery(
                                "UPDATE appointments SET status = 'cancelled', cancellation_reason = ?, cancelled_at = NOW() WHERE id = ?", 
                                ['Cancelled by admin', $appointment_id]
                            );
                            
                            // 4. Log the cancellation (if you have a logs table, otherwise skip)
                            // $db->executeQuery("INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (?, 'cancel_appointment', ?, NOW())", 
                            //     [$user_id, "Cancelled appointment ID: $appointment_id"]);
                        }
                        
                        $db->commit();
                        $message = "Appointment cancelled successfully. Associated bills and records have been removed.";
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        $error = "Error cancelling appointment: " . $e->getMessage();
                    }
                }
                break;
                
            case 'reactivate':
                $appointment_id = intval($_POST['appointment_id']);
                if ($appointment_id > 0) {
                    try {
                        $db->executeQuery(
                            "UPDATE appointments SET status = 'scheduled', cancellation_reason = NULL, cancelled_at = NULL WHERE id = ?", 
                            [$appointment_id]
                        );
                        $message = "Appointment reactivated successfully.";
                    } catch (Exception $e) {
                        $error = "Error reactivating appointment: " . $e->getMessage();
                    }
                }
                break;
                
            case 'update_status':
                $appointment_id = intval($_POST['appointment_id']);
                $new_status = $_POST['status'];
                $allowed_statuses = ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];
                
                if ($appointment_id > 0 && in_array($new_status, $allowed_statuses)) {
                    try {
                        if ($new_status === 'cancelled') {
                            // If changing to cancelled, also delete bills and records
                            $db->beginTransaction();
                            $db->executeQuery("DELETE FROM bills WHERE appointment_id = ?", [$appointment_id]);
                            $db->executeQuery("DELETE FROM medical_records WHERE appointment_id = ?", [$appointment_id]);
                            $db->executeQuery(
                                "UPDATE appointments SET status = ?, cancellation_reason = ?, cancelled_at = NOW() WHERE id = ?", 
                                [$new_status, 'Status changed by admin', $appointment_id]
                            );
                            $db->commit();
                        } else {
                            $db->executeQuery("UPDATE appointments SET status = ? WHERE id = ?", [$new_status, $appointment_id]);
                        }
                        $message = "Appointment status updated successfully.";
                    } catch (Exception $e) {
                        if ($new_status === 'cancelled') $db->rollback();
                        $error = "Error updating appointment status: " . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$doctor_filter = $_GET['doctor'] ?? '';
$search = $_GET['search'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "a.appointment_date = ?";
    $params[] = $date_filter;
}

if (!empty($doctor_filter)) {
    $where_conditions[] = "d.id = ?";
    $params[] = intval($doctor_filter);
}

if (!empty($search)) {
    $where_conditions[] = "(p_user.first_name LIKE ? OR p_user.last_name LIKE ? OR p_user.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all appointments with details
$appointments_sql = "
    SELECT 
        a.*,
        p_user.first_name as patient_first_name,
        p_user.last_name as patient_last_name,
        p_user.email as patient_email,
        p_user.phone as patient_phone,
        d_user.first_name as doctor_first_name,
        d_user.last_name as doctor_last_name,
        doc.specialization,
        dept.name as department_name,
        COALESCE(b.total_amount, 0) as bill_amount,
        b.payment_status
    FROM appointments a
    INNER JOIN users p_user ON a.patient_id = p_user.id
    INNER JOIN doctors doc ON a.doctor_id = doc.id
    INNER JOIN users d_user ON doc.user_id = d_user.id
    INNER JOIN departments dept ON doc.department_id = dept.id
    LEFT JOIN bills b ON a.id = b.appointment_id
    $where_clause
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 100
";

$appointments = $db->fetchAll($appointments_sql, $params);

// Get doctors for filter dropdown
$doctors = $db->fetchAll("
    SELECT d.id, u.first_name, u.last_name, d.specialization 
    FROM doctors d 
    INNER JOIN users u ON d.user_id = u.id 
    ORDER BY u.first_name, u.last_name
");

// Get appointment statistics
$stats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
        SUM(CASE WHEN appointment_date = CURDATE() THEN 1 ELSE 0 END) as today_appointments
    FROM appointments
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Appointments - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'medical-primary': '#0f766e',
                        'medical-secondary': '#059669',
                        'medical-light': '#d1fae5',
                        'medical-accent': '#065f46',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">

    <!-- Header -->
    <header class="bg-white shadow-lg border-b-2 border-medical-light">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <div class="bg-gradient-to-r from-medical-primary to-medical-secondary text-white h-10 w-10 rounded-full flex items-center justify-center">
                        <i class="fas fa-hospital text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">MediCare Admin</h1>
                        <p class="text-sm text-gray-600">Appointment Management</p>
                    </div>
                </div>
                
                <nav class="flex space-x-6">
                    <a href="dashboard.php" class="text-gray-600 hover:text-medical-primary font-medium">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                    </a>
                    <a href="departments.php" class="text-gray-600 hover:text-medical-primary font-medium">
                        <i class="fas fa-building mr-1"></i>Departments
                    </a>
                    <a href="manage-doctors.php" class="text-gray-600 hover:text-medical-primary font-medium">
                        <i class="fas fa-user-md mr-1"></i>Doctors
                    </a>
                    <a href="manage-patients.php" class="text-gray-600 hover:text-medical-primary font-medium">
                        <i class="fas fa-users mr-1"></i>Patients
                    </a>
                    <a href="all-appointments.php" class="text-medical-primary font-medium">
                        <i class="fas fa-calendar-alt mr-1"></i>Appointments
                    </a>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800 font-medium">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900">Appointment Management</h2>
            <p class="text-gray-600 mt-2">Manage all hospital appointments, cancel bookings and handle billing</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="grid md:grid-cols-6 gap-4 mb-8">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg p-4">
                <div class="text-center">
                    <p class="text-blue-100 text-sm">Total</p>
                    <p class="text-2xl font-bold"><?php echo $stats['total_appointments']; ?></p>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white rounded-lg p-4">
                <div class="text-center">
                    <p class="text-yellow-100 text-sm">Scheduled</p>
                    <p class="text-2xl font-bold"><?php echo $stats['scheduled']; ?></p>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg p-4">
                <div class="text-center">
                    <p class="text-green-100 text-sm">Completed</p>
                    <p class="text-2xl font-bold"><?php echo $stats['completed']; ?></p>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg p-4">
                <div class="text-center">
                    <p class="text-red-100 text-sm">Cancelled</p>
                    <p class="text-2xl font-bold"><?php echo $stats['cancelled']; ?></p>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-gray-500 to-gray-600 text-white rounded-lg p-4">
                <div class="text-center">
                    <p class="text-gray-100 text-sm">No Show</p>
                    <p class="text-2xl font-bold"><?php echo $stats['no_show']; ?></p>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg p-4">
                <div class="text-center">
                    <p class="text-purple-100 text-sm">Today</p>
                    <p class="text-2xl font-bold"><?php echo $stats['today_appointments']; ?></p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-filter text-medical-primary mr-2"></i>Filter Appointments
            </h3>
            
            <form method="GET" class="grid md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent">
                        <option value="">All Statuses</option>
                        <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="no_show" <?php echo $status_filter === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Date</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Doctor</label>
                    <select name="doctor" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent">
                        <option value="">All Doctors</option>
                        <?php foreach ($doctors as $doctor): ?>
                        <option value="<?php echo $doctor['id']; ?>" <?php echo $doctor_filter == $doctor['id'] ? 'selected' : ''; ?>>
                            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?> - <?php echo htmlspecialchars($doctor['specialization']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Search Patient</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Patient name or email"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent">
                </div>
                
                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-medical-primary text-white px-6 py-2 rounded-lg hover:bg-medical-accent transition duration-200">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                    <a href="all-appointments.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Appointments List -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-calendar-alt text-medical-primary mr-2"></i>
                    All Appointments (<?php echo count($appointments); ?> results)
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Bill Amount</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($appointments as $appointment): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-user text-blue-500 mr-2"></i>
                                    <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($appointment['patient_email']); ?>
                                </div>
                                <?php if ($appointment['patient_phone']): ?>
                                <div class="text-sm text-gray-500">
                                    <i class="fas fa-phone text-xs mr-1"></i>
                                    <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-user-md text-green-500 mr-2"></i>
                                    Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($appointment['specialization']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($appointment['department_name']); ?>
                                </div>
                            </td>
                            
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-calendar text-blue-500 mr-2"></i>
                                    <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <i class="fas fa-clock text-xs mr-1"></i>
                                    <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                </div>
                                <?php if ($appointment['appointment_number']): ?>
                                <div class="text-xs text-gray-400">
                                    #<?php echo htmlspecialchars($appointment['appointment_number']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-6 py-4 text-center">
                                <?php
                                $status_colors = [
                                    'scheduled' => 'bg-yellow-100 text-yellow-800',
                                    'confirmed' => 'bg-blue-100 text-blue-800',
                                    'in_progress' => 'bg-purple-100 text-purple-800',
                                    'completed' => 'bg-green-100 text-green-800',
                                    'cancelled' => 'bg-red-100 text-red-800',
                                    'no_show' => 'bg-gray-100 text-gray-800'
                                ];
                                $color_class = $status_colors[$appointment['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 py-1 rounded-full text-sm font-medium <?php echo $color_class; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                </span>
                                
                                <?php if ($appointment['cancelled_at']): ?>
                                <div class="text-xs text-gray-500 mt-1">
                                    Cancelled: <?php echo date('M d, Y', strtotime($appointment['cancelled_at'])); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-6 py-4 text-center">
                                <?php if ($appointment['bill_amount'] > 0): ?>
                                    <div class="text-sm font-medium text-gray-900">
                                        ৳<?php echo number_format($appointment['bill_amount'], 2); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo ucfirst($appointment['payment_status'] ?? 'pending'); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400">No bill</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center space-x-2">
                                    <!-- Status Change Dropdown -->
                                    <div class="relative">
                                        <select onchange="updateStatus(<?php echo $appointment['id']; ?>, this.value)" 
                                                class="text-sm border border-gray-300 rounded px-2 py-1 bg-white">
                                            <option value="">Change Status</option>
                                            <option value="scheduled" <?php echo $appointment['status'] === 'scheduled' ? 'disabled' : ''; ?>>Scheduled</option>
                                            <option value="confirmed" <?php echo $appointment['status'] === 'confirmed' ? 'disabled' : ''; ?>>Confirmed</option>
                                            <option value="in_progress" <?php echo $appointment['status'] === 'in_progress' ? 'disabled' : ''; ?>>In Progress</option>
                                            <option value="completed" <?php echo $appointment['status'] === 'completed' ? 'disabled' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $appointment['status'] === 'cancelled' ? 'disabled' : ''; ?>>Cancelled</option>
                                            <option value="no_show" <?php echo $appointment['status'] === 'no_show' ? 'disabled' : ''; ?>>No Show</option>
                                        </select>
                                    </div>
                                    
                                    <?php if ($appointment['status'] !== 'cancelled'): ?>
                                    <!-- Cancel Button -->
                                    <button onclick="cancelAppointment(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>')"
                                            class="text-red-600 hover:text-red-800 px-2 py-1 rounded bg-red-50 hover:bg-red-100 transition duration-200" 
                                            title="Cancel Appointment">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php else: ?>
                                    <!-- Reactivate Button -->
                                    <button onclick="reactivateAppointment(<?php echo $appointment['id']; ?>)"
                                            class="text-green-600 hover:text-green-800 px-2 py-1 rounded bg-green-50 hover:bg-green-100 transition duration-200" 
                                            title="Reactivate Appointment">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($appointments)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-calendar-times text-4xl mb-4"></i>
                                <p class="text-lg">No appointments found</p>
                                <p>Try adjusting your filters or search criteria</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div id="cancelModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-red-600 mb-4">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Cancel Appointment
                </h3>
                
                <p class="text-gray-600 mb-4">Are you sure you want to cancel the appointment for <strong id="cancelPatientName"></strong>?</p>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                    <p class="text-yellow-800 text-sm">
                        <i class="fas fa-warning mr-2"></i>
                        <strong>Warning:</strong> This action will:
                    </p>
                    <ul class="text-yellow-700 text-sm mt-2 ml-4 list-disc">
                        <li>Cancel the appointment</li>
                        <li>Delete associated bills</li>
                        <li>Remove medical records</li>
                        <li>Reset all appointment data</li>
                    </ul>
                </div>
                
                <form id="cancelForm" method="POST">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" id="cancelAppointmentId" name="appointment_id">
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">
                            <i class="fas fa-times mr-2"></i>Cancel Appointment
                        </button>
                        <button type="button" onclick="closeCancelModal()" class="flex-1 bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                            Keep Appointment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden forms for status updates -->
    <form id="statusUpdateForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" id="statusAppointmentId" name="appointment_id">
        <input type="hidden" id="newStatus" name="status">
    </form>

    <form id="reactivateForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="reactivate">
        <input type="hidden" id="reactivateAppointmentId" name="appointment_id">
    </form>

    <script>
        function cancelAppointment(appointmentId, patientName) {
            document.getElementById('cancelAppointmentId').value = appointmentId;
            document.getElementById('cancelPatientName').textContent = patientName;
            document.getElementById('cancelModal').classList.remove('hidden');
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.add('hidden');
        }

        function updateStatus(appointmentId, newStatus) {
            if (newStatus && confirm('Are you sure you want to change the appointment status to "' + newStatus.replace('_', ' ') + '"?')) {
                document.getElementById('statusAppointmentId').value = appointmentId;
                document.getElementById('newStatus').value = newStatus;
                document.getElementById('statusUpdateForm').submit();
            }
        }

        function reactivateAppointment(appointmentId) {
            if (confirm('Are you sure you want to reactivate this cancelled appointment?')) {
                document.getElementById('reactivateAppointmentId').value = appointmentId;
                document.getElementById('reactivateForm').submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const cancelModal = document.getElementById('cancelModal');
            if (event.target === cancelModal) {
                closeCancelModal();
            }
        }
    </script>

</body>
</html>