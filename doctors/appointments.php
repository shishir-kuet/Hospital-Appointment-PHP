<?php
// File: doctor/appointments.php
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

// Get doctor information
$doctor_info_sql = "SELECT * FROM doctors WHERE user_id = ?";
$doctor = $db->fetchOne($doctor_info_sql, [$user_id]);

if (!$doctor) {
    header('Location: ../auth.php');
    exit();
}

// Handle filters
$date_filter = $_GET['date'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$search_patient = $_GET['search'] ?? '';

// Build WHERE clause
$where_conditions = ["a.doctor_id = ?"];
$params = [$doctor['id']];

if ($date_filter) {
    $where_conditions[] = "a.appointment_date = ?";
    $params[] = $date_filter;
}

if ($status_filter !== 'all') {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($search_patient) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search_patient%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Count total appointments
$count_sql = "
    SELECT COUNT(*) as total
    FROM appointments a
    INNER JOIN users u ON a.patient_id = u.id
    WHERE " . implode(' AND ', $where_conditions);
$total_result = $db->fetchOne($count_sql, $params);
$total_appointments = $total_result['total'];
$total_pages = ceil($total_appointments / $limit);

// Get appointments
$appointments_sql = "
    SELECT 
        a.*,
        u.first_name as patient_first_name,
        u.last_name as patient_last_name,
        u.email as patient_email,
        u.phone as patient_phone,
        p.age as patient_age,
        p.blood_group as patient_blood_group,
        p.address as patient_address
    FROM appointments a
    INNER JOIN users u ON a.patient_id = u.id
    LEFT JOIN patients p ON a.patient_id = p.user_id
    WHERE " . implode(' AND ', $where_conditions) . "
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT $limit OFFSET $offset
";
$appointments = $db->fetchAll($appointments_sql, $params);

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $appointment_id = intval($_POST['appointment_id']);
        $new_status = $_POST['status'];
        $notes = trim($_POST['notes'] ?? '');
        
        if (in_array($new_status, ['completed', 'cancelled', 'no_show'])) {
            $update_sql = "UPDATE appointments SET status = ?, notes = ?, updated_at = NOW() 
                           WHERE id = ? AND doctor_id = ?";
            try {
                $db->executeQuery($update_sql, [$new_status, $notes, $appointment_id, $doctor['id']]);
                $success_message = "Appointment status updated successfully!";
            } catch (Exception $e) {
                $error_message = "Failed to update appointment status.";
            }
        }
    }
}

// Get appointment statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show
    FROM appointments 
    WHERE doctor_id = ?
";
$stats = $db->fetchOne($stats_sql, [$doctor['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Doctor Dashboard</title>
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
                    <a href="dashboard.php" class="flex items-center">
                        <div class="medical-icon bg-primary text-white mr-3">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <h1 class="text-xl font-bold text-gray-800">Doctor Portal</h1>
                    </a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                    </a>
                    <a href="schedule.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-clock mr-1"></i>My Schedule
                    </a>
                    <a href="patients.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-users mr-1"></i>My Patients
                    </a>
                    <span class="text-gray-600">Welcome, Dr. <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
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
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">My Appointments</h1>
                    <p class="text-gray-600">Manage and view all your patient appointments</p>
                </div>
                <a href="dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-medium transition duration-300 hover:shadow-lg transform hover:scale-105 flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success mb-6"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error mb-6"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 border border-gray-100">
                <div class="px-4 py-6 text-center">
                    <div class="bg-blue-500 p-3 rounded-full w-12 h-12 mx-auto mb-3 flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-white"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></div>
                    <div class="text-gray-600 text-sm font-medium">Total</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 border border-gray-100">
                <div class="px-4 py-6 text-center">
                    <div class="bg-yellow-500 p-3 rounded-full w-12 h-12 mx-auto mb-3 flex items-center justify-center">
                        <i class="fas fa-clock text-white"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo $stats['scheduled']; ?></div>
                    <div class="text-gray-600 text-sm font-medium">Scheduled</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 border border-gray-100">
                <div class="px-4 py-6 text-center">
                    <div class="bg-green-500 p-3 rounded-full w-12 h-12 mx-auto mb-3 flex items-center justify-center">
                        <i class="fas fa-check-circle text-white"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo $stats['completed']; ?></div>
                    <div class="text-gray-600 text-sm font-medium">Completed</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 border border-gray-100">
                <div class="px-4 py-6 text-center">
                    <div class="bg-red-500 p-3 rounded-full w-12 h-12 mx-auto mb-3 flex items-center justify-center">
                        <i class="fas fa-times-circle text-white"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo $stats['cancelled']; ?></div>
                    <div class="text-gray-600 text-sm font-medium">Cancelled</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 border border-gray-100">
                <div class="px-4 py-6 text-center">
                    <div class="bg-gray-500 p-3 rounded-full w-12 h-12 mx-auto mb-3 flex items-center justify-center">
                        <i class="fas fa-user-slash text-white"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo $stats['no_show']; ?></div>
                    <div class="text-gray-600 text-sm font-medium">No Show</div>
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
                    Filter Appointments
                </h3>
            </div>
            <div class="p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                        <input type="date" name="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="no_show" <?php echo $status_filter === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search Patient</label>
                        <input type="text" name="search" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="Name or phone..." value="<?php echo htmlspecialchars($search_patient); ?>">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-medium transition duration-300 hover:shadow-lg transform hover:scale-105 w-full flex items-center justify-center">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Appointments List -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-green-50 to-emerald-50 rounded-t-xl">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <div class="bg-green-500 p-2 rounded-full mr-3">
                            <i class="fas fa-calendar-alt text-white"></i>
                        </div>
                        Appointments
                    </h3>
                    <div class="text-sm text-gray-600 font-medium">
                        Showing <?php echo min($limit, $total_appointments); ?> of <?php echo $total_appointments; ?> appointments
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <?php if (empty($appointments)): ?>
                    <div class="p-12 text-center">
                        <i class="fas fa-calendar-times text-gray-400 text-6xl mb-4"></i>
                        <h3 class="text-xl font-medium text-gray-600 mb-2">No Appointments Found</h3>
                        <p class="text-gray-500">No appointments match your current filters.</p>
                    </div>
                <?php else: ?>
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($appointments as $appointment): ?>
                                <tr class="hover:bg-gray-50">
                                    <td>
                                        <div class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            ID: #<?php echo str_pad($appointment['patient_id'], 4, '0', STR_PAD_LEFT); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-sm text-gray-900">
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-clock mr-1"></i>
                                            <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-sm text-gray-900">
                                            <i class="fas fa-phone mr-1"></i>
                                            <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-envelope mr-1"></i>
                                            <?php echo htmlspecialchars($appointment['patient_email']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($appointment['patient_age']): ?>
                                            <div class="text-sm"><i class="fas fa-birthday-cake mr-1"></i><?php echo $appointment['patient_age']; ?> years</div>
                                        <?php endif; ?>
                                        <?php if ($appointment['patient_blood_group']): ?>
                                            <div class="text-sm"><i class="fas fa-tint text-red-500 mr-1"></i><?php echo $appointment['patient_blood_group']; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="role-badge status-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <?php if ($appointment['status'] === 'scheduled'): ?>
                                                <button onclick="updateStatus(<?php echo $appointment['id']; ?>, 'completed')" 
                                                        class="btn btn-sm bg-green-100 text-green-600 hover:bg-green-200" title="Mark Completed">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button onclick="updateStatus(<?php echo $appointment['id']; ?>, 'cancelled')" 
                                                        class="btn btn-sm bg-red-100 text-red-600 hover:bg-red-200" title="Cancel">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="viewDetails(<?php echo htmlspecialchars(json_encode($appointment)); ?>)" 
                                                    class="btn btn-sm bg-blue-100 text-blue-600 hover:bg-blue-200" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&date=<?php echo $date_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo $search_patient; ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&date=<?php echo $date_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo $search_patient; ?>" 
                           class="px-3 py-2 <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 rounded">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&date=<?php echo $date_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo $search_patient; ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
        
        </div> <!-- End relative z-10 -->
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold">Update Appointment Status</h3>
                </div>
                <form method="POST">
                    <div class="p-6">
                        <input type="hidden" id="appointmentId" name="appointment_id">
                        <input type="hidden" id="appointmentStatus" name="status">
                        
                        <div class="mb-4">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="notes" rows="3" class="form-input w-full" 
                                      placeholder="Add any notes about this appointment..."></textarea>
                        </div>
                        
                        <div class="text-sm text-gray-600">
                            This will update the appointment status to <span id="statusText" class="font-semibold"></span>.
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" class="btn btn-outline">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold">Appointment Details</h3>
                </div>
                <div id="detailsContent" class="p-6"></div>
                <div class="px-6 py-4 border-t flex justify-end">
                    <button onclick="closeDetailsModal()" class="btn btn-outline">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateStatus(appointmentId, status) {
            document.getElementById('appointmentId').value = appointmentId;
            document.getElementById('appointmentStatus').value = status;
            document.getElementById('statusText').textContent = status.charAt(0).toUpperCase() + status.slice(1);
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        function viewDetails(appointment) {
            const content = `
                <div class="space-y-4">
                    <div class="border-b pb-4">
                        <h4 class="font-semibold text-gray-800 mb-2">Patient Information</h4>
                        <p><strong>Name:</strong> ${appointment.patient_first_name} ${appointment.patient_last_name}</p>
                        <p><strong>Phone:</strong> ${appointment.patient_phone}</p>
                        <p><strong>Email:</strong> ${appointment.patient_email}</p>
                        <p><strong>Age:</strong> ${appointment.patient_age} years</p>
                        <p><strong>Blood Group:</strong> ${appointment.patient_blood_group}</p>
                    </div>
                    <div class="border-b pb-4">
                        <h4 class="font-semibold text-gray-800 mb-2">Appointment Details</h4>
                        <p><strong>Date:</strong> ${new Date(appointment.appointment_date).toLocaleDateString()}</p>
                        <p><strong>Time:</strong> ${new Date('1970-01-01T' + appointment.appointment_time).toLocaleTimeString('en-US', {hour: 'numeric', minute: 'numeric', hour12: true})}</p>
                        <p><strong>Status:</strong> <span class="role-badge status-${appointment.status}">${appointment.status}</span></p>
                        <p><strong>Reason:</strong> ${appointment.reason || 'N/A'}</p>
                    </div>
                    ${appointment.notes ? `
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2">Notes</h4>
                        <p class="text-gray-700">${appointment.notes}</p>
                    </div>
                    ` : ''}
                </div>
            `;
            document.getElementById('detailsContent').innerHTML = content;
            document.getElementById('detailsModal').classList.remove('hidden');
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }

        // Close modals on outside click
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) closeDetailsModal();
        });
    </script>
</body>
</html>