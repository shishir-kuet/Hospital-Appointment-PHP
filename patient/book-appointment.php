<?php
// File: patient/book-appointment.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header('Location: ../auth.php');
    exit();
}

$db = Database::getInstance();
$patient_id = $_SESSION['user_id'];
$doctor_id = intval($_GET['doctor_id'] ?? 0);
$error = '';
$success = '';

// Get doctor details
if ($doctor_id > 0) {
    $doctor_sql = "
        SELECT 
            d.*,
            u.first_name,
            u.last_name,
            u.email,
            dept.name as department_name,
            dept.description as department_description
        FROM doctors d
        INNER JOIN users u ON d.user_id = u.id
        INNER JOIN departments dept ON d.department_id = dept.id
        WHERE d.id = ? AND d.is_available = 1 AND u.is_active = 1
    ";
    $doctor = $db->fetchOne($doctor_sql, [$doctor_id]);
    
    if (!$doctor) {
        header('Location: find-doctors.php');
        exit();
    }
} else {
    header('Location: find-doctors.php');
    exit();
}

// Get doctor's existing appointments to show unavailable slots
$existing_appointments_sql = "
    SELECT appointment_date, appointment_time 
    FROM appointments 
    WHERE doctor_id = ? AND status = 'scheduled'
    ORDER BY appointment_date, appointment_time
";
$existing_appointments = $db->fetchAll($existing_appointments_sql, [$doctor_id]);

// Convert to array for easier checking
$booked_slots = [];
foreach ($existing_appointments as $apt) {
    $key = $apt['appointment_date'] . '_' . $apt['appointment_time'];
    $booked_slots[$key] = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $reason = trim($_POST['reason']);
    
    // Validation
    if (empty($appointment_date) || empty($appointment_time)) {
        $error = 'Please select date and time for the appointment.';
    } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = 'Cannot book appointments in the past.';
    } elseif (empty($reason)) {
        $error = 'Please provide a reason for your visit.';
    } else {
        // Check if slot is already booked
        $slot_key = $appointment_date . '_' . $appointment_time;
        if (isset($booked_slots[$slot_key])) {
            $error = 'This time slot is already booked. Please select another time.';
        } else {
            // Check if the selected day is available
            $day_of_week = strtolower(date('l', strtotime($appointment_date)));
            $available_days = explode(',', $doctor['available_days']);
            
            // Convert available days to lowercase and trim whitespace for comparison
            $available_days_lower = array_map('strtolower', array_map('trim', $available_days));
            
            if (!in_array($day_of_week, $available_days_lower)) {
                $error = 'Doctor is not available on ' . ucfirst($day_of_week) . '. Please select another date.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Generate appointment number
                    $appointment_number = 'APT-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Insert appointment
                    $appointment_sql = "
                        INSERT INTO appointments (appointment_number, patient_id, doctor_id, appointment_date, appointment_time, status, reason_for_visit, created_at)
                        VALUES (?, ?, ?, ?, ?, 'scheduled', ?, NOW())
                    ";
                    $db->executeQuery($appointment_sql, [$appointment_number, $patient_id, $doctor_id, $appointment_date, $appointment_time, $reason]);
                    $appointment_id = $db->lastInsertId();
                    
                    // Generate bill number
                    $bill_number = 'BILL-' . date('Y') . '-' . str_pad($appointment_id, 6, '0', STR_PAD_LEFT);
                    
                    // Calculate due date (7 days after appointment)
                    $due_date = date('Y-m-d', strtotime($appointment_date . ' +7 days'));
                    
                    // Create bill
                    $bill_sql = "
                        INSERT INTO bills (
                            bill_number, appointment_id, patient_id, 
                            consultation_fee, additional_charges, discount_amount, total_amount,
                            payment_status, due_date, created_at
                        ) VALUES (?, ?, ?, ?, 0.00, 0.00, ?, 'pending', ?, NOW())
                    ";
                    $db->executeQuery($bill_sql, [
                        $bill_number, 
                        $appointment_id, 
                        $patient_id, 
                        $doctor['consultation_fee'],
                        $doctor['consultation_fee'],
                        $due_date
                    ]);
                    
                    $db->commit();
                    
                    $success = 'Appointment booked successfully! Your bill number is ' . $bill_number;
                    
                    // Redirect after 3 seconds
                    header("refresh:3;url=appointments.php");
                    
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Failed to book appointment. Please try again.';
                    error_log("Booking error: " . $e->getMessage());
                }
            }
        }
    }
}

// Generate available time slots
$available_days_array = explode(',', $doctor['available_days']);
// Create lowercase version for JavaScript
$available_days_lower = array_map('strtolower', array_map('trim', $available_days_array));
$start_time = strtotime($doctor['start_time']);
$end_time = strtotime($doctor['end_time']);
$time_slots = [];

while ($start_time < $end_time) {
    $time_slots[] = date('H:i:s', $start_time);
    $start_time = strtotime('+30 minutes', $start_time); // 30-minute slots
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Hospital Management</title>
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
                    <a href="find-doctors.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-search mr-1"></i>Find Doctors
                    </a>
                    <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <a href="find-doctors.php" class="text-blue-600 hover:text-blue-800 mb-4 inline-block">
                <i class="fas fa-arrow-left mr-2"></i>Back to Find Doctors
            </a>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Book Appointment</h1>
            <p class="text-gray-600">Schedule your appointment with the doctor</p>
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
                <p class="mt-2 text-sm">Redirecting to your appointments...</p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Doctor Information -->
            <div class="lg:col-span-1">
                <div class="card sticky top-6">
                    <div class="px-6 py-4 border-b bg-blue-50">
                        <h3 class="text-lg font-semibold text-gray-800">Doctor Information</h3>
                    </div>
                    <div class="p-6">
                        <div class="text-center mb-4">
                            <div class="medical-icon bg-blue-100 text-blue-600 mx-auto mb-3">
                                <i class="fas fa-user-md text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">
                                Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </h3>
                            <p class="text-blue-600 font-medium"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($doctor['department_name']); ?></p>
                        </div>

                        <div class="space-y-3 border-t pt-4">
                            <div class="flex items-center text-sm">
                                <i class="fas fa-graduation-cap text-gray-500 w-5"></i>
                                <span class="text-gray-700"><?php echo $doctor['experience_years']; ?> years experience</span>
                            </div>
                            
                            <div class="flex items-center text-sm">
                                <i class="fas fa-star text-yellow-500 w-5"></i>
                                <span class="text-gray-700">Rating: <?php echo number_format($doctor['rating'], 1); ?>/5.0</span>
                            </div>
                            
                            <div class="flex items-center text-sm">
                                <i class="fas fa-dollar-sign text-green-500 w-5"></i>
                                <span class="text-gray-700">Fee: ৳<?php echo number_format($doctor['consultation_fee'], 2); ?></span>
                            </div>
                            
                            <div class="flex items-center text-sm">
                                <i class="fas fa-clock text-blue-500 w-5"></i>
                                <span class="text-gray-700">
                                    <?php echo date('g:i A', strtotime($doctor['start_time'])); ?> - 
                                    <?php echo date('g:i A', strtotime($doctor['end_time'])); ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($doctor['bio']): ?>
                            <div class="mt-4 pt-4 border-t">
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($doctor['bio']); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4 pt-4 border-t">
                            <p class="text-sm font-medium text-gray-700 mb-2">Available Days:</p>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($available_days_array as $day): ?>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">
                                        <?php echo ucfirst(trim($day)); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Booking Form -->
            <div class="lg:col-span-2">
                <form method="POST" id="bookingForm" class="space-y-6">
                    <!-- Appointment Details -->
                    <div class="card">
                        <div class="px-6 py-4 border-b">
                            <h3 class="text-lg font-semibold text-gray-800">Appointment Details</h3>
                        </div>
                        <div class="p-6 space-y-6">
                            <div>
                                <label class="form-label">Appointment Date *</label>
                                <input type="date" 
                                       name="appointment_date" 
                                       id="appointmentDate"
                                       required 
                                       class="form-input" 
                                       min="<?php echo date('Y-m-d'); ?>"
                                       value="<?php echo htmlspecialchars($_POST['appointment_date'] ?? ''); ?>">
                                <p class="text-xs text-gray-500 mt-1">
                                    Available on: <?php echo implode(', ', array_map('ucfirst', $available_days_array)); ?>
                                </p>
                            </div>

                            <div>
                                <label class="form-label">Appointment Time *</label>
                                <select name="appointment_time" 
                                        id="appointmentTime"
                                        required 
                                        class="form-input">
                                    <option value="">Select time slot</option>
                                    <?php foreach ($time_slots as $slot): ?>
                                        <option value="<?php echo $slot; ?>" 
                                                <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == $slot) ? 'selected' : ''; ?>>
                                            <?php echo date('g:i A', strtotime($slot)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-500 mt-1" id="slotAvailability"></p>
                            </div>

                            <div>
                                <label class="form-label">Reason for Visit *</label>
                                <textarea name="reason" 
                                          required 
                                          rows="4" 
                                          class="form-textarea" 
                                          placeholder="Please describe your symptoms or reason for consultation"><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                                <p class="text-xs text-gray-500 mt-1">This helps the doctor prepare for your visit</p>
                            </div>
                        </div>
                    </div>

                    <!-- Billing Information -->
                    <div class="card">
                        <div class="px-6 py-4 border-b">
                            <h3 class="text-lg font-semibold text-gray-800">Billing Summary</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Consultation Fee:</span>
                                    <span class="font-semibold text-gray-800">৳<?php echo number_format($doctor['consultation_fee'], 2); ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Additional Charges:</span>
                                    <span class="font-semibold text-gray-800">৳0.00</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Discount:</span>
                                    <span class="font-semibold text-green-600">-৳0.00</span>
                                </div>
                                <div class="border-t pt-3 flex justify-between">
                                    <span class="font-bold text-gray-800">Total Amount:</span>
                                    <span class="font-bold text-blue-600 text-xl">৳<?php echo number_format($doctor['consultation_fee'], 2); ?></span>
                                </div>
                            </div>
                            
                            <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                                <p class="text-sm text-blue-800">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    A bill will be generated after booking. Payment is due within 7 days of your appointment date.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="card">
                        <div class="p-6">
                            <label class="flex items-start space-x-3 cursor-pointer">
                                <input type="checkbox" required class="form-checkbox rounded text-blue-600 mt-1">
                                <span class="text-sm text-gray-700">
                                    I agree to the terms and conditions. I understand that I need to arrive 15 minutes before my appointment time and cancellations must be made at least 24 hours in advance.
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex justify-between items-center">
                        <a href="find-doctors.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left mr-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-calendar-check mr-2"></i>Confirm Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Available days for the doctor (lowercase for comparison)
        const availableDays = <?php echo json_encode($available_days_lower); ?>;
        const bookedSlots = <?php echo json_encode($booked_slots); ?>;
        
        // Date validation
        document.getElementById('appointmentDate').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const dayName = selectedDate.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
            
            if (!availableDays.includes(dayName)) {
                alert('Doctor is not available on ' + dayName.charAt(0).toUpperCase() + dayName.slice(1) + '. Please select another date.');
                this.value = '';
            }
            
            checkSlotAvailability();
        });
        
        // Time slot availability check
        document.getElementById('appointmentTime').addEventListener('change', checkSlotAvailability);
        
        function checkSlotAvailability() {
            const date = document.getElementById('appointmentDate').value;
            const time = document.getElementById('appointmentTime').value;
            const availabilityText = document.getElementById('slotAvailability');
            
            if (date && time) {
                const slotKey = date + '_' + time;
                if (bookedSlots[slotKey]) {
                    availabilityText.innerHTML = '<span class="text-red-600"><i class="fas fa-times-circle"></i> This slot is already booked</span>';
                    document.getElementById('appointmentTime').value = '';
                } else {
                    availabilityText.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle"></i> This slot is available</span>';
                }
            }
        }
        
        // Form submission confirmation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const date = document.getElementById('appointmentDate').value;
            const time = document.getElementById('appointmentTime').value;
            const formattedDate = new Date(date).toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            const formattedTime = new Date('1970-01-01T' + time).toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: 'numeric', 
                hour12: true 
            });
            
            if (!confirm('Confirm booking for ' + formattedDate + ' at ' + formattedTime + '?')) {
                e.preventDefault();
            }
        });

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>