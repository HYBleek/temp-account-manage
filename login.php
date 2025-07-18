<?php
/**
 * Login System Functions
 * Handles OAuth2 authentication with Microsoft/Google
 */

require_once 'php/config.php';

/**
 * OAuth2 Configuration
 * Define your OAuth2 settings in config.php:
 * - MICROSOFT_CLIENT_ID
 * - MICROSOFT_CLIENT_SECRET
 * - GOOGLE_CLIENT_ID
 * - GOOGLE_CLIENT_SECRET
 * - REDIRECT_URI
 */

/**
 * Generate OAuth2 authorization URL for Microsoft
 * @param string $state CSRF protection state
 * @return string Authorization URL
 */
function getMicrosoftAuthUrl($state) {
    $params = array(
        'client_id' => MICROSOFT_CLIENT_ID,
        'response_type' => 'code',
        'redirect_uri' => REDIRECT_URI,
        'response_mode' => 'query',
        'scope' => 'openid profile email User.Read',
        'state' => $state
    );
    
    return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query($params);
}

/**
 * Generate OAuth2 authorization URL for Google
 * @param string $state CSRF protection state
 * @return string Authorization URL
 */
function getGoogleAuthUrl($state) {
    $params = array(
        'client_id' => GOOGLE_CLIENT_ID,
        'response_type' => 'code',
        'redirect_uri' => REDIRECT_URI,
        'scope' => 'openid profile email',
        'state' => $state,
        'access_type' => 'offline',
        'prompt' => 'select_account'
    );
    
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * Exchange authorization code for access token (Microsoft)
 * @param string $code Authorization code
 * @return array|false Token response or false on error
 */
function getMicrosoftAccessToken($code) {
    $tokenUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    
    $params = array(
        'client_id' => MICROSOFT_CLIENT_ID,
        'client_secret' => MICROSOFT_CLIENT_SECRET,
        'code' => $code,
        'redirect_uri' => REDIRECT_URI,
        'grant_type' => 'authorization_code'
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Exchange authorization code for access token (Google)
 * @param string $code Authorization code
 * @return array|false Token response or false on error
 */
function getGoogleAccessToken($code) {
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    
    $params = array(
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'code' => $code,
        'redirect_uri' => REDIRECT_URI,
        'grant_type' => 'authorization_code'
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Get user profile from Microsoft Graph API
 * @param string $accessToken Access token
 * @return array|false User profile or false on error
 */
function getMicrosoftUserProfile($accessToken) {
    $graphUrl = 'https://graph.microsoft.com/v1.0/me';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $graphUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Get user profile from Google API
 * @param string $accessToken Access token
 * @return array|false User profile or false on error
 */
function getGoogleUserProfile($accessToken) {
    $profileUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $profileUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $accessToken
    ));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Check if user is authorized in Users.csv
 * @param string $email User email
 * @return array|false User data or false if not found
 */
function getUserByEmail($email) {
    if (!file_exists("Users.csv")) {
        return false;
    }
    
    $users = array_map('str_getcsv', file("Users.csv"));
    foreach ($users as $user) {
        // CSV format: email, username, access_level, account_status, registration_date, days_since_last_login
        if (isset($user[0]) && strtolower($user[0]) === strtolower($email)) {
            return array(
                'email' => $user[0],
                'username' => $user[1] ?? '',
                'access_level' => $user[2] ?? 'user',
                'account_status' => $user[3] ?? 'active',
                'registration_date' => $user[4] ?? '',
                'days_since_last_login' => $user[5] ?? 0
            );
        }
    }
    return false;
}

/**
 * Update user's last login time
 * @param string $email User email
 * @return bool Success status
 */
function updateLastLogin($email) {
    if (!file_exists("Users.csv")) {
        return false;
    }
    
    $users = array_map('str_getcsv', file("Users.csv"));
    $updated = false;
    
    foreach ($users as $key => $user) {
        if (isset($user[0]) && strtolower($user[0]) === strtolower($email)) {
            // Reset days_since_last_login to 0
            $users[$key][5] = 0;
            
            // Update account status from 'new' to 'active' if needed
            if (isset($users[$key][3]) && $users[$key][3] === 'new') {
                $users[$key][3] = 'active';
            }
            
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        $file = fopen("Users.csv", "w");
        foreach ($users as $user) {
            fputcsv($file, $user);
        }
        fclose($file);
        return true;
    }
    
    return false;
}

/**
 * Create login session
 * @param array $userData User data from CSV
 * @param string $provider OAuth provider (microsoft/google)
 */
function createLoginSession($userData, $provider) {
    $_SESSION['USER'] = $userData['username'];
    $_SESSION['EMAIL'] = $userData['email'];
    $_SESSION['Access_Level'] = $userData['access_level'];
    $_SESSION['loggedIn'] = true;
    $_SESSION['oauth_provider'] = $provider;
    $_SESSION['login_time'] = time();
    
    // Update last login
    updateLastLogin($userData['email']);
}

/**
 * Destroy login session
 */
function logout() {
    session_start();
    session_unset();
    session_destroy();
    
    // Clear session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['loggedIn']) && $_SESSION['loggedIn'] === true;
}

/**
 * Get current user info
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return array(
        'username' => $_SESSION['USER'] ?? '',
        'email' => $_SESSION['EMAIL'] ?? '',
        'access_level' => $_SESSION['Access_Level'] ?? 'user',
        'provider' => $_SESSION['oauth_provider'] ?? ''
    );
}

/**
 * Generate state parameter for CSRF protection
 * @return string
 */
function generateStateParameter() {
    $_SESSION['oauth2state'] = bin2hex(random_bytes(16));
    return $_SESSION['oauth2state'];
}

/**
 * Verify state parameter
 * @param string $state State parameter from callback
 * @return bool
 */
function verifyStateParameter($state) {
    if (!isset($_SESSION['oauth2state']) || $state !== $_SESSION['oauth2state']) {
        return false;
    }
    unset($_SESSION['oauth2state']);
    return true;
}
