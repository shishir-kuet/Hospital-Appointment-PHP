<?php
// File: doctors/profile.php
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

// Get doctor and user information with department details
$doctor_info_sql = "
    SELECT 
        u.*,
        d.*,
        dept.name as department_name,
        dept.description as department_description
    FROM users u 
    INNER JOIN doctors d ON u.id = d.user_id 
    INNER JOIN departments dept ON d.department_id = dept.id
    WHERE u.id = ?
";
$doctor = $db->fetchOne($doctor_info_sql, [$user_id]);

if (!$doctor) {
    // Debug: Log the issue instead of redirecting
    error_log("Doctor profile: No doctor record found for user_id: $user_id");
    
    // Destroy session to prevent redirect loop
    session_destroy();
    
    // Redirect with error message
    header('Location: ../auth.php?error=no_doctor_record');
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_personal'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $clinic_address = trim($_POST['clinic_address']);
        
        try {
            // Update users table
            $update_user_sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?";
            $db->executeQuery($update_user_sql, [$first_name, $last_name, $email, $phone, $user_id]);
            
            // Update doctors table clinic address
            $update_doctor_sql = "UPDATE doctors SET clinic_address = ?, updated_at = NOW() WHERE user_id = ?";
            $db->executeQuery($update_doctor_sql, [$clinic_address, $user_id]);
            
            // Update session
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            
            $success_message = "Personal information updated successfully!";
            
            // Refresh doctor data
            $doctor = $db->fetchOne($doctor_info_sql, [$user_id]);
            
        } catch (Exception $e) {
            $error_message = "Failed to update personal information. Please try again.";
        }
    }
    
    if (isset($_POST['update_professional'])) {
        $specialization = trim($_POST['specialization']);
        $experience = intval($_POST['experience']);
        $education = trim($_POST['education']);
        $bio = trim($_POST['bio']);
        $consultation_fee = floatval($_POST['consultation_fee']);
        
        try {
            // Update doctors table
            $update_doctor_sql = "
                UPDATE doctors SET 
                    specialization = ?, 
                    experience_years = ?, 
                    education = ?, 
                    bio = ?, 
                    consultation_fee = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ";
            $db->executeQuery($update_doctor_sql, [
                $specialization, $experience, $education, $bio, 
                $consultation_fee, $user_id
            ]);
            
            $success_message = "Professional information updated successfully!";
            
            // Refresh doctor data
            $doctor = $db->fetchOne($doctor_info_sql, [$user_id]);
            
        } catch (Exception $e) {
            $error_message = "Failed to update professional information. Please try again.";
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } else {
            // Verify current password
            if ($current_password === $doctor['password']) {
                $update_password_sql = "UPDATE users SET password = ? WHERE id = ?";
                try {
                    $db->executeQuery($update_password_sql, [$new_password, $user_id]);
                    $success_message = "Password changed successfully!";
                } catch (Exception $e) {
                    $error_message = "Failed to change password.";
                }
            } else {
                $error_message = "Current password is incorrect.";
            }
        }
    }
}

// Get doctor statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_appointments,
        COUNT(DISTINCT patient_id) as total_patients,
        AVG(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100 as completion_rate,
        SUM(CASE WHEN appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as appointments_this_month
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
    <title>My Profile - Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></title>
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
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">My Profile</h1>
                    <p class="text-gray-600">Manage your professional information and settings</p>
                </div>
                <a href="dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-medium transition duration-300 hover:shadow-lg transform hover:scale-105 flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Profile Overview -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                    <div class="px-6 py-4 border-b bg-gradient-to-r from-blue-50 to-indigo-50 rounded-t-xl">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <div class="bg-blue-500 p-2 rounded-full mr-3">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            Profile Overview
                        </h3>
                    </div>
                    <div class="p-6 text-center">
                        <div class="bg-blue-500 p-6 rounded-full w-20 h-20 mx-auto mb-4 flex items-center justify-center">
                            <i class="fas fa-user-md text-white text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-1">
                            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                        </h3>
                        <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                        <p class="text-sm text-gray-500 mb-4"><?php echo htmlspecialchars($doctor['department_name']); ?></p>
                        
                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600"><?php echo $doctor['experience_years']; ?></div>
                                <div class="text-sm text-gray-600 font-medium">Years Experience</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">BDT <?php echo number_format($doctor['consultation_fee']); ?></div>
                                <div class="text-sm text-gray-600 font-medium">Consultation Fee</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 mt-6">
                    <div class="px-6 py-4 border-b bg-gradient-to-r from-green-50 to-emerald-50 rounded-t-xl">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <div class="bg-green-500 p-2 rounded-full mr-3">
                                <i class="fas fa-chart-bar text-white"></i>
                            </div>
                            Statistics
                        </h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-calendar-alt mr-2 text-blue-500"></i>Total Appointments
                            </span>
                            <span class="font-bold text-gray-900"><?php echo intval($stats['total_appointments']); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-users mr-2 text-green-500"></i>Total Patients
                            </span>
                            <span class="font-bold text-gray-900"><?php echo intval($stats['total_patients']); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-clock mr-2 text-purple-500"></i>This Month
                            </span>
                            <span class="font-bold text-gray-900"><?php echo intval($stats['appointments_this_month']); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600 flex items-center">
                                <i class="fas fa-check-circle mr-2 text-green-500"></i>Completion Rate
                            </span>
                            <span class="font-bold text-green-600"><?php echo number_format($stats['completion_rate'], 1); ?>%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Forms -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Personal Information -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                    <div class="px-6 py-4 border-b bg-gradient-to-r from-purple-50 to-indigo-50 rounded-t-xl">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <div class="bg-purple-500 p-2 rounded-full mr-3">
                                <i class="fas fa-user-edit text-white"></i>
                            </div>
                            Personal Information
                        </h3>
                    </div>
                    <form method="POST" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                <input type="text" name="first_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="<?php echo htmlspecialchars($doctor['first_name']); ?>" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                <input type="text" name="last_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="<?php echo htmlspecialchars($doctor['last_name']); ?>" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                <input type="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                                <input type="tel" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="<?php echo htmlspecialchars($doctor['phone']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Clinic Address</label>
                            <textarea name="clinic_address" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                      placeholder="Enter your clinic address..."><?php echo htmlspecialchars($doctor['clinic_address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button type="submit" name="update_personal" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-medium transition duration-300 hover:shadow-lg transform hover:scale-105 flex items-center">
                                <i class="fas fa-save mr-2"></i>Update Personal Info
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Professional Information -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                    <div class="px-6 py-4 border-b bg-gradient-to-r from-orange-50 to-red-50 rounded-t-xl">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <div class="bg-orange-500 p-2 rounded-full mr-3">
                                <i class="fas fa-stethoscope text-white"></i>
                            </div>
                            Professional Information
                        </h3>
                    </div>
                    <form method="POST" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Specialization</label>
                                <input type="text" name="specialization" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="<?php echo htmlspecialchars($doctor['specialization']); ?>" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Experience (Years)</label>
                                <input type="number" name="experience" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" min="0" max="50"
                                       value="<?php echo $doctor['experience_years']; ?>" required>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Education</label>
                                <textarea name="education" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                          placeholder="MBBS, MD, etc."><?php echo htmlspecialchars($doctor['education'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Consultation Fee (BDT)</label>
                                <input type="number" name="consultation_fee" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" min="0" step="0.01"
                                       value="<?php echo $doctor['consultation_fee']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Bio</label>
                            <textarea name="bio" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                      placeholder="Brief description about yourself and your practice..."><?php echo htmlspecialchars($doctor['bio'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button type="submit" name="update_professional" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium transition duration-300 hover:shadow-lg transform hover:scale-105 flex items-center">
                                <i class="fas fa-save mr-2"></i>Update Professional Info
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                    <div class="px-6 py-4 border-b bg-gradient-to-r from-red-50 to-pink-50 rounded-t-xl">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <div class="bg-red-500 p-2 rounded-full mr-3">
                                <i class="fas fa-lock text-white"></i>
                            </div>
                            Change Password
                        </h3>
                    </div>
                    <form method="POST" class="p-6" onsubmit="return validatePassword()">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                <input type="password" name="current_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                <input type="password" name="new_password" id="new_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" minlength="6" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" minlength="6" required>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button type="submit" name="change_password" class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg font-medium transition duration-300 hover:shadow-lg transform hover:scale-105 flex items-center">
                                <i class="fas fa-key mr-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        </div> <!-- End relative z-10 -->
    </div>

    <script>
        function validatePassword() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                alert('New passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 6) {
                alert('New password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        }

        // Real-time password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                this.style.borderColor = '#ef4444';
            } else {
                this.setCustomValidity('');
                this.style.borderColor = '#d1d5db';
            }
        });
    </script>
</body>
</html>