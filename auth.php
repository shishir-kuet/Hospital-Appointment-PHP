<?php
// File: auth.php - Complete Authentication System
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'];
    $folder = ($role === 'doctor') ? 'doctors' : $role;
    header("Location: {$folder}/dashboard.php");
    exit();
}

$db = Database::getInstance();
$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login';

// Handle error messages from redirects
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'no_doctor_record') {
        $error = 'Your account is marked as a doctor but no doctor profile exists. Please contact admin.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'login') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            // Check user credentials and get patient data
            $sql = "SELECT u.*, p.age, p.blood_group
                    FROM users u 
                    LEFT JOIN patients p ON u.id = p.user_id
                    WHERE u.email = ? AND u.is_active = 1";
            
            $user = $db->fetchOne($sql, [$email]);
            
            if ($user && $password === $user['password']) {

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // Set age and blood group for patients
                if ($user['role'] === 'patient') {
                    // Calculate age from date_of_birth if it exists, otherwise use age field
                    $calculated_age = 25; // default
                    if (!empty($user['date_of_birth'])) {
                        $birthDate = new DateTime($user['date_of_birth']);
                        $today = new DateTime('today');
                        $calculated_age = $birthDate->diff($today)->y;
                    } elseif (!empty($user['age'])) {
                        $calculated_age = $user['age'];
                    }
                    
                    $_SESSION['user_age'] = $calculated_age;
                    $_SESSION['user_blood_group'] = $user['blood_group'] ?? 'O+';
                }
                
                // Redirect based on role
                $folder = ($user['role'] === 'doctor') ? 'doctors' : $user['role'];
                header("Location: {$folder}/dashboard.php");
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        }
    } elseif ($mode === 'register') {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $age = intval($_POST['age']);
        $blood_group = $_POST['blood_group'];
        $address = trim($_POST['address']);
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($age < 1 || $age > 120) {
            $error = 'Please enter a valid age.';
        } else {
            // Check if email exists
            $existing = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existing) {
                $error = 'An account with this email already exists.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Create user account
                 $user_sql = "INSERT INTO users (first_name, last_name, email, phone, password, role, created_at, is_active) 
                VALUES (?, ?, ?, ?, ?, 'patient', NOW(), 1)";

                $db->executeQuery($user_sql, [$first_name, $last_name, $email, $phone, $password]);

                    $user_id = $db->lastInsertId();
                    
                    // Create patient profile
                    $patient_sql = "INSERT INTO patients (user_id, age, blood_group, address, created_at) 
                                   VALUES (?, ?, ?, ?, NOW())";
                    
                    $db->executeQuery($patient_sql, [$user_id, $age, $blood_group, $address]);
                    
                    $db->commit();
                    $success = 'Account created successfully! You can now log in.';
                    $mode = 'login';
                    
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Registration failed. Please try again.';
                    error_log("Registration error: " . $e->getMessage());
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mode === 'login' ? 'Login' : 'Register'; ?> - Hospital Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-blue-50 min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-16 w-16 medical-icon bg-blue-600 text-white mb-4">
                    <i class="fas fa-hospital text-2xl"></i>
                </div>
                <h2 class="text-3xl font-extrabold text-gray-900 mb-2">
                    <?php echo $mode === 'login' ? 'Sign in to your account' : 'Create your account'; ?>
                </h2>
                <p class="text-gray-600">
                    <?php echo $mode === 'login' ? 'Welcome back! Please sign in to continue.' : 'Join our hospital management system'; ?>
                </p>
            </div>

            <!-- Auth Form -->
            <div class="card">
                <div class="px-8 py-8">
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

                    <form method="POST" class="space-y-6">
                        <?php if ($mode === 'login'): ?>
                            <!-- Login Form -->
                            <div>
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" required class="form-input" 
                                       placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>

                            <div>
                                <label class="form-label">Password</label>
                                <input type="password" name="password" required class="form-input" 
                                       placeholder="Enter your password">
                            </div>

                            <div>
                                <button type="submit" class="btn btn-primary w-full">
                                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                                </button>
                            </div>

                        <?php else: ?>
                            <!-- Registration Form -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" required class="form-input" 
                                           placeholder="First name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" required class="form-input" 
                                           placeholder="Last name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" required class="form-input" 
                                       placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>

                            <div>
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-input" 
                                       placeholder="Enter your phone number" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Age</label>
                                    <input type="number" name="age" required min="1" max="120" class="form-input" 
                                           placeholder="Age" value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="form-label">Blood Group</label>
                                    <select name="blood_group" required class="form-input">
                                        <option value="">Select Blood Group</option>
                                        <?php 
                                        $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                        foreach ($blood_groups as $bg): ?>
                                            <option value="<?php echo $bg; ?>" <?php echo ($_POST['blood_group'] ?? '') === $bg ? 'selected' : ''; ?>>
                                                <?php echo $bg; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-textarea" rows="2" 
                                          placeholder="Enter your address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" required minlength="6" class="form-input" 
                                           placeholder="Create password">
                                </div>
                                <div>
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" name="confirm_password" required minlength="6" class="form-input" 
                                           placeholder="Confirm password">
                                </div>
                            </div>

                            <div>
                                <button type="submit" class="btn btn-primary w-full">
                                    <i class="fas fa-user-plus mr-2"></i>Create Account
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Toggle Mode -->
            <div class="text-center">
                <?php if ($mode === 'login'): ?>
                    <p class="text-gray-600">
                        Don't have an account? 
                        <a href="auth.php?mode=register" class="text-blue-600 hover:text-blue-500 font-medium">
                            Sign up here
                        </a>
                    </p>
                <?php else: ?>
                    <p class="text-gray-600">
                        Already have an account? 
                        <a href="auth.php?mode=login" class="text-blue-600 hover:text-blue-500 font-medium">
                            Sign in here
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Form validation and UX enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Password strength indicator for registration
            const passwordInput = document.querySelector('input[name="password"]');
            const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
            
            if (passwordInput && confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', function() {
                    if (this.value && passwordInput.value !== this.value) {
                        this.setCustomValidity('Passwords do not match');
                        this.classList.add('border-red-500');
                    } else {
                        this.setCustomValidity('');
                        this.classList.remove('border-red-500');
                    }
                });
            }
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>