# Security Best Practices

This document outlines security best practices for the Jakababa POS SaaS system. Following these guidelines will help protect your application from common security vulnerabilities.

## Table of Contents

1. [Authentication Security](#authentication-security)
2. [Session Management](#session-management)
3. [CSRF Protection](#csrf-protection)
4. [SQL Injection Prevention](#sql-injection-prevention)
5. [XSS Prevention](#xss-prevention)
6. [Multi-Tenant Isolation](#multi-tenant-isolation)
7. [AJAX Security](#ajax-security)
8. [Password Security](#password-security)
9. [Rate Limiting](#rate-limiting)
10. [Logging and Monitoring](#logging-and-monitoring)

---

## Authentication Security

### 1. Use Separate Authentication for Admin and Tenant

The system uses two separate authentication mechanisms:

- **Admin Authentication**: Uses `admins` table with `requireAdmin()` middleware
- **Tenant Authentication**: Uses `users` table with `requireTenant()` middleware

**Why?** This separation ensures that:

- Admin credentials are never exposed to tenant users
- Tenant users cannot access admin functionality
- Each authentication system can be secured independently

### 2. Always Use Middleware Functions

```php
<?php
// GOOD: Use middleware functions
require_once __DIR__ . '/../../src/middleware.php';
requireTenant();

// BAD: Don't check authentication manually
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
?>
```

### 3. Validate User Status on Every Request

The middleware functions automatically validate:

- User exists in database
- User status is 'active'
- Company status is 'active' or 'trial'
- Session fingerprint matches

---

## Session Management

### 1. Regenerate Session ID After Login

Always regenerate session ID after successful login to prevent session fixation attacks:

```php
<?php
// After successful password verification
session_regenerate_id(true);

// Set session fingerprint
$_SESSION['fingerprint'] = md5(
    get_client_ip() .
    ($_SERVER['HTTP_USER_AGENT'] ?? '') .
    session_id()
);
?>
```

### 2. Use Session Fingerprinting

Session fingerprinting helps detect session hijacking:

```php
<?php
// Set fingerprint on login
$_SESSION['fingerprint'] = md5(
    get_client_ip() .
    ($_SERVER['HTTP_USER_AGENT'] ?? '') .
    session_id()
);

// Validate fingerprint on each request
if (!validate_session_fingerprint()) {
    session_destroy();
    header('Location: /login.php?error=session_invalid');
    exit;
}
?>
```

### 3. Set Secure Session Parameters

```php
<?php
session_name('jakababa_saas_sid');

$cookieParams = [
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,      // Prevent JavaScript access
    'samesite' => 'Lax',     // CSRF protection
    'secure' => true         // Only send over HTTPS (in production)
];

session_set_cookie_params($cookieParams);
session_start();
?>
```

### 4. Implement Session Timeout

```php
<?php
// Check if session has expired
if (is_session_expired(1800)) { // 30 minutes
    session_destroy();
    header('Location: /login.php?error=session_expired');
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();
?>
```

---

## CSRF Protection

### 1. Always Use CSRF Tokens for Forms

```php
<?php
// Generate CSRF token
$csrf_token = csrf_token();

// Include in form
<form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
    <!-- other form fields -->
</form>
?>
```

### 2. Verify CSRF Token on Submission

```php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Process form
    }
}
?>
```

### 3. Use Different CSRF Tokens for Different Forms

```php
<?php
// Generate token for specific form
$csrf_token = csrf_token('login_form');

// Verify specific form token
if (!verify_csrf_token($_POST['csrf_token'], 'login_form')) {
    // Invalid token
}
?>
```

### 4. CSRF Tokens Expire Automatically

CSRF tokens are automatically cleaned up after 1 hour. You can also manually clean expired tokens:

```php
<?php
clean_expired_csrf_tokens();
?>
```

---

## SQL Injection Prevention

### 1. Always Use Prepared Statements

```php
<?php
// GOOD: Use prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND company_id = ?");
$stmt->execute([$user_id, $company_id]);
$user = $stmt->fetch();

// BAD: Don't concatenate user input
$sql = "SELECT * FROM users WHERE id = " . $_GET['id']; // Dangerous!
?>
```

### 2. Use Parameterized Queries with db*fetch*\* Functions

```php
<?php
// GOOD: Use parameterized queries
$user = db_fetch_one(
    "SELECT * FROM users WHERE username = ? AND company_id = ?",
    [$username, $company_id]
);

// BAD: Don't use string concatenation
$user = db_fetch_one(
    "SELECT * FROM users WHERE username = '$username'" // Dangerous!
);
?>
```

### 3. Validate and Sanitize Input

```php
<?php
// Sanitize input
$username = trim($_POST['username'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$age = filter_var($_POST['age'] ?? '', FILTER_VALIDATE_INT);

// Validate input
if (empty($username)) {
    $error = 'Username is required';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Invalid email address';
}
?>
```

---

## XSS Prevention

### 1. Always Escape Output

```php
<?php
// GOOD: Escape output
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');

// BAD: Don't output raw user input
echo $user_input; // Dangerous!
?>
```

### 2. Use htmlspecialchars() for HTML Output

```php
<?php
// GOOD: Escape HTML
<p><?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?></p>

// BAD: Don't output raw HTML
<p><?= $user_name ?></p> // Dangerous!
?>
```

### 3. Use json_encode() for JavaScript Output

```php
<?php
// GOOD: Encode JSON
<script>
    var userData = <?= json_encode($user_data) ?>;
</script>

// BAD: Don't output raw data
<script>
    var userData = "<?= $user_data ?>"; // Dangerous!
</script>
?>
```

### 4. Set Content Security Policy (CSP)

```php
<?php
// Set CSP header
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
?>
```

---

## Multi-Tenant Isolation

### 1. Always Validate Company Context

```php
<?php
// Get company ID from session
$company_id = get_current_company_id();

if (!$company_id) {
    header('Location: /public/auth/login.php');
    exit;
}

// Use company_id in all queries
$products = db_fetch_all(
    "SELECT * FROM products WHERE company_id = ? AND deleted_at IS NULL",
    [$company_id]
);
?>
```

### 2. Never Trust User Input for Company ID

```php
<?php
// GOOD: Use company ID from session
$company_id = get_current_company_id();

// BAD: Don't trust user input
$company_id = $_GET['company_id']; // Dangerous!
?>
```

### 3. Use Company Filter Condition

```php
<?php
// Use company filter condition in queries
$condition = company_filter_condition('p');
$products = db_fetch_all(
    "SELECT * FROM products p WHERE {$condition} AND p.deleted_at IS NULL"
);
?>
```

### 4. Validate AJAX Company Access

```php
<?php
// Validate company_id in AJAX requests
$company_id = $_POST['company_id'] ?? null;
validate_ajax_company($company_id);
?>
```

---

## AJAX Security

### 1. Always Require AJAX Authentication

```php
<?php
// Require AJAX authentication
require_ajax_auth();

// For admin AJAX endpoints
require_ajax_auth(true);
?>
```

### 2. Validate CSRF Token for AJAX POST Requests

```php
<?php
// Validate CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
require_ajax_csrf($csrf_token);
?>
```

### 3. Validate Request Method

```php
<?php
// Validate request method
validate_ajax_method('POST');
?>
```

### 4. Use Rate Limiting

```php
<?php
// Rate limiting: 60 requests per minute
ajax_rate_limit('action_name', 60, 60);
?>
```

### 5. Return Proper JSON Responses

```php
<?php
// Success response
json_success($data, 'Operation successful');

// Error response
json_error('Operation failed', 400, 'OPERATION_FAILED');
?>
```

---

## Password Security

### 1. Always Use password_hash() for Passwords

```php
<?php
// GOOD: Use password_hash()
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// BAD: Don't use MD5 or SHA1
$password_hash = md5($password); // Dangerous!
?>
```

### 2. Always Use password_verify() for Verification

```php
<?php
// GOOD: Use password_verify()
if (password_verify($password, $user['password_hash'])) {
    // Password is correct
}

// BAD: Don't compare hashes directly
if ($password_hash === $user['password_hash']) { // Dangerous!
    // This is vulnerable to timing attacks
}
?>
```

### 3. Implement Account Lockout

```php
<?php
// Check if account is locked
if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
    $error = 'Account is locked. Please try again later.';
}

// Lock account after 5 failed attempts
if ($failed_attempts >= 5) {
    $lock_until = date('Y-m-d H:i:s', time() + 900); // 15 minutes
    db_query(
        "UPDATE admins SET locked_until = ? WHERE id = ?",
        [$lock_until, $admin['id']]
    );
}
?>
```

### 4. Enforce Strong Passwords

```php
<?php
function validate_password_strength($password): bool
{
    // At least 8 characters
    if (strlen($password) < 8) {
        return false;
    }

    // At least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }

    // At least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }

    // At least one number
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }

    // At least one special character
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }

    return true;
}
?>
```

---

## Rate Limiting

### 1. Implement Rate Limiting for Login Attempts

```php
<?php
// Rate limiting for login
$ip = get_client_ip();
$key = "login_attempts_{$ip}";

if (!isset($_SESSION[$key])) {
    $_SESSION[$key] = [
        'count' => 0,
        'window_start' => time()
    ];
}

$rate_data = $_SESSION[$key];

// Reset window after 15 minutes
if ((time() - $rate_data['window_start']) > 900) {
    $_SESSION[$key] = [
        'count' => 1,
        'window_start' => time()
    ];
} else {
    // Check if limit exceeded (5 attempts per 15 minutes)
    if ($rate_data['count'] >= 5) {
        $error = 'Too many login attempts. Please try again later.';
    } else {
        $_SESSION[$key]['count']++;
    }
}
?>
```

### 2. Implement Rate Limiting for AJAX Endpoints

```php
<?php
// Rate limiting for AJAX endpoints
ajax_rate_limit('get_products', 100, 60); // 100 requests per minute
?>
```

---

## Logging and Monitoring

### 1. Log All Authentication Attempts

```php
<?php
// Log successful login
log_login_attempt($username, true, $user_id, $company_id);

// Log failed login
log_login_attempt($username, false, null, $company_id);
?>
```

### 2. Log Security Events

```php
<?php
// Log session hijacking attempt
error_log("Session hijacking detected for user ID: {$user_id}");

// Log permission denied
error_log("Permission denied: User {$user_id} tried to access {$permission}");

// Log CSRF token validation failure
error_log("CSRF token validation failed for user ID: {$user_id}");
?>
```

### 3. Log Activity for Audit Trail

```php
<?php
// Log user activity
log_activity(
    'user.login',
    'User logged in',
    ['username' => $username, 'ip' => get_client_ip()],
    $user_id,
    $company_id
);

// Log admin activity
log_activity(
    'admin.company_created',
    'Admin created new company',
    ['company_name' => $company_name],
    $admin_id,
    null
);
?>
```

### 4. Monitor Failed Login Attempts

```php
<?php
// Check for suspicious activity
$failed_attempts = db_fetch_one("
    SELECT COUNT(*) as count
    FROM login_attempts
    WHERE ip_address = ?
    AND success = 0
    AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
", [get_client_ip()]);

if ($failed_attempts['count'] > 10) {
    // Alert administrator
    error_log("Suspicious activity detected from IP: " . get_client_ip());
}
?>
```

---

## Additional Security Measures

### 1. Use HTTPS in Production

```php
<?php
// Force HTTPS in production
if ($_SERVER['HTTPS'] !== 'on') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}
?>
```

### 2. Set Security Headers

```php
<?php
// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
```

### 3. Disable Error Display in Production

```php
<?php
// In production, disable error display
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
?>
```

### 4. Validate File Uploads

```php
<?php
// Validate file uploads
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_size = 5 * 1024 * 1024; // 5MB

if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error = 'File upload failed';
}

if (!in_array($_FILES['file']['type'], $allowed_types)) {
    $error = 'Invalid file type';
}

if ($_FILES['file']['size'] > $max_size) {
    $error = 'File too large';
}

// Generate unique filename
$filename = uniqid() . '_' . basename($_FILES['file']['name']);
$filepath = '/uploads/' . $filename;

move_uploaded_file($_FILES['file']['tmp_name'], $filepath);
?>
```

---

## Security Checklist

### Authentication

- [ ] Use separate authentication for admin and tenant
- [ ] Always use middleware functions
- [ ] Validate user status on every request
- [ ] Implement account lockout after failed attempts

### Session Management

- [ ] Regenerate session ID after login
- [ ] Use session fingerprinting
- [ ] Set secure session parameters
- [ ] Implement session timeout

### CSRF Protection

- [ ] Use CSRF tokens for all forms
- [ ] Verify CSRF token on submission
- [ ] Use different CSRF tokens for different forms
- [ ] CSRF tokens expire automatically

### SQL Injection Prevention

- [ ] Always use prepared statements
- [ ] Use parameterized queries
- [ ] Validate and sanitize input
- [ ] Never concatenate user input into SQL

### XSS Prevention

- [ ] Always escape output
- [ ] Use htmlspecialchars() for HTML
- [ ] Use json_encode() for JavaScript
- [ ] Set Content Security Policy

### Multi-Tenant Isolation

- [ ] Always validate company context
- [ ] Never trust user input for company ID
- [ ] Use company filter condition
- [ ] Validate AJAX company access

### AJAX Security

- [ ] Always require AJAX authentication
- [ ] Validate CSRF token for POST requests
- [ ] Validate request method
- [ ] Use rate limiting

### Password Security

- [ ] Use password_hash() for passwords
- [ ] Use password_verify() for verification
- [ ] Implement account lockout
- [ ] Enforce strong passwords

### Rate Limiting

- [ ] Implement rate limiting for login attempts
- [ ] Implement rate limiting for AJAX endpoints
- [ ] Monitor for suspicious activity

### Logging and Monitoring

- [ ] Log all authentication attempts
- [ ] Log security events
- [ ] Log activity for audit trail
- [ ] Monitor failed login attempts

---

## Common Security Vulnerabilities

### 1. SQL Injection

**Vulnerable Code:**

```php
<?php
$user_id = $_GET['id'];
$user = db_fetch_one("SELECT * FROM users WHERE id = $user_id");
?>
```

**Secure Code:**

```php
<?php
$user_id = $_GET['id'];
$user = db_fetch_one("SELECT * FROM users WHERE id = ?", [$user_id]);
?>
```

### 2. Cross-Site Scripting (XSS)

**Vulnerable Code:**

```php
<?php
echo $user_input;
?>
```

**Secure Code:**

```php
<?php
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');
?>
```

### 3. Cross-Site Request Forgery (CSRF)

**Vulnerable Code:**

```php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form without CSRF validation
}
?>
```

**Secure Code:**

```php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        // Process form
    }
}
?>
```

### 4. Session Fixation

**Vulnerable Code:**

```php
<?php
session_start();
// Don't regenerate session ID after login
?>
```

**Secure Code:**

```php
<?php
session_start();
// Regenerate session ID after login
session_regenerate_id(true);
?>
```

### 5. Session Hijacking

**Vulnerable Code:**

```php
<?php
session_start();
// Don't validate session fingerprint
?>
```

**Secure Code:**

```php
<?php
session_start();
// Validate session fingerprint
if (!validate_session_fingerprint()) {
    session_destroy();
    header('Location: /login.php?error=session_invalid');
    exit;
}
?>
```

---

## Production Security Checklist

Before deploying to production, ensure:

- [ ] All passwords are hashed with password_hash()
- [ ] All SQL queries use prepared statements
- [ ] All output is escaped with htmlspecialchars()
- [ ] All forms have CSRF protection
- [ ] All AJAX endpoints have authentication
- [ ] Session ID is regenerated after login
- [ ] Session fingerprinting is enabled
- [ ] HTTPS is enforced
- [ ] Security headers are set
- [ ] Error display is disabled
- [ ] Error logging is enabled
- [ ] Rate limiting is implemented
- [ ] Account lockout is implemented
- [ ] File uploads are validated
- [ ] Multi-tenant isolation is enforced
- [ ] Admin and tenant authentication are separate
- [ ] All routes use middleware functions
- [ ] Activity logging is enabled
- [ ] Failed login attempts are monitored

---

## Summary

Security is a critical aspect of any SaaS application. By following these best practices, you can significantly reduce the risk of security vulnerabilities:

1. **Always use middleware functions** for authentication and authorization
2. **Always use prepared statements** for database queries
3. **Always escape output** to prevent XSS attacks
4. **Always use CSRF protection** for forms
5. **Always validate company context** for multi-tenant isolation
6. **Always regenerate session ID** after login
7. **Always log security events** for monitoring and auditing

Remember: Security is not a one-time task, but an ongoing process. Regularly review and update your security measures to stay protected against new threats.
