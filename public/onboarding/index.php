<?php
/**
 * Tenant Onboarding Wizard
 * Self-service signup for new businesses
 */

require_once __DIR__ . '/../../src/paths.php';

// Don't require login - this is the signup page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    redirect(base_url('dashboard/home.php'));
}

$page_title = 'Get Started - JDH POS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: linear-gradient(135deg, #0b1120 0%, #1e293b 100%);
            min-height: 100vh;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .onboarding-container {
            max-width: 600px;
            width: 100%;
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid #334155;
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(20px);
        }
        .logo-section {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
        }
        h1 {
            font-size: 28px;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #94a3b8;
            font-size: 16px;
        }
        .progress-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 32px;
        }
        .progress-step {
            flex: 1;
            height: 4px;
            background: #334155;
            border-radius: 2px;
            transition: all 0.3s;
        }
        .progress-step.active {
            background: #fbbf24;
        }
        .progress-step.completed {
            background: #10b981;
        }
        .step {
            display: none;
        }
        .step.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #cbd5e1;
            margin-bottom: 8px;
        }
        input, select {
            width: 100%;
            padding: 12px 16px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid #334155;
            border-radius: 10px;
            color: #f8fafc;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.2s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #fbbf24;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.1);
        }
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #0f172a;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
        }
        .btn-secondary {
            background: transparent;
            border: 1px solid #334155;
            color: #94a3b8;
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.05);
            color: #e2e8f0;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .btn-group .btn {
            flex: 1;
        }
        .plan-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 20px 0;
        }
        .plan-card {
            padding: 16px;
            border: 2px solid #334155;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: rgba(15, 23, 42, 0.4);
        }
        .plan-card:hover {
            border-color: #475569;
        }
        .plan-card.selected {
            border-color: #fbbf24;
            background: rgba(251, 191, 36, 0.1);
        }
        .plan-card h3 {
            font-size: 16px;
            color: #f8fafc;
            margin-bottom: 8px;
        }
        .plan-price {
            font-size: 24px;
            font-weight: 700;
            color: #fbbf24;
        }
        .plan-price span {
            font-size: 14px;
            color: #94a3b8;
            font-weight: 400;
        }
        .plan-features {
            margin-top: 12px;
            text-align: left;
        }
        .plan-features li {
            list-style: none;
            font-size: 12px;
            color: #94a3b8;
            padding: 4px 0;
        }
        .plan-features li::before {
            content: '✓';
            color: #10b981;
            margin-right: 6px;
        }
        .business-types {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 16px 0;
        }
        .business-type {
            padding: 16px;
            border: 2px solid #334155;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .business-type:hover {
            border-color: #475569;
        }
        .business-type.selected {
            border-color: #fbbf24;
            background: rgba(251, 191, 36, 0.1);
        }
        .business-type .icon {
            font-size: 32px;
            margin-bottom: 8px;
        }
        .business-type h4 {
            font-size: 14px;
            color: #f8fafc;
        }
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
            display: none;
        }
        .error-message.visible {
            display: block;
        }
        .success-check {
            text-align: center;
            padding: 40px 0;
        }
        .success-check .checkmark {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin-bottom: 24px;
        }
        .helper-text {
            font-size: 13px;
            color: #64748b;
            margin-top: 6px;
        }
        @media (max-width: 480px) {
            .onboarding-container {
                padding: 24px;
            }
            .plan-cards {
                grid-template-columns: 1fr;
            }
            .business-types {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="onboarding-container">
        <div class="logo-section">
            <div class="logo">🚀</div>
            <h1>Welcome to JDH POS</h1>
            <p class="subtitle">Let's get your business set up in minutes</p>
        </div>
        
        <div class="progress-bar">
            <div class="progress-step active" data-step="1"></div>
            <div class="progress-step" data-step="2"></div>
            <div class="progress-step" data-step="3"></div>
            <div class="progress-step" data-step="4"></div>
        </div>
        
        <div id="errorMessage" class="error-message"></div>
        
        <!-- Step 1: Business Info -->
        <div class="step active" data-step="1">
            <h2 style="margin-bottom: 20px; font-size: 20px;">Tell us about your business</h2>
            
            <div class="form-group">
                <label>Business Name</label>
                <input type="text" id="businessName" placeholder="My Awesome Store">
            </div>
            
            <div class="form-group">
                <label>Business Type</label>
                <div class="business-types">
                    <div class="business-type" data-type="retail">
                        <div class="icon">🏪</div>
                        <h4>Retail Store</h4>
                    </div>
                    <div class="business-type" data-type="restaurant">
                        <div class="icon">🍽️</div>
                        <h4>Restaurant</h4>
                    </div>
                    <div class="business-type" data-type="supermarket">
                        <div class="icon">🥑</div>
                        <h4>Supermarket</h4>
                    </div>
                    <div class="business-type" data-type="pharmacy">
                        <div class="icon">💊</div>
                        <h4>Pharmacy</h4>
                    </div>
                    <div class="business-type" data-type="electronics">
                        <div class="icon">📱</div>
                        <h4>Electronics</h4>
                    </div>
                    <div class="business-type" data-type="other">
                        <div class="icon">🏢</div>
                        <h4>Other</h4>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Your Name</label>
                <input type="text" id="ownerName" placeholder="John Doe">
            </div>
            
            <button class="btn btn-primary" onclick="nextStep(2)">
                Continue →
            </button>
        </div>
        
        <!-- Step 2: Account Setup -->
        <div class="step" data-step="2">
            <h2 style="margin-bottom: 20px; font-size: 20px;">Create your account</h2>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" id="email" placeholder="you@example.com">
                <p class="helper-text">This will be your login email</p>
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" id="phone" placeholder="+254 712 345 678">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="password" placeholder="Min 8 characters">
                <p class="helper-text">Must include uppercase, number, and special character</p>
            </div>
            
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" id="confirmPassword" placeholder="Repeat password">
            </div>
            
            <div class="btn-group">
                <button class="btn btn-secondary" onclick="prevStep(1)">← Back</button>
                <button class="btn btn-primary" onclick="nextStep(3)">Continue →</button>
            </div>
        </div>
        
        <!-- Step 3: Choose Plan -->
        <div class="step" data-step="3">
            <h2 style="margin-bottom: 20px; font-size: 20px;">Choose your plan</h2>
            
            <div class="plan-cards">
                <div class="plan-card" data-plan="free">
                    <h3>Free</h3>
                    <div class="plan-price">$0<span>/mo</span></div>
                    <ul class="plan-features">
                        <li>1 User</li>
                        <li>100 Products</li>
                        <li>Basic POS</li>
                        <li>Email Support</li>
                    </ul>
                </div>
                <div class="plan-card selected" data-plan="starter">
                    <h3>Starter</h3>
                    <div class="plan-price">$19<span>/mo</span></div>
                    <ul class="plan-features">
                        <li>3 Users</li>
                        <li>1,000 Products</li>
                        <li>Advanced POS</li>
                        <li>Priority Support</li>
                        <li>SMS Notifications</li>
                    </ul>
                </div>
                <div class="plan-card" data-plan="pro">
                    <h3>Professional</h3>
                    <div class="plan-price">$49<span>/mo</span></div>
                    <ul class="plan-features">
                        <li>Unlimited Users</li>
                        <li>Unlimited Products</li>
                        <li>Multi-branch</li>
                        <li>24/7 Support</li>
                        <li>Advanced Reports</li>
                        <li>API Access</li>
                    </ul>
                </div>
            </div>
            
            <div class="btn-group">
                <button class="btn btn-secondary" onclick="prevStep(2)">← Back</button>
                <button class="btn btn-primary" onclick="submitSignup()">Get Started →</button>
            </div>
        </div>
        
        <!-- Step 4: Success -->
        <div class="step" data-step="4">
            <div class="success-check">
                <div class="checkmark">✓</div>
                <h2 style="font-size: 24px; margin-bottom: 12px;">You're all set!</h2>
                <p style="color: #94a3b8; margin-bottom: 24px;">
                    Your account has been created. Check your email for login details.
                </p>
                <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-primary" style="text-decoration: none;">
                    Go to Login →
                </a>
            </div>
        </div>
    </div>
    
    <script>
        let currentStep = 1;
        let formData = {
            business_type: 'retail',
            plan: 'starter'
        };
        
        // Business type selection
        document.querySelectorAll('.business-type').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.business-type').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                formData.business_type = card.dataset.type;
            });
        });
        
        // Plan selection
        document.querySelectorAll('.plan-card').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                formData.plan = card.dataset.plan;
            });
        });
        
        function nextStep(step) {
            if (!validateStep(currentStep)) return;
            
            collectStepData(currentStep);
            
            document.querySelector(`.step[data-step="${currentStep}"]`).classList.remove('active');
            document.querySelector(`.step[data-step="${step}"]`).classList.add('active');
            
            // Update progress
            document.querySelectorAll('.progress-step').forEach((el, i) => {
                if (i + 1 < step) el.classList.add('completed');
                if (i + 1 === step) el.classList.add('active');
                if (i + 1 > step) el.classList.remove('active', 'completed');
            });
            
            currentStep = step;
        }
        
        function prevStep(step) {
            document.querySelector(`.step[data-step="${currentStep}"]`).classList.remove('active');
            document.querySelector(`.step[data-step="${step}"]`).classList.add('active');
            currentStep = step;
        }
        
        function validateStep(step) {
            const errorEl = document.getElementById('errorMessage');
            errorEl.classList.remove('visible');
            
            if (step === 1) {
                const name = document.getElementById('businessName').value.trim();
                if (!name) {
                    showError('Please enter your business name');
                    return false;
                }
            }
            
            if (step === 2) {
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;
                const confirm = document.getElementById('confirmPassword').value;
                
                if (!email || !email.includes('@')) {
                    showError('Please enter a valid email address');
                    return false;
                }
                if (password.length < 8) {
                    showError('Password must be at least 8 characters');
                    return false;
                }
                if (password !== confirm) {
                    showError('Passwords do not match');
                    return false;
                }
            }
            
            return true;
        }
        
        function collectStepData(step) {
            if (step === 1) {
                formData.business_name = document.getElementById('businessName').value.trim();
                formData.owner_name = document.getElementById('ownerName').value.trim();
            }
            if (step === 2) {
                formData.email = document.getElementById('email').value.trim();
                formData.phone = document.getElementById('phone').value.trim();
                formData.password = document.getElementById('password').value;
            }
        }
        
        function showError(message) {
            const errorEl = document.getElementById('errorMessage');
            errorEl.textContent = message;
            errorEl.classList.add('visible');
        }
        
        async function submitSignup() {
            const btn = document.querySelector('.step.active .btn-primary');
            btn.textContent = 'Creating Account...';
            btn.disabled = true;
            
            try {
                const response = await fetch('<?php echo base_url('ajax/register_tenant.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    nextStep(4);
                } else {
                    showError(data.message || 'Registration failed. Please try again.');
                }
            } catch (error) {
                showError('Network error. Please try again.');
            } finally {
                btn.textContent = 'Get Started →';
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
<?php
// Prevent further execution
exit;
?>
