<?php
// File: admin/manage-patients.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth.php');
    exit();
}

$db = Database::getInstance();
$error = '';
$success = '';

// Handle patient actions (activate/deactivate/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $patient_user_id = intval($_POST['patient_id'] ?? 0);
    
    if ($action === 'toggle_status' && $patient_user_id > 0) {
        $current_status = intval($_POST['current_status']);
        $new_status = $current_status ? 0 : 1;
        
        $db->executeQuery("UPDATE users SET is_active = ? WHERE id = ? AND role = 'patient'", [$new_status, $patient_user_id]);
        $success = 'Patient status updated successfully!';
        
    } elseif ($action === 'delete' && $patient_user_id > 0) {
        try {
            $db->beginTransaction();
            
            // Check if patient has appointments
            $appointment_check = $db->fetchOne(
                "SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'scheduled'", 
                [$patient_user_id]
            );
            
            if ($appointment_check['count'] > 0) {
                $error = 'Cannot delete patient with scheduled appointments. Please cancel appointments first.';
            } else {
                // Soft delete - deactivate instead of removing
                $db->executeQuery("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'patient'", [$patient_user_id]);
                
                $db->commit();
                $success = 'Patient removed successfully!';
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Failed to remove patient. Please try again.';
        }
    }
}

// Get all patients with details
$patients_sql = "
    SELECT 
        u.*,
        p.age,
        p.blood_group,
        p.address,
        p.medical_history,
        COUNT(DISTINCT a.id) as total_appointments,
        COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as completed_appointments,
        COUNT(DISTINCT CASE WHEN a.status = 'scheduled' THEN a.id END) as scheduled_appointments,
        COUNT(DISTINCT CASE WHEN a.status = 'cancelled' THEN a.id END) as cancelled_appointments,
        SUM(DISTINCT b.total_amount) as total_bills,
        SUM(DISTINCT CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END) as paid_amount,
        SUM(DISTINCT CASE WHEN b.payment_status = 'pending' THEN b.total_amount ELSE 0 END) as pending_amount
    FROM users u
    INNER JOIN patients p ON u.id = p.user_id
    LEFT JOIN appointments a ON u.id = a.patient_id
    LEFT JOIN bills b ON u.id = b.patient_id
    WHERE u.role = 'patient'
    GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone, u.is_active, 
             u.created_at, p.age, p.blood_group, p.address, p.medical_history
    ORDER BY u.is_active DESC, u.created_at DESC
";
$patients = $db->fetchAll($patients_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients - Admin Portal</title>
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
                    <a href="dashboard.php" class="flex items-center">
                        <div class="medical-icon bg-purple-600 text-white mr-3">
                            <i class="fas fa-hospital"></i>
                        </div>
                        <h1 class="text-xl font-bold text-gray-800">Admin Portal</h1>
                    </a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                    </a>
                    <a href="manage-doctors.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-user-md mr-1"></i>Doctors
                    </a>
                    <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
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
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Manage Patients</h1>
            <p class="text-gray-600">View and manage patient accounts</p>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-error mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="card bg-blue-50">
                <div class="px-6 py-4">
                    <div class="flex items-center">
                        <i class="fas fa-users text-blue-600 text-2xl mr-4"></i>
                        <div>
                            <p class="text-sm text-gray-600">Total Patients</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo count($patients); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card bg-green-50">
                <div class="px-6 py-4">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 text-2xl mr-4"></i>
                        <div>
                            <p class="text-sm text-gray-600">Active Patients</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo count(array_filter($patients, fn($p) => $p['is_active'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card bg-purple-50">
                <div class="px-6 py-4">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-check text-purple-600 text-2xl mr-4"></i>
                        <div>
                            <p class="text-sm text-gray-600">Total Appointments</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo array_sum(array_column($patients, 'total_appointments')); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card bg-orange-50">
                <div class="px-6 py-4">
                    <div class="flex items-center">
                        <i class="fas fa-dollar-sign text-orange-600 text-2xl mr-4"></i>
                        <div>
                            <p class="text-sm text-gray-600">Total Revenue</p>
                            <p class="text-2xl font-bold text-gray-800">
                                ৳<?php echo number_format(array_sum(array_column($patients, 'total_bills')), 0); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patients Table -->
        <div class="card">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">All Patients</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Age / Blood</th>
                            <th>Contact</th>
                            <th>Appointments</th>
                            <th>Bills</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($patients)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-8">
                                    <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                                    <p class="text-gray-500">No patients found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($patients as $patient): ?>
                                <tr class="hover:bg-gray-50">
                                    <td>
                                        <div>
                                            <p class="font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($patient['email']); ?></p>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-sm">
                                            <div class="text-gray-800">
                                                <i class="fas fa-birthday-cake text-blue-500 mr-1"></i>
                                                <?php echo $patient['age']; ?> years
                                            </div>
                                            <div class="text-gray-600">
                                                <i class="fas fa-tint text-red-500 mr-1"></i>
                                                <?php echo htmlspecialchars($patient['blood_group']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-sm">
                                            <div class="text-gray-600">
                                                <i class="fas fa-phone mr-1"></i>
                                                <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?>
                                            </div>
                                            <?php if ($patient['address']): ?>
                                                <div class="text-gray-500 text-xs mt-1">
                                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                                    <?php echo htmlspecialchars(substr($patient['address'], 0, 30)) . '...'; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-sm">
                                            <div class="text-gray-600">Total: <?php echo $patient['total_appointments']; ?></div>
                                            <div class="text-green-600">Completed: <?php echo $patient['completed_appointments']; ?></div>
                                            <div class="text-blue-600">Scheduled: <?php echo $patient['scheduled_appointments']; ?></div>
                                            <?php if ($patient['cancelled_appointments'] > 0): ?>
                                                <div class="text-red-600">Cancelled: <?php echo $patient['cancelled_appointments']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-sm">
                                            <?php if ($patient['total_bills'] > 0): ?>
                                                <div class="text-gray-800">Total: ৳<?php echo number_format($patient['total_bills'], 2); ?></div>
                                                <div class="text-green-600">Paid: ৳<?php echo number_format($patient['paid_amount'], 2); ?></div>
                                                <?php if ($patient['pending_amount'] > 0): ?>
                                                    <div class="text-red-600">Pending: ৳<?php echo number_format($patient['pending_amount'], 2); ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">No bills</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-sm text-gray-600">
                                            <?php echo date('M j, Y', strtotime($patient['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($patient['is_active']): ?>
                                            <span class="role-badge bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="role-badge bg-red-100 text-red-800">
                                                <i class="fas fa-times-circle"></i> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <a href="view-patient.php?id=<?php echo $patient['id']; ?>" 
                                               class="btn btn-sm bg-blue-100 text-blue-600 hover:bg-blue-200" 
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to change this patient\'s status?');">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $patient['is_active'] ? 1 : 0; ?>">
                                                <button type="submit" 
                                                        class="btn btn-sm <?php echo $patient['is_active'] ? 'bg-yellow-100 text-yellow-600 hover:bg-yellow-200' : 'bg-green-100 text-green-600 hover:bg-green-200'; ?>" 
                                                        title="<?php echo $patient['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $patient['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this patient? This action cannot be undone.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                                                <button type="submit" 
                                                        class="btn btn-sm bg-red-100 text-red-600 hover:bg-red-200" 
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Patient Statistics by Age Group -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
            <div class="card">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Patients by Age Group</h3>
                </div>
                <div class="p-6">
                    <?php
                    $pediatric = count(array_filter($patients, fn($p) => $p['age'] <= 18));
                    $adult = count(array_filter($patients, fn($p) => $p['age'] > 18 && $p['age'] < 65));
                    $geriatric = count(array_filter($patients, fn($p) => $p['age'] >= 65));
                    ?>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <i class="fas fa-baby text-blue-600 mr-2"></i>
                                <span class="text-gray-700">Pediatric (0-18)</span>
                            </div>
                            <span class="font-bold text-gray-800"><?php echo $pediatric; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <div>
                                <i class="fas fa-user text-green-600 mr-2"></i>
                                <span class="text-gray-700">Adult (19-64)</span>
                            </div>
                            <span class="font-bold text-gray-800"><?php echo $adult; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <div>
                                <i class="fas fa-user-clock text-orange-600 mr-2"></i>
                                <span class="text-gray-700">Geriatric (65+)</span>
                            </div>
                            <span class="font-bold text-gray-800"><?php echo $geriatric; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Blood Group Distribution</h3>
                </div>
                <div class="p-6">
                    <?php
                    $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                    $blood_distribution = [];
                    foreach ($blood_groups as $bg) {
                        $blood_distribution[$bg] = count(array_filter($patients, fn($p) => $p['blood_group'] === $bg));
                    }
                    arsort($blood_distribution);
                    ?>
                    <div class="space-y-2">
                        <?php foreach (array_slice($blood_distribution, 0, 5) as $bg => $count): ?>
                            <?php if ($count > 0): ?>
                                <div class="flex justify-between items-center">
                                    <div>
                                        <i class="fas fa-tint text-red-600 mr-2"></i>
                                        <span class="text-gray-700"><?php echo $bg; ?></span>
                                    </div>
                                    <span class="font-bold text-gray-800"><?php echo $count; ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Registrations</h3>
                </div>
                <div class="p-6">
                    <?php
                    $today = count(array_filter($patients, fn($p) => date('Y-m-d', strtotime($p['created_at'])) === date('Y-m-d')));
                    $this_week = count(array_filter($patients, fn($p) => strtotime($p['created_at']) >= strtotime('-7 days')));
                    $this_month = count(array_filter($patients, fn($p) => strtotime($p['created_at']) >= strtotime('-30 days')));
                    ?>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <i class="fas fa-calendar-day text-blue-600 mr-2"></i>
                                <span class="text-gray-700">Today</span>
                            </div>
                            <span class="font-bold text-gray-800"><?php echo $today; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <div>
                                <i class="fas fa-calendar-week text-green-600 mr-2"></i>
                                <span class="text-gray-700">This Week</span>
                            </div>
                            <span class="font-bold text-gray-800"><?php echo $this_week; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <div>
                                <i class="fas fa-calendar-alt text-purple-600 mr-2"></i>
                                <span class="text-gray-700">This Month</span>
                            </div>
                            <span class="font-bold text-gray-800"><?php echo $this_month; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });

        // Add table row hover effect
        const tableRows = document.querySelectorAll('tbody tr');
        tableRows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.005)';
                this.style.transition = 'transform 0.2s ease';
            });
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Search functionality (optional enhancement)
        function searchPatients() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.querySelector('table');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                tr[i].style.display = found ? '' : 'none';
            }
        }
    </script>
</body>
</html>