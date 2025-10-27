<?php
// File: admin/departments.php
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
            case 'add':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                
                if (!empty($name)) {
                    try {
                        $sql = "INSERT INTO departments (name, description, created_at) VALUES (?, ?, NOW())";
                        $db->executeQuery($sql, [$name, $description]);
                        $message = "Department added successfully!";
                    } catch (Exception $e) {
                        $error = "Error adding department: " . $e->getMessage();
                    }
                } else {
                    $error = "Department name is required.";
                }
                break;
                
            case 'update':
                $id = intval($_POST['id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (!empty($name) && $id > 0) {
                    try {
                        $sql = "UPDATE departments SET name = ?, description = ?, is_active = ? WHERE id = ?";
                        $db->executeQuery($sql, [$name, $description, $is_active, $id]);
                        $message = "Department updated successfully!";
                    } catch (Exception $e) {
                        $error = "Error updating department: " . $e->getMessage();
                    }
                } else {
                    $error = "Invalid data provided.";
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                if ($id > 0) {
                    try {
                        // Check if department has associated doctors
                        $check = $db->fetchOne("SELECT COUNT(*) as count FROM doctors WHERE department_id = ?", [$id]);
                        if ($check['count'] > 0) {
                            $error = "Cannot delete department. It has associated doctors.";
                        } else {
                            $sql = "DELETE FROM departments WHERE id = ?";
                            $db->executeQuery($sql, [$id]);
                            $message = "Department deleted successfully!";
                        }
                    } catch (Exception $e) {
                        $error = "Error deleting department: " . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get all departments
$departments_sql = "SELECT * FROM departments ORDER BY name";
$departments = $db->fetchAll($departments_sql);

// Get department statistics
$stats_sql = "
    SELECT 
        d.id,
        d.name,
        COUNT(DISTINCT doc.id) as doctor_count,
        COUNT(DISTINCT a.id) as appointment_count,
        d.is_active
    FROM departments d
    LEFT JOIN doctors doc ON d.id = doc.department_id
    LEFT JOIN appointments a ON doc.id = a.doctor_id
    GROUP BY d.id, d.name, d.is_active
    ORDER BY d.name
";
$dept_stats = $db->fetchAll($stats_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - Admin Dashboard</title>
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
                        <p class="text-sm text-gray-600">Department Management</p>
                    </div>
                </div>
                
                <nav class="flex space-x-6">
                    <a href="dashboard.php" class="text-gray-600 hover:text-medical-primary font-medium">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                    </a>
                    <a href="departments.php" class="text-medical-primary font-medium">
                        <i class="fas fa-building mr-1"></i>Departments
                    </a>
                    <a href="manage-doctors.php" class="text-gray-600 hover:text-medical-primary font-medium">
                        <i class="fas fa-user-md mr-1"></i>Doctors
                    </a>
                    <a href="manage-patients.php" class="text-gray-600 hover:text-medical-primary font-medium">
                        <i class="fas fa-users mr-1"></i>Patients
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
            <h2 class="text-3xl font-bold text-gray-900">Department Management</h2>
            <p class="text-gray-600 mt-2">Manage hospital departments and their information</p>
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

        <!-- Add New Department Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-plus-circle text-medical-primary mr-2"></i>Add New Department
            </h3>
            
            <form method="POST" class="grid md:grid-cols-3 gap-4">
                <input type="hidden" name="action" value="add">
                
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Department Name *</label>
                    <input type="text" name="name" required 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent"
                           placeholder="Enter department name">
                </div>
                
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Description</label>
                    <input type="text" name="description"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent"
                           placeholder="Enter description">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-gradient-to-r from-medical-primary to-medical-secondary text-white px-6 py-2 rounded-lg hover:shadow-lg transform hover:scale-105 transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Add Department
                    </button>
                </div>
            </form>
        </div>

        <!-- Departments Overview -->
        <div class="grid md:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100">Total Departments</p>
                        <p class="text-3xl font-bold"><?php echo count($departments); ?></p>
                    </div>
                    <i class="fas fa-building text-4xl text-blue-200"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100">Active Departments</p>
                        <p class="text-3xl font-bold"><?php echo count(array_filter($departments, function($d) { return $d['is_active']; })); ?></p>
                    </div>
                    <i class="fas fa-check-circle text-4xl text-green-200"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100">Total Doctors</p>
                        <p class="text-3xl font-bold"><?php echo array_sum(array_column($dept_stats, 'doctor_count')); ?></p>
                    </div>
                    <i class="fas fa-user-md text-4xl text-purple-200"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100">Total Appointments</p>
                        <p class="text-3xl font-bold"><?php echo array_sum(array_column($dept_stats, 'appointment_count')); ?></p>
                    </div>
                    <i class="fas fa-calendar-check text-4xl text-orange-200"></i>
                </div>
            </div>
        </div>

        <!-- Departments List -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-list text-medical-primary mr-2"></i>All Departments
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Doctors</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Appointments</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($dept_stats as $dept): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-building text-medical-primary mr-2"></i>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-600">
                                    <?php 
                                    $full_dept = array_filter($departments, function($d) use ($dept) { return $d['id'] == $dept['id']; });
                                    $full_dept = reset($full_dept);
                                    echo htmlspecialchars($full_dept['description'] ?? 'No description'); 
                                    ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-sm font-medium">
                                    <?php echo $dept['doctor_count']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-sm font-medium">
                                    <?php echo $dept['appointment_count']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($dept['is_active']): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-sm font-medium">
                                        <i class="fas fa-check mr-1"></i>Active
                                    </span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-sm font-medium">
                                        <i class="fas fa-times mr-1"></i>Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center space-x-2">
                                    <button onclick="editDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['name']); ?>', '<?php echo htmlspecialchars($full_dept['description'] ?? ''); ?>', <?php echo $dept['is_active'] ? 'true' : 'false'; ?>)"
                                            class="text-blue-600 hover:text-blue-800 px-2 py-1">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <?php if ($dept['doctor_count'] == 0): ?>
                                    <button onclick="deleteDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['name']); ?>')"
                                            class="text-red-600 hover:text-red-800 px-2 py-1">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <span class="text-gray-400 px-2 py-1" title="Cannot delete department with doctors">
                                        <i class="fas fa-trash"></i>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Edit Department</h3>
                
                <form id="editForm" method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="editId" name="id">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Department Name</label>
                        <input type="text" id="editName" name="name" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Description</label>
                        <textarea id="editDescription" name="description" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label class="flex items-center">
                            <input type="checkbox" id="editActive" name="is_active" class="mr-2">
                            <span class="text-gray-700">Active Department</span>
                        </label>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-medical-primary text-white px-4 py-2 rounded-lg hover:bg-medical-accent transition duration-200">
                            Update
                        </button>
                        <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="bg-white rounded-lg max-w-sm w-full p-6">
                <h3 class="text-lg font-semibold text-red-600 mb-4">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Confirm Delete
                </h3>
                
                <p class="text-gray-600 mb-6">Are you sure you want to delete the department "<span id="deleteName"></span>"?</p>
                
                <form id="deleteForm" method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteId" name="id">
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">
                            Delete
                        </button>
                        <button type="button" onclick="closeDeleteModal()" class="flex-1 bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editDepartment(id, name, description, isActive) {
            document.getElementById('editId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editDescription').value = description;
            document.getElementById('editActive').checked = isActive;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function deleteDepartment(id, name) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteName').textContent = name;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
    </script>

</body>
</html>