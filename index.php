<?php
// File: index.php - Hospital Appointment System Homepage
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

// Get statistics for homepage
try {
    $stats = $db->fetchOne("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE role = 'patient' AND is_active = 1) as total_patients,
            (SELECT COUNT(*) FROM doctors d INNER JOIN users u ON d.user_id = u.id WHERE d.is_available = 1 AND u.is_active = 1) as total_doctors,
            (SELECT COUNT(*) FROM departments WHERE is_active = 1) as total_departments,
            (SELECT COUNT(*) FROM appointments WHERE status = 'completed') as total_appointments
    ");
    
    $departments = $db->fetchAll("SELECT id, name, description FROM departments WHERE is_active = 1 ORDER BY name LIMIT 6");
    
} catch (Exception $e) {
    $stats = ['total_patients' => 0, 'total_doctors' => 0, 'total_departments' => 0, 'total_appointments' => 0];
    $departments = [];
}

// Handle login/register form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $sql = "SELECT u.*
                    FROM users u 
                    WHERE u.email = ? AND u.is_active = 1";
            
            $user = $db->fetchOne($sql, [$email]);
            
            if ($user && $password === $user['password']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // Redirect to dashboard
                $folder = ($user['role'] === 'doctor') ? 'doctors' : $user['role'];
                header("Location: {$folder}/dashboard.php");
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'register') {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $age = intval($_POST['age']);
        $blood_group = $_POST['blood_group'];
        $address = trim($_POST['address']);
        
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
            $existing = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existing) {
                $error = 'An account with this email already exists.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    $user_sql = "INSERT INTO users (first_name, last_name, email, phone, password, role, created_at, is_active) 
                                VALUES (?, ?, ?, ?, ?, 'patient', NOW(), 1)";
                    $db->executeQuery($user_sql, [$first_name, $last_name, $email, $phone, $password]);
                    $user_id = $db->lastInsertId();
                    
                    $patient_sql = "INSERT INTO patients (user_id, age, blood_group, address, created_at) 
                                   VALUES (?, ?, ?, ?, NOW())";
                    $db->executeQuery($patient_sql, [$user_id, $age, $blood_group, $address]);
                    
                    $db->commit();
                    $success = 'Account created successfully! You can now log in.';
                    
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
    <title>Hospital Appointment System - Your Health, Our Priority</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'medical-primary': '#0f766e',      // Professional teal/green
                        'medical-secondary': '#059669',    // Slightly lighter green
                        'medical-light': '#d1fae5',       // Very light green
                        'medical-accent': '#065f46',       // Dark green
                        'medical-gray': '#6b7280',         // Professional gray
                        'medical-bg': '#f8fafc',           // Clean background
                    }
                }
            }
        }
    </script>
    <style>
        .medical-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Header/Navigation -->
    <header class="bg-white shadow-lg relative z-50 border-b-2 border-medical-light">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="medical-icon bg-medical-primary text-white h-12 w-12">
                        <i class="fas fa-hospital text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">MediCare</h1>
                        <p class="text-sm text-medical-gray">Hospital Management System</p>
                    </div>
                </div>
                
                <nav class="hidden md:flex space-x-8">
                    <a href="#home" class="text-gray-700 hover:text-medical-primary font-medium transition duration-200">Home</a>
                    <a href="#services" class="text-gray-700 hover:text-medical-primary font-medium transition duration-200">Services</a>
                    <a href="#departments" class="text-gray-700 hover:text-medical-primary font-medium transition duration-200">Departments</a>
                    <a href="#about" class="text-gray-700 hover:text-medical-primary font-medium transition duration-200">About</a>
                </nav>
                
                <div class="flex space-x-3">
                    <button onclick="showLoginModal()" class="bg-medical-primary text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                    </button>
                    <button onclick="showRegisterModal()" class="border-2 border-medical-primary text-medical-primary px-6 py-2 rounded-lg hover:bg-medical-primary hover:text-white transition duration-200">
                        <i class="fas fa-user-plus mr-2"></i>Register
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="bg-medical-primary text-white py-20">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-5xl font-bold mb-6 leading-tight">Your Health, <span class="text-emerald-200">Our Priority</span></h2>
                    <p class="text-xl mb-8 text-green-100">Book appointments with expert doctors, manage your health records, and access quality healthcare services - all in one place.</p>
                    
                    <div class="flex space-x-4 mb-12">
                        <button onclick="showRegisterModal()" class="bg-white text-medical-primary px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 hover:shadow-lg transform hover:scale-105 transition duration-200 flex items-center">
                            <i class="fas fa-calendar-plus mr-2"></i>Book Appointment
                        </button>
                        <button onclick="document.getElementById('services').scrollIntoView({behavior: 'smooth'})" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-medical-primary transition duration-200">
                            Learn More
                        </button>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-emerald-200"><?php echo number_format($stats['total_patients']); ?>+</div>
                            <div class="text-sm text-green-100">Patients</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-emerald-200"><?php echo number_format($stats['total_doctors']); ?>+</div>
                            <div class="text-sm text-green-100">Doctors</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-emerald-200"><?php echo number_format($stats['total_departments']); ?>+</div>
                            <div class="text-sm text-green-100">Departments</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-emerald-200"><?php echo number_format($stats['total_appointments']); ?>+</div>
                            <div class="text-sm text-green-100">Appointments</div>
                        </div>
                    </div>
                </div>
                
                <div class="hidden md:block">
                    <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-3xl p-8">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="bg-white bg-opacity-20 rounded-xl p-6 text-center">
                                <i class="fas fa-user-md text-4xl mb-3 text-blue-200"></i>
                                <h4 class="font-semibold mb-2">Expert Doctors</h4>
                                <p class="text-sm text-blue-100">Qualified specialists</p>
                            </div>
                            <div class="bg-white bg-opacity-20 rounded-xl p-6 text-center">
                                <i class="fas fa-clock text-4xl mb-3 text-blue-200"></i>
                                <h4 class="font-semibold mb-2">24/7 Service</h4>
                                <p class="text-sm text-blue-100">Round-the-clock care</p>
                            </div>
                            <div class="bg-white bg-opacity-20 rounded-xl p-6 text-center">
                                <i class="fas fa-mobile-alt text-4xl mb-3 text-blue-200"></i>
                                <h4 class="font-semibold mb-2">Online Booking</h4>
                                <p class="text-sm text-blue-100">Easy appointments</p>
                            </div>
                            <div class="bg-white bg-opacity-20 rounded-xl p-6 text-center">
                                <i class="fas fa-shield-alt text-4xl mb-3 text-blue-200"></i>
                                <h4 class="font-semibold mb-2">Secure Data</h4>
                                <p class="text-sm text-blue-100">Privacy protected</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-20 bg-gradient-to-br from-blue-100 via-slate-100 to-indigo-100 relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-25">
            <div class="absolute top-10 left-10 w-20 h-20 bg-blue-400 rounded-full opacity-40"></div>
            <div class="absolute top-32 right-20 w-16 h-16 bg-slate-400 rounded-full opacity-45"></div>
            <div class="absolute bottom-20 left-32 w-12 h-12 bg-indigo-400 rounded-full opacity-40"></div>
            <div class="absolute bottom-40 right-10 w-24 h-24 bg-blue-500 rounded-full opacity-30"></div>
        </div>
        
        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Our Services</h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">Comprehensive healthcare services designed to meet all your medical needs</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <div class="bg-white rounded-xl p-8 service-card transition duration-300 shadow-lg">
                    <div class="medical-icon bg-emerald-50 text-medical-primary h-16 w-16 mb-6">
                        <i class="fas fa-calendar-check text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4">Online Appointment Booking</h3>
                    <p class="text-gray-600 mb-6">Schedule appointments with your preferred doctors at your convenience. Choose time slots that work for you.</p>
                    <ul class="text-sm text-gray-500 space-y-2 mb-6">
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Instant confirmation</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Flexible rescheduling</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>SMS reminders</li>
                    </ul>
                    <div class="text-center">
                        <button onclick="showRegisterModal()" class="bg-medical-primary text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Book Now
                        </button>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-8 service-card transition duration-300 shadow-lg">
                    <div class="medical-icon bg-green-100 text-green-600 h-16 w-16 mb-6">
                        <i class="fas fa-user-md text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4">Expert Medical Consultation</h3>
                    <p class="text-gray-600 mb-6">Connect with qualified doctors across multiple specializations for comprehensive healthcare.</p>
                    <ul class="text-sm text-gray-500 space-y-2 mb-6">
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Qualified specialists</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Detailed consultations</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Follow-up care</li>
                    </ul>
                    <div class="text-center">
                        <button onclick="document.getElementById('departments').scrollIntoView({behavior: 'smooth'})" class="bg-medical-primary text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-stethoscope mr-2"></i>View Doctors
                        </button>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-8 service-card transition duration-300 shadow-lg">
                    <div class="medical-icon bg-purple-100 text-purple-600 h-16 w-16 mb-6">
                        <i class="fas fa-file-medical text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4">Health Records Management</h3>
                    <p class="text-gray-600 mb-6">Maintain and access your complete medical history, prescriptions, and treatment records securely.</p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Secure storage</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Easy access</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Prescription history</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-20 bg-gradient-to-br from-gray-100 via-slate-100 to-zinc-100 relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-25">
            <div class="absolute top-10 left-10 w-20 h-20 bg-gray-400 rounded-full opacity-40"></div>
            <div class="absolute top-32 right-20 w-16 h-16 bg-slate-500 rounded-full opacity-45"></div>
            <div class="absolute bottom-20 left-32 w-12 h-12 bg-zinc-400 rounded-full opacity-35"></div>
            <div class="absolute bottom-40 right-10 w-24 h-24 bg-gray-500 rounded-full opacity-30"></div>
        </div>
        
        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">How It Works</h2>
                <p class="text-xl text-gray-600">Simple steps to get the healthcare you need</p>
            </div>
            
            <div class="grid md:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="medical-icon bg-medical-primary text-white h-16 w-16 mx-auto mb-6">
                        <span class="text-2xl font-bold">1</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Register Account</h3>
                    <p class="text-gray-600">Create your patient profile with basic information and medical details.</p>
                </div>
                
                <div class="text-center">
                    <div class="medical-icon bg-medical-primary text-white h-16 w-16 mx-auto mb-6">
                        <span class="text-2xl font-bold">2</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Choose Doctor</h3>
                    <p class="text-gray-600">Browse and select from our qualified doctors based on specialization.</p>
                </div>
                
                <div class="text-center">
                    <div class="medical-icon bg-medical-primary text-white h-16 w-16 mx-auto mb-6">
                        <span class="text-2xl font-bold">3</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Book Appointment</h3>
                    <p class="text-gray-600">Select your preferred date and time slot for the consultation.</p>
                </div>
                
                <div class="text-center">
                    <div class="medical-icon bg-medical-primary text-white h-16 w-16 mx-auto mb-6">
                        <span class="text-2xl font-bold">4</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Get Treatment</h3>
                    <p class="text-gray-600">Attend your appointment and receive quality medical care.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Departments Section -->
    <section id="departments" class="py-20 bg-gradient-to-br from-blue-100 via-slate-100 to-indigo-100 relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-25">
            <div class="absolute top-10 left-10 w-20 h-20 bg-blue-400 rounded-full opacity-40"></div>
            <div class="absolute top-32 right-20 w-16 h-16 bg-slate-400 rounded-full opacity-45"></div>
            <div class="absolute bottom-20 left-32 w-12 h-12 bg-indigo-400 rounded-full opacity-40"></div>
            <div class="absolute bottom-40 right-10 w-24 h-24 bg-blue-500 rounded-full opacity-30"></div>
        </div>
        
        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Our Departments</h2>
                <p class="text-xl text-gray-600">Specialized medical departments with expert healthcare professionals</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <?php foreach ($departments as $dept): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition duration-200 shadow-md">
                    <div class="medical-icon bg-medical-light text-medical-primary h-12 w-12 mb-4">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3"><?php echo htmlspecialchars($dept['name']); ?></h3>
                    <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($dept['description'] ?? 'Comprehensive medical care with expert specialists.'); ?></p>
                    <button onclick="showRegisterModal()" class="text-medical-primary font-medium hover:underline flex items-center">
                        <i class="fas fa-arrow-right mr-2"></i>Book Consultation
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 bg-gradient-to-br from-blue-100 via-slate-100 to-indigo-100 relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-25">
            <div class="absolute top-10 left-10 w-20 h-20 bg-blue-400 rounded-full opacity-40"></div>
            <div class="absolute top-32 right-20 w-16 h-16 bg-slate-400 rounded-full opacity-45"></div>
            <div class="absolute bottom-20 left-32 w-12 h-12 bg-indigo-400 rounded-full opacity-40"></div>
            <div class="absolute bottom-40 right-10 w-24 h-24 bg-blue-500 rounded-full opacity-30"></div>
        </div>
        
        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">About Our Hospital</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">
                    Dedicated to providing exceptional healthcare services with compassion, innovation, and excellence for over two decades.
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-6">Our Mission</h3>
                    <p class="text-gray-600 mb-6">
                        To provide accessible, high-quality healthcare services to our community through innovative medical practices, 
                        compassionate care, and cutting-edge technology. We are committed to improving the health and well-being of 
                        every patient we serve.
                    </p>
                    
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="text-center p-4 bg-white rounded-lg shadow-md hover:shadow-lg transition duration-200">
                            <div class="text-2xl font-bold text-medical-primary"><?php echo date('Y') - 2001; ?>+</div>
                            <div class="text-sm text-gray-600">Years of Service</div>
                        </div>
                        <div class="text-center p-4 bg-white rounded-lg shadow-md hover:shadow-lg transition duration-200">
                            <div class="text-2xl font-bold text-medical-primary">50K+</div>
                            <div class="text-sm text-gray-600">Happy Patients</div>
                        </div>
                    </div>

                    <h4 class="text-lg font-semibold text-gray-800 mb-3">Why Choose Us?</h4>
                    <ul class="space-y-2 text-gray-600">
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            Expert medical professionals with years of experience
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            State-of-the-art medical equipment and facilities
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            24/7 emergency services and patient care
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            Affordable healthcare with insurance acceptance
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            Online appointment booking and digital health records
                        </li>
                    </ul>
                </div>

                <div class="space-y-6">
                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-md hover:shadow-lg transition duration-200">
                        <div class="flex items-center mb-4">
                            <div class="medical-icon bg-medical-light text-medical-primary mr-4">
                                <i class="fas fa-heart"></i>
                            </div>
                            <h4 class="text-xl font-semibold text-gray-800">Patient-Centered Care</h4>
                        </div>
                        <p class="text-gray-600">
                            Every treatment plan is personalized to meet the unique needs of our patients, 
                            ensuring the best possible outcomes and patient satisfaction.
                        </p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-md hover:shadow-lg transition duration-200">
                        <div class="flex items-center mb-4">
                            <div class="medical-icon bg-medical-light text-medical-primary mr-4">
                                <i class="fas fa-microscope"></i>
                            </div>
                            <h4 class="text-xl font-semibold text-gray-800">Advanced Technology</h4>
                        </div>
                        <p class="text-gray-600">
                            We utilize the latest medical technology and evidence-based practices to provide 
                            accurate diagnoses and effective treatments.
                        </p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-md hover:shadow-lg transition duration-200">
                        <div class="flex items-center mb-4">
                            <div class="medical-icon bg-medical-light text-medical-primary mr-4">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4 class="text-xl font-semibold text-gray-800">Expert Team</h4>
                        </div>
                        <p class="text-gray-600">
                            Our multidisciplinary team of healthcare professionals works collaboratively 
                            to ensure comprehensive and coordinated care.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="text-center mt-16">
                <div class="bg-medical-primary text-white py-12 px-8 rounded-2xl">
                    <h3 class="text-3xl font-bold mb-4">Ready to Experience Quality Healthcare?</h3>
                    <p class="text-xl mb-8 text-blue-100">Join thousands of satisfied patients who trust us with their health.</p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <button onclick="showRegisterModal()" class="bg-white text-medical-primary px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-200">
                            <i class="fas fa-user-plus mr-2"></i>Register Now
                        </button>
                        <button onclick="showLoginModal()" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-medical-primary transition duration-200">
                            <i class="fas fa-sign-in-alt mr-2"></i>Login
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="medical-icon bg-medical-primary text-white h-10 w-10">
                            <i class="fas fa-hospital"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold">MediCare</h3>
                            <p class="text-gray-400 text-sm">Hospital Management</p>
                        </div>
                    </div>
                    <p class="text-gray-300">Your trusted partner in healthcare management, providing quality medical services with modern technology.</p>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-gray-300">
                        <li><a href="#home" class="hover:text-white">Home</a></li>
                        <li><a href="#services" class="hover:text-white">Services</a></li>
                        <li><a href="#about" class="hover:text-white">About</a></li>
                        <li><a href="#departments" class="hover:text-white">Departments</a></li>
                        <li><button onclick="showLoginModal()" class="hover:text-white">Login</button></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Services</h4>
                    <ul class="space-y-2 text-gray-300">
                        <li>Online Appointments</li>
                        <li>Expert Consultations</li>
                        <li>Health Records</li>
                        <li>Emergency Care</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Contact Info</h4>
                    <ul class="space-y-2 text-gray-300">
                        <li><i class="fas fa-phone mr-2"></i>+8801324207402</li>
                        <li><i class="fas fa-envelope mr-2"></i>info@medicare.com</li>
                        <li><i class="fas fa-map-marker-alt mr-2"></i>KUET, Khulna, Bangladesh</li>
                        <li><i class="fas fa-clock mr-2"></i>24/7 Available</li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2025 MediCare Hospital Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Login Modal -->
    <div id="loginModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="bg-white rounded-xl max-w-md w-full p-8 relative">
                <button onclick="closeModals()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
                
                <div class="text-center mb-8">
                    <div class="medical-icon bg-medical-primary text-white h-16 w-16 mx-auto mb-4">
                        <i class="fas fa-sign-in-alt text-xl"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-800">Welcome Back</h2>
                    <p class="text-gray-600 mt-2">Sign in to access your account</p>
                </div>

                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="login">
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Email Address</label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent"
                               placeholder="Enter your email">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Password</label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent"
                               placeholder="Enter your password">
                    </div>
                    
                    <button type="submit" class="w-full bg-medical-primary text-white py-3 rounded-lg font-semibold hover:bg-medical-accent hover:shadow-lg transition duration-200">
                        <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                    </button>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-gray-600">Don't have an account? 
                        <button onclick="switchToRegister()" class="text-medical-primary font-semibold hover:underline">Sign up</button>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="bg-white rounded-xl max-w-2xl w-full p-8 relative max-h-screen overflow-y-auto">
                <button onclick="closeModals()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
                
                <div class="text-center mb-8">
                    <div class="medical-icon bg-medical-primary text-white h-16 w-16 mx-auto mb-4">
                        <i class="fas fa-user-plus text-xl"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-800">Create Account</h2>
                    <p class="text-gray-600 mt-2">Join our healthcare management system</p>
                </div>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">First Name *</label>
                            <input type="text" name="first_name" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent"
                                   placeholder="First name">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Last Name *</label>
                            <input type="text" name="last_name" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent"
                                   placeholder="Last name">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Email Address *</label>
                        <input type="email" name="email" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent"
                               placeholder="Enter your email">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Phone Number</label>
                        <input type="tel" name="phone"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent"
                               placeholder="Enter phone number">
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Age *</label>
                            <input type="number" name="age" required min="1" max="120"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent"
                                   placeholder="Age">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Blood Group</label>
                            <select name="blood_group" 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent">
                                <option value="">Select blood group</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Address</label>
                        <textarea name="address" rows="3"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent"
                                  placeholder="Enter your address"></textarea>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Password *</label>
                            <input type="password" name="password" required minlength="6"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent"
                                   placeholder="Enter password">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Confirm Password *</label>
                            <input type="password" name="confirm_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-medical-primary focus:border-transparent"
                                   placeholder="Confirm password">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-medical-primary text-white py-3 rounded-lg font-semibold hover:bg-medical-accent hover:shadow-lg transition duration-200">
                        <i class="fas fa-user-plus mr-2"></i>Create Account
                    </button>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-gray-600">Already have an account? 
                        <button onclick="switchToLogin()" class="text-medical-primary font-semibold hover:underline">Sign in</button>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showLoginModal() {
            document.getElementById('loginModal').classList.remove('hidden');
            document.getElementById('registerModal').classList.add('hidden');
        }
        
        function showRegisterModal() {
            document.getElementById('registerModal').classList.remove('hidden');
            document.getElementById('loginModal').classList.add('hidden');
        }
        
        function closeModals() {
            document.getElementById('loginModal').classList.add('hidden');
            document.getElementById('registerModal').classList.add('hidden');
        }
        
        function switchToRegister() {
            showRegisterModal();
        }
        
        function switchToLogin() {
            showLoginModal();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const loginModal = document.getElementById('loginModal');
            const registerModal = document.getElementById('registerModal');
            
            if (event.target === loginModal || event.target === registerModal) {
                closeModals();
            }
        }
        
        // Show modal if there are form errors or success messages
        <?php if ($error || $success): ?>
            <?php if (isset($_POST['action']) && $_POST['action'] === 'login'): ?>
                showLoginModal();
            <?php elseif (isset($_POST['action']) && $_POST['action'] === 'register'): ?>
                showRegisterModal();
            <?php endif; ?>
        <?php endif; ?>
        
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>
