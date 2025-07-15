<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    require_once 'php/config.php';
    
    // Initialize session for CSRF protection
    session_start();
    
    function sendmail($to, $subject, $txt){
        $headers = 'From: labwebserver@university.edu' . "\r\n" .
        'Reply-To: labwebserver@university.edu' . "\r\n" .
        'X-Mailer: PHP/' . phpversion();
        mail($to,$subject,$txt,$headers);
    }
    
    $message = "";
    $error = "";
    
    // Handle registration form submission
    if(isset($_POST['SubmitButton']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']){ 
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check if email already exists in Users.csv or PendingUsers.csv
            $existingUser = false;
            
            // Check Users.csv
            if (file_exists("Users.csv")) {
                $users = array_map('str_getcsv', file("Users.csv"));
                foreach ($users as $user) {
                    if (isset($user[0]) && $user[0] === $email) {
                        $existingUser = true;
                        break;
                    }
                }
            }
            
            // Check PendingUsers.csv
            if (!$existingUser && file_exists("PendingUsers.csv")) {
                $pendingUsers = array_map('str_getcsv', file("PendingUsers.csv"));
                foreach ($pendingUsers as $user) {
                    if (isset($user[0]) && $user[0] === $email) {
                        $existingUser = true;
                        break;
                    }
                }
            }
            
            if ($existingUser) {
                $error = "An account with this email already exists or is pending approval.";
            } else {
                // Generate secure confirmation token
                $confirmationToken = bin2hex(random_bytes(32));
                
                // Store user data: email, username, token, registration_timestamp, days_since_last_login (starts at 0)
                $timestamp = date('Y-m-d H:i:s');
                $daysSinceLastLogin = 0;
                $info = array($email, $username, $confirmationToken, $timestamp, $daysSinceLastLogin);
                
                // Append to PendingUsers.csv
                $file = fopen("PendingUsers.csv", "a");
                fputcsv($file, $info);
                fclose($file);
                
                // Send confirmation email
                $subject = "Registration Confirmation:  Lab Webserver";
                $txt = "Hello " . $username . ",\n\n" .
                       "Thank you for registering with the  Lab webserver.\n\n" .
                       "Click this link to confirm your registration:\n" .
                       CONFIRM_URL . "?token=" . $confirmationToken . "\n\n" .
                       "After confirmation, you'll be able to sign in using your Microsoft or Google account.\n\n" .
                       "This link will expire in 24 hours.\n\n" .
                       "If you did not request this registration, please ignore this email.\n\n" .
                       "- Lab at university";
                
                sendmail($email, $subject, $txt);
                $message = "Registration successful! Please check your email to confirm your account.";
            }
        }
    }
    
    // Generate CSRF token
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register -  Lab Webserver</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            display: flex;
            min-height: 600px;
        }
        
        .intro-section {
            background: linear-gradient(135deg, #2a5298 0%, #1e3c72 100%);
            color: white;
            padding: 50px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .intro-section h1 {
            font-size: 36px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .intro-section p {
            font-size: 18px;
            line-height: 1.6;
            opacity: 0.9;
            margin-bottom: 30px;
        }
        
        .features {
            list-style: none;
            margin-top: 30px;
        }
        
        .features li {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .features li:before {
            content: "✓";
            margin-right: 10px;
            font-weight: bold;
            font-size: 20px;
        }
        
        .form-section {
            padding: 50px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .form-section h2 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .form-section .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .oauth-info {
            background: #f0f7ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .oauth-info h3 {
            color: #0066cc;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .oauth-info p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .oauth-providers {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .provider-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            color: #555;
        }
        
        .provider-badge svg {
            width: 20px;
            height: 20px;
            margin-right: 8px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #2a5298;
            box-shadow: 0 0 0 3px rgba(42, 82, 152, 0.1);
        }
        
        .form-help {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        .submit-button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #2a5298 0%, #1e3c72 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .submit-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(42, 82, 152, 0.3);
        }
        
        .submit-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .login-link a {
            color: #2a5298;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                min-height: auto;
            }
            
            .intro-section {
                padding: 40px 30px;
            }
            
            .intro-section h1 {
                font-size: 28px;
            }
            
            .form-section {
                padding: 40px 30px;
            }
            
            .oauth-providers {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="intro-section">
            <h1>Welcome to  Lab</h1>
            <p>Join our research community and gain access to cutting-edge computational tools and resources for scientific discovery.</p>
            
            <ul class="features">
                <li>Secure authentication via Microsoft or Google</li>
                <li>Access to exclusive research tools and datasets</li>
                <li>Collaborate with leading researchers worldwide</li>
                <li>Cloud-based computing resources</li>
                <li>Regular updates on latest research findings</li>
            </ul>
        </div>
        
        <div class="form-section">
            <h2>Request Access</h2>
            <p class="subtitle">Register to gain access to  Lab resources</p>
            
            <div class="oauth-info">
                <h3>Secure Authentication</h3>
                <p>We use OAuth2 authentication for maximum security. After your registration is approved, you'll sign in using your institutional account:</p>
                <div class="oauth-providers">
                    <div class="provider-badge">
                        <svg viewBox="0 0 23 23" fill="#0078d4">
                            <path d="M11 11H0V0h11v11zM23 11H12V0h11v11zM11 23H0V12h11v11zM23 23H12V12h11v11z"/>
                        </svg>
                        Microsoft
                    </div>
                    <div class="provider-badge">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Google
                    </div>
                </div>
            </div>
            
            <form action="" method="post" id="registrationForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="username">Full Name</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="John Doe">
                    <div class="form-help">Your name as it will appear in the system</div>
                </div>
                
                <div class="form-group">
                    <label for="email">Institutional Email</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="john.doe@institution.edu"
                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                    <div class="form-help">Use the same email you'll use for OAuth2 authentication</div>
                </div>
                
                <button type="submit" name="SubmitButton" class="submit-button" id="submitBtn">
                    Submit Registration Request
                </button>
            </form>
            
            <?php if($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Sign in with OAuth2</a>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById("registrationForm");
        const email = document.getElementById("email");
        const username = document.getElementById("username");
        
        // Simple form validation
        form.addEventListener("submit", function(event) {
            const emailValue = email.value.trim();
            const usernameValue = username.value.trim();
            
            if (!emailValue || !usernameValue) {
                event.preventDefault();
                alert("Please fill in all fields.");
                return;
            }
            
            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailValue)) {
                event.preventDefault();
                alert("Please enter a valid email address.");
                return;
            }
        });
    </script>
</body>
</html>