<?php
// File: patient/doctor-profile.php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header('Location: ../auth.php');
    exit();
}

$db = Database::getInstance();
$doctor_id = intval($_GET['id'] ?? 0);

if ($doctor_id <= 0) {
    header('Location: find-doctors.php');
    exit();
}

// Get doctor details with comprehensive information
$doctor_sql = "
    SELECT 
        d.*,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        dept.name as department_name,
        dept.description as department_description,
        COUNT(DISTINCT a.id) as total_appointments,
        COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as completed_appointments
    FROM doctors d
    INNER JOIN users u ON d.user_id = u.id
    INNER JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN appointments a ON d.id = a.doctor_id
    WHERE d.id = ? AND d.is_available = 1 AND u.is_active = 1
    GROUP BY d.id, u.id, dept.id
";

$doctor = $db->fetchOne($doctor_sql, [$doctor_id]);

if (!$doctor) {
    header('Location: find-doctors.php?error=doctor_not_found');
    exit();
}

// Get doctor's reviews with patient names (if reviews table exists)
$reviews = [];
try {
    $reviews_sql = "
        SELECT 
            r.*,
            u.first_name as patient_first_name,
            u.last_name as patient_last_name,
            a.appointment_date
        FROM reviews r
        INNER JOIN appointments a ON r.appointment_id = a.id
        INNER JOIN users u ON a.patient_id = u.id
        WHERE r.doctor_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ";
    $reviews = $db->fetchAll($reviews_sql, [$doctor_id]);
} catch (Exception $e) {
    // Reviews table might not exist yet, handle gracefully
    $reviews = [];
}

// Set default values for reviews if not calculated
$doctor['total_reviews'] = count($reviews);
$doctor['avg_rating'] = $doctor['rating'] ?? 4.0; // Use doctor's base rating as default, fallback to 4.0

// Calculate average rating from reviews if available
if (!empty($reviews)) {
    $ratings = array_column($reviews, 'rating');
    if (!empty($ratings)) {
        $doctor['avg_rating'] = array_sum($ratings) / count($ratings);
    }
}

// Get doctor's availability schedule
$schedule = [
    'available_days' => $doctor['available_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday',
    'start_time' => $doctor['start_time'] ?? '09:00:00',
    'end_time' => $doctor['end_time'] ?? '17:00:00',
    'consultation_duration' => $doctor['consultation_duration'] ?? 30
];

// Get recent appointment statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_appointments,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN status = 'scheduled' AND appointment_date >= CURDATE() THEN 1 END) as upcoming_count,
        COUNT(CASE WHEN appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as recent_count
    FROM appointments 
    WHERE doctor_id = ?
";
$stats = $db->fetchOne($stats_sql, [$doctor_id]);

// Check if current patient has an appointment with this doctor
$patient_appointment_sql = "
    SELECT id, appointment_date, appointment_time, status
    FROM appointments 
    WHERE doctor_id = ? AND patient_id = ?
    ORDER BY appointment_date DESC
    LIMIT 1
";
$patient_appointment = $db->fetchOne($patient_appointment_sql, [$doctor_id, $_SESSION['user_id']]);

// Get doctor's specialties/expertise areas
$specialties = array_filter(array_map('trim', explode(',', $doctor['specialization'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?> - Doctor Profile</title>
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
                    <a href="find-doctors.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-search mr-1"></i>Find Doctors
                    </a>
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
        <!-- Back Button -->
        <div class="mb-6">
            <a href="find-doctors.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Back to Doctor Search
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Doctor Profile Card -->
            <div class="lg:col-span-2">
                <div class="card">
                    <div class="px-6 py-8">
                        <!-- Doctor Header -->
                        <div class="flex items-start mb-6">
                            <div class="medical-icon bg-blue-100 text-blue-600 mr-6" style="width: 5rem; height: 5rem; font-size: 2rem;">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <div class="flex-1">
                                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                                    Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                </h1>
                                <p class="text-xl text-blue-600 mb-2"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                <p class="text-lg text-gray-600 mb-4"><?php echo htmlspecialchars($doctor['department_name']); ?></p>
                                
                                <!-- Rating and Stats -->
                                <div class="flex items-center space-x-6 mb-4">
                                    <div class="flex items-center">
                                        <div class="flex text-yellow-400 mr-2">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <?php if($i <= floor($doctor['avg_rating'] ?? $doctor['rating'])): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php elseif($i - 0.5 <= ($doctor['avg_rating'] ?? $doctor['rating'])): ?>
                                                    <i class="fas fa-star-half-alt"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="text-gray-600">
                                            <?php echo number_format($doctor['avg_rating'] ?? $doctor['rating'], 1); ?>/5.0
                                            (<?php echo $doctor['total_reviews']; ?> reviews)
                                        </span>
                                    </div>
                                    <div class="text-gray-600">
                                        <i class="fas fa-users mr-1"></i>
                                        <?php echo $stats['completed_count']; ?> patients treated
                                    </div>
                                </div>

                                <!-- Quick Stats -->
                                <div class="grid grid-cols-3 gap-4 mb-6">
                                    <div class="text-center p-3 bg-green-50 rounded-lg">
                                        <div class="text-2xl font-bold text-green-600"><?php echo $doctor['experience_years']; ?></div>
                                        <div class="text-sm text-green-600">Years Experience</div>
                                    </div>
                                    <div class="text-center p-3 bg-blue-50 rounded-lg">
                                        <div class="text-2xl font-bold text-blue-600">৳<?php echo number_format($doctor['consultation_fee'], 0); ?></div>
                                        <div class="text-sm text-blue-600">Consultation Fee</div>
                                    </div>
                                    <div class="text-center p-3 bg-purple-50 rounded-lg">
                                        <div class="text-2xl font-bold text-purple-600"><?php echo $stats['recent_count']; ?></div>
                                        <div class="text-sm text-purple-600">Recent Patients</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Doctor Bio -->
                        <?php if (!empty($doctor['bio'])): ?>
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-3">About Dr. <?php echo htmlspecialchars($doctor['last_name']); ?></h3>
                                <p class="text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($doctor['bio'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Specializations -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Specializations</h3>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach($specialties as $specialty): ?>
                                    <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                                        <?php echo htmlspecialchars(trim($specialty)); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Department Info -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Department</h3>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($doctor['department_name']); ?></h4>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($doctor['department_description']); ?></p>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Contact Information</h3>
                            <div class="space-y-2">
                                <div class="flex items-center text-gray-700">
                                    <i class="fas fa-envelope w-5 mr-3"></i>
                                    <span><?php echo htmlspecialchars($doctor['email']); ?></span>
                                </div>
                                <div class="flex items-center text-gray-700">
                                    <i class="fas fa-phone w-5 mr-3"></i>
                                    <span><?php echo htmlspecialchars($doctor['phone']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Patient Reviews -->
                <?php if (!empty($reviews)): ?>
                    <div class="card mt-8">
                        <div class="px-6 py-4 border-b">
                            <h3 class="text-lg font-semibold text-gray-800">Patient Reviews</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-6">
                                <?php foreach($reviews as $review): ?>
                                    <div class="border-b border-gray-200 pb-6 last:border-b-0 last:pb-0">
                                        <div class="flex items-start justify-between mb-2">
                                            <div>
                                                <div class="font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($review['patient_first_name'] . ' ' . substr($review['patient_last_name'], 0, 1)); ?>.
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    Appointment: <?php echo date('M j, Y', strtotime($review['appointment_date'])); ?>
                                                </div>
                                            </div>
                                            <div class="flex text-yellow-400">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? '' : 'text-gray-300'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <p class="text-gray-700"><?php echo htmlspecialchars($review['comment']); ?></p>
                                        <div class="text-xs text-gray-500 mt-2">
                                            Reviewed on <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <!-- Schedule & Availability -->
                <div class="card mb-6">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">Schedule & Availability</h3>
                    </div>
                    <div class="p-6">
                        <div class="mb-4">
                            <h4 class="font-medium text-gray-900 mb-2">Available Days</h4>
                            <div class="flex flex-wrap gap-1">
                                <?php 
                                $days = explode(',', $schedule['available_days']);
                                foreach($days as $day): ?>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">
                                        <?php echo ucfirst(trim($day)); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h4 class="font-medium text-gray-900 mb-2">Working Hours</h4>
                            <p class="text-gray-600">
                                <i class="fas fa-clock mr-2"></i>
                                <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                            </p>
                        </div>

                        <div class="mb-6">
                            <h4 class="font-medium text-gray-900 mb-2">Consultation Duration</h4>
                            <p class="text-gray-600">
                                <i class="fas fa-stopwatch mr-2"></i>
                                <?php echo $schedule['consultation_duration']; ?> minutes per session
                            </p>
                        </div>

                        <!-- Book Appointment Button -->
                        <div class="space-y-3">
                            <a href="book-appointment.php?doctor_id=<?php echo $doctor['id']; ?>" 
                               class="btn btn-primary w-full text-center">
                                <i class="fas fa-calendar-plus mr-2"></i>Book Appointment
                            </a>
                            
                            <?php if ($patient_appointment): ?>
                                <div class="p-3 bg-blue-50 border border-blue-200 rounded">
                                    <p class="text-sm font-medium text-blue-800">Your Last Appointment</p>
                                    <p class="text-sm text-blue-600">
                                        <?php echo date('M j, Y', strtotime($patient_appointment['appointment_date'])); ?>
                                        at <?php echo date('g:i A', strtotime($patient_appointment['appointment_time'])); ?>
                                    </p>
                                    <p class="text-xs text-blue-500 capitalize">
                                        Status: <?php echo $patient_appointment['status']; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Doctor Statistics -->
                <div class="card">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">Doctor Statistics</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Appointments</span>
                                <span class="font-semibold"><?php echo $stats['total_appointments']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Completed Treatments</span>
                                <span class="font-semibold text-green-600"><?php echo $stats['completed_count']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Upcoming Appointments</span>
                                <span class="font-semibold text-blue-600"><?php echo $stats['upcoming_count']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Success Rate</span>
                                <span class="font-semibold text-green-600">
                                    <?php echo $stats['total_appointments'] > 0 ? round(($stats['completed_count'] / $stats['total_appointments']) * 100, 1) : 0; ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add smooth scrolling for better UX
        document.addEventListener('DOMContentLoaded', function() {
            // Animate elements on scroll
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            });

            document.querySelectorAll('.card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>