<?php
// File: admin/manage-doctors.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth.php');
    exit();
}

$db = Database::getInstance();
$error = '';
$success = '';

// Handle doctor actions (activate/deactivate/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    
    if ($action === 'toggle_status' && $doctor_id > 0) {
        $current_status = intval($_POST['current_status']);
        $new_status = $current_status ? 0 : 1;
        
        $db->executeQuery("UPDATE doctors SET is_available = ? WHERE id = ?", [$new_status, $doctor_id]);
        $success = 'Doctor status updated successfully!';
        
    } elseif ($action === 'delete' && $doctor_id > 0) {
        try {
            $db->beginTransaction();
            
            // Get user_id for this doctor
            $doctor = $db->fetchOne("SELECT user_id FROM doctors WHERE id = ?", [$doctor_id]);
            
            if ($doctor) {
                // Soft delete - deactivate instead of removing
                $db->executeQuery("UPDATE users SET is_active = 0 WHERE id = ?", [$doctor['user_id']]);
                $db->executeQuery("UPDATE doctors SET is_available = 0 WHERE id = ?", [$doctor_id]);
                
                $db->commit();
                $success = 'Doctor removed successfully!';
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Failed to remove doctor. Please try again.';
        }
    }
}

// Get all doctors with details
$doctors_sql = "
    SELECT 
        d.*,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.is_active,
        u.created_at as user_created_at,
        dept.name as department_name,
        dept.age_group,
        COUNT(DISTINCT a.id) as total_appointments,
        COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as completed_appointments,
        COUNT(DISTINCT CASE WHEN a.status = 'scheduled' THEN a.id END) as scheduled_appointments
    FROM doctors d
    INNER JOIN users u ON d.user_id = u.id
    INNER JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN appointments a ON d.id = a.doctor_id
    GROUP BY d.id, u.first_name, u.last_name, u.email, u.phone, u.is_active, 
             u.created_at, dept.name, dept.age_group, d.specialization, d.consultation_fee,
             d.experience_years, d.bio, d.rating, d.available_days, d.start_time, 
             d.end_time, d.is_available, d.min_age, d.max_age, d.preferred_blood_groups
    ORDER BY u.is_active DESC, d.is_available DESC, d.rating DESC
";
$doctors = $db->fetchAll($doctors_sql);

// Get departments for filter
$departments = $db->fetchAll("SELECT * FROM departments WHERE is_active = 1 ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors - Admin Portal</title>
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
                    <a href="manage-patients.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-users mr-1"></i>Patients
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
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Manage Doctors</h1>
                <p class="text-gray-600">Add, edit, or remove doctors from the system</p>
            </div>
            <a href="add-doctor.php" class="btn btn-primary">
                <i class="fas fa-user-plus mr-2"></i>Add New Doctor
            </a>
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
                        <i class="fas fa-user-md text-blue-600 text-2xl mr-4"></i>
                        <div>
                            <p class="text-sm text-gray-600">Total Doctors</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo count($doctors); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card bg-green-50">
                <div class="px-6 py-4">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 text-2xl mr-4"></i>
                        <div>
                            <p class="text-sm text-gray-600">Active Doctors</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo count(array_filter($doctors, fn($d) => $d['is_available'] && $d['is_active'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card bg-yellow-50">
                <div class="px-6 py-4">
                    <div class="flex items-center">
                        <i class="fas fa-pause-circle text-yellow-600 text-2xl mr-4"></i>
                        <div>
                            <p class="text-sm text-gray-600">Inactive Doctors</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo count(array_filter($doctors, fn($d) => !$d['is_available'] || !$d['is_active'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card bg-purple-50">
                <div class="px-6 py-4">
                    <div class="flex items-center">
                        <i class="fas fa-building text-purple-600 text-2xl mr-4"></i>
                        <div>
                            <p class="text-sm text-gray-600">Departments</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo count($departments); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Doctors Table -->
        <div class="card">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">All Doctors</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Department</th>
                            <th>Specialization</th>
                            <th>Experience</th>
                            <th>Fee</th>
                            <th>Rating</th>
                            <th>Appointments</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($doctors)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-8">
                                    <i class="fas fa-user-md text-gray-400 text-4xl mb-4"></i>
                                    <p class="text-gray-500">No doctors found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr class="hover:bg-gray-50">
                                    <td>
                                        <div>
                                            <p class="font-semibold text-gray-800">
                                                Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($doctor['email']); ?></p>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?></p>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($doctor['department_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                                    <td><?php echo $doctor['experience_years']; ?> years</td>
                                    <td>$<?php echo number_format($doctor['consultation_fee'], 2); ?></td>
                                    <td>
                                        <span class="text-yellow-500">
                                            <i class="fas fa-star"></i> <?php echo number_format($doctor['rating'], 1); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-sm">
                                            <div class="text-gray-600">Total: <?php echo $doctor['total_appointments']; ?></div>
                                            <div class="text-green-600">Completed: <?php echo $doctor['completed_appointments']; ?></div>
                                            <div class="text-blue-600">Scheduled: <?php echo $doctor['scheduled_appointments']; ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($doctor['is_active'] && $doctor['is_available']): ?>
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
                                            <a href="edit-doctor.php?id=<?php echo $doctor['id']; ?>" 
                                               class="btn btn-sm bg-blue-100 text-blue-600 hover:bg-blue-200" 
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to change this doctor\'s status?');">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $doctor['is_available'] ? 1 : 0; ?>">
                                                <button type="submit" 
                                                        class="btn btn-sm <?php echo $doctor['is_available'] ? 'bg-yellow-100 text-yellow-600 hover:bg-yellow-200' : 'bg-green-100 text-green-600 hover:bg-green-200'; ?>" 
                                                        title="<?php echo $doctor['is_available'] ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $doctor['is_available'] ? 'pause' : 'play'; ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this doctor? This action cannot be undone.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
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
                this.style.transform = 'scale(1.01)';
                this.style.transition = 'transform 0.2s ease';
            });
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>