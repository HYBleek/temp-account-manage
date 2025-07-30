<?php
/**
 * Registration System Functions
 * Handles all backend logic for user registration
 * Need a CVS file for recording
 */

require_once 'php/config.php';

/**
 * Send email notifications
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $txt Email body text
 * @return bool Success status
 */
function sendmail($to, $subject, $txt) {
    $headers = 'From: labwebserver@university.edu' . "\r\n" .
               'Reply-To: labwebserver@university.edu' . "\r\n" .
               'X-Mailer: PHP/' . phpversion();
    return mail($to, $subject, $txt, $headers);
}

/**
 * Check if user exists in CSV file
 * @param string $email Email to check
 * @param string $filename CSV filename
 * @return bool True if user exists
 */
function userExists($email, $filename = "Users.csv") {
    if (!file_exists($filename)) {
        return false;
    }
    
    $users = array_map('str_getcsv', file($filename));
    foreach ($users as $user) {
        if (isset($user[0]) && $user[0] === $email) {
            return true;
        }
    }
    return false;
}

/**
 * Add new user to Users.csv
 * @param string $email User email
 * @param string $username User full name
 * @return bool Success status
 */
function addUser($email, $username) {
    $timestamp = date('Y-m-d H:i:s');
    $daysSinceLastLogin = 0;
    $accessLevel = 'user'; // Default access level
    $accountStatus = 'new'; // Mark as new user
    
    // CSV format: email, username, access_level, account_status, registration_date, days_since_last_login
    $info = array($email, $username, $accessLevel, $accountStatus, $timestamp, $daysSinceLastLogin);
    
    // Append to Users.csv
    $file = fopen("Users.csv", "a");
    if ($file) {
        fputcsv($file, $info);
        fclose($file);
        return true;
    }
    return false;
}

/**
 * Send welcome email to new user
 * @param string $email User email
 * @param string $username User name
 * @return bool Success status
 */
function sendWelcomeEmail($email, $username) {
    $subject = "Welcome to Lab Webserver";
    $txt = "Hello " . $username . ",\n\n" .
           "Welcome to the Lab webserver! Your account has been created successfully.\n\n" .
           "You can now sign in using your institutional account (Microsoft or Google) at:\n" .
           SITE_URL . "/login.php\n\n" .
           "Please use the same email address (" . $email . ") that you registered with.\n\n" .
           "If you have any questions, please contact our support team.\n\n" .
           "-Lab at University";
    
    return sendmail($email, $subject, $txt);
}

/**
 * Process registration form submission
 * @param array $postData POST data from form
 * @param string $sessionToken Session CSRF token
 * @return array Result array with 'success', 'message', and 'error' keys
 */
function processRegistration($postData, $sessionToken) {
    $result = array(
        'success' => false,
        'message' => '',
        'error' => ''
    );
    
    // Verify CSRF token
    if (!isset($postData['csrf_token']) || $postData['csrf_token'] !== $sessionToken) {
        $result['error'] = "Invalid security token. Please refresh and try again.";
        return $result;
    }
    
    // Sanitize inputs
    $email = filter_var($postData['email'], FILTER_SANITIZE_EMAIL);
    $username = filter_var($postData['username'], FILTER_SANITIZE_STRING);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result['error'] = "Invalid email format.";
        return $result;
    }
    
    // Check if user already exists
    if (userExists($email)) {
        $result['error'] = "An account with this email already exists.";
        return $result;
    }
    
    // Add user to database
    if (addUser($email, $username)) {
        // Send welcome email
        sendWelcomeEmail($email, $username);
        
        $result['success'] = true;
        $result['message'] = "Registration successful! You can now sign in using your Microsoft or Google account.";
    } else {
        $result['error'] = "Failed to create account. Please try again.";
    }
    
    return $result;
}

/**
 * Generate CSRF token for session
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get user statistics from Users.csv
 * @return array Statistics array
 */
function getUserStatistics() {
    $stats = array(
        'total_users' => 0,
        'new_users' => 0,
        'active_users' => 0
    );
    
    if (file_exists("Users.csv")) {
        $users = array_map('str_getcsv', file("Users.csv"));
        $stats['total_users'] = count($users);
        
        foreach ($users as $user) {
            if (isset($user[3]) && $user[3] === 'new') {
                $stats['new_users']++;
            }
            if (isset($user[5]) && intval($user[5]) < 30) { // Active if logged in within 30 days
                $stats['active_users']++;
            }
        }
    }
    
    return $stats;
}
