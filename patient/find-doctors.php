<?php
// File: patient/find-doctors.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header('Location: ../auth.php');
    exit();
}

$db = Database::getInstance();

// Ensure session variables are properly set (for existing users)
if (!isset($_SESSION['user_age']) || !isset($_SESSION['user_blood_group'])) {
    try {
        $user_data_sql = "SELECT u.*, p.age, p.blood_group
                          FROM users u 
                          LEFT JOIN patients p ON u.id = p.user_id
                          WHERE u.id = ?";
        
        $user_data = $db->fetchOne($user_data_sql, [$_SESSION['user_id']]);
        if ($user_data) {
            // Calculate age from date_of_birth if it exists, otherwise use age field
            $calculated_age = 25; // default
            if (!empty($user_data['date_of_birth'])) {
                $birthDate = new DateTime($user_data['date_of_birth']);
                $today = new DateTime('today');
                $calculated_age = $birthDate->diff($today)->y;
            } elseif (!empty($user_data['age'])) {
                $calculated_age = $user_data['age'];
            }
            
            $_SESSION['user_age'] = $calculated_age;
            $_SESSION['user_blood_group'] = $user_data['blood_group'] ?? 'O+';
        }
    } catch (Exception $e) {
        // Fallback if there are database issues
        $_SESSION['user_age'] = 25;
        $_SESSION['user_blood_group'] = 'O+';
    }
}

$user_age = $_SESSION['user_age'] ?? 25; // Fallback if session not properly set
$user_blood_group = $_SESSION['user_blood_group'] ?? 'O+'; // Fallback if session not properly set

// Get search filters
$search_specialization = $_GET['specialization'] ?? '';
$search_department = $_GET['department'] ?? '';
$search_symptoms = $_GET['symptoms'] ?? '';

// Smart doctor matching based on patient profile
$doctors = $db->findMatchingDoctors($user_age, $user_blood_group, $search_symptoms);

// Filter results based on search criteria
if ($search_specialization) {
    $doctors = array_filter($doctors, function($doctor) use ($search_specialization) {
        return stripos($doctor['specialization'], $search_specialization) !== false;
    });
}

if ($search_department) {
    $doctors = array_filter($doctors, function($doctor) use ($search_department) {
        return stripos($doctor['department_name'], $search_department) !== false;
    });
}

// Get all departments for filter
$departments = $db->fetchAll("SELECT * FROM departments WHERE is_active = 1 ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Smart Doctors - Hospital Management</title>
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
                        <div class="medical-icon bg-primary text-white mr-3">
                            <i class="fas fa-hospital"></i>
                        </div>
                        <h1 class="text-xl font-bold text-gray-800">Patient Portal</h1>
                    </a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
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
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Smart Doctor Finder</h1>
            <p class="text-gray-600">
                Doctors recommended based on your age (<?php echo $user_age; ?> years) 
                and blood group (<?php echo htmlspecialchars($user_blood_group); ?>)
            </p>
        </div>

        <!-- Search Filters -->
        <div class="card mb-8">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Find Your Perfect Doctor</h3>
            </div>
            <div class="p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="form-label">Department</label>
                        <select name="department" class="form-input">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['name']); ?>" 
                                        <?php echo $search_department === $dept['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-label">Specialization</label>
                        <input type="text" name="specialization" 
                               class="form-input" 
                               placeholder="e.g., Cardiologist"
                               value="<?php echo htmlspecialchars($search_specialization); ?>">
                    </div>
                    
                    <div>
                        <label class="form-label">Symptoms (Optional)</label>
                        <input type="text" name="symptoms" 
                               class="form-input" 
                               placeholder="Describe your symptoms"
                               value="<?php echo htmlspecialchars($search_symptoms); ?>">
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="btn btn-primary w-full">
                            <i class="fas fa-search mr-2"></i>Find Doctors
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Smart Recommendations Alert -->
        <?php if ($user_age <= 18): ?>
            <div class="alert alert-info mb-6">
                <i class="fas fa-baby mr-2"></i>
                <strong>Pediatric Care Recommended:</strong> Since you're <?php echo $user_age; ?> years old, 
                we're showing pediatric specialists first who specialize in children's healthcare.
            </div>
        <?php elseif ($user_age >= 65): ?>
            <div class="alert alert-warning mb-6">
                <i class="fas fa-user-clock mr-2"></i>
                <strong>Geriatric Care Recommended:</strong> Since you're <?php echo $user_age; ?> years old, 
                we're showing geriatric specialists first who specialize in elderly care.
            </div>
        <?php else: ?>
            <div class="alert alert-success mb-6">
                <i class="fas fa-user-md mr-2"></i>
                <strong>Adult Care:</strong> Showing doctors specialized in adult healthcare for your age group.
            </div>
        <?php endif; ?>

        <!-- Doctors Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($doctors)): ?>
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-user-md text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Doctors Found</h3>
                    <p class="text-gray-500">Try adjusting your search criteria</p>
                </div>
            <?php else: ?>
                <?php foreach ($doctors as $doctor): ?>
                    <div class="card card-hover">
                        <div class="p-6">
                            <!-- Doctor Header -->
                            <div class="flex items-center mb-4">
                                <div class="medical-icon bg-blue-100 text-blue-600 mr-4">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800">
                                        Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                    </h3>
                                    <p class="text-sm text-blue-600"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($doctor['department_name']); ?></p>
                                </div>
                            </div>

                            <!-- Doctor Details -->
                            <div class="space-y-3 mb-4">
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-graduation-cap w-4 mr-2"></i>
                                    <?php echo $doctor['experience_years']; ?> years experience
                                </div>
                                
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-dollar-sign w-4 mr-2"></i>
                                    ৳<?php echo number_format($doctor['consultation_fee'], 2); ?> consultation
                                </div>
                                
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-star w-4 mr-2"></i>
                                    Rating: <?php echo number_format($doctor['rating'], 1); ?>/5.0
                                </div>

                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-users w-4 mr-2"></i>
                                    <?php echo $doctor['total_appointments']; ?> patients treated
                                </div>
                            </div>

                            <!-- Available Days -->
                            <div class="mb-4">
                                <p class="text-sm font-medium text-gray-700 mb-2">Available Days:</p>
                                <div class="flex flex-wrap gap-1">
                                    <?php 
                                    $days = explode(',', $doctor['available_days']);
                                    foreach ($days as $day): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">
                                            <?php echo ucfirst(trim($day)); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Working Hours -->
                            <div class="mb-4">
                                <p class="text-sm text-gray-600">
                                    <i class="fas fa-clock mr-2"></i>
                                    <?php echo date('g:i A', strtotime($doctor['start_time'])); ?> - 
                                    <?php echo date('g:i A', strtotime($doctor['end_time'])); ?>
                                </p>
                            </div>

                            <?php if ($doctor['bio']): ?>
                                <div class="mb-4">
                                    <p class="text-sm text-gray-600 line-clamp-2"><?php echo htmlspecialchars($doctor['bio']); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Match Indicator -->
                            <?php 
                            $is_age_match = false;
                            if ($user_age <= 18 && strpos($doctor['department_name'], 'Pediatric') !== false) $is_age_match = true;
                            elseif ($user_age >= 65 && strpos($doctor['department_name'], 'Geriatric') !== false) $is_age_match = true;
                            elseif ($user_age > 18 && $user_age < 65 && strpos($doctor['department_name'], 'Internal') !== false) $is_age_match = true;
                            ?>

                            <?php if ($is_age_match): ?>
                                <div class="mb-4 px-3 py-2 bg-green-50 border border-green-200 rounded">
                                    <p class="text-sm text-green-700 flex items-center">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        Perfect age match for your profile
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- Action Button -->
                            <div class="flex space-x-2">
                                <a href="book-appointment.php?doctor_id=<?php echo $doctor['id']; ?>" 
                                   class="flex-1 btn btn-primary text-center">
                                    <i class="fas fa-calendar-plus mr-2"></i>Book Appointment
                                </a>
                                <a href="doctor-profile.php?id=<?php echo $doctor['id']; ?>" 
                                   class="flex-1 btn btn-outline text-center">
                                    <i class="fas fa-eye mr-2"></i>View Profile
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($doctors)): ?>
            <div class="text-center mt-8">
                <p class="text-gray-600 mb-4">Found <?php echo count($doctors); ?> doctors matching your profile</p>
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on scroll
            const cards = document.querySelectorAll('.card-hover');
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '0';
                        entry.target.style.transform = 'translateY(20px)';
                        setTimeout(() => {
                            entry.target.style.transition = 'all 0.6s ease';
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, 100);
                    }
                });
            }, observerOptions);

            cards.forEach(card => {
                observer.observe(card);
            });
        });
    </script>
</body>
</html>