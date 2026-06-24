<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Apps Access - SaaS POS System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 600px;
            width: 90%;
            text-align: center;
        }
        .logo {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        .access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .access-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .access-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        .access-card.new {
            position: relative;
        }
        .access-card.new::before {
            content: 'NEW';
            position: absolute;
            top: -10px;
            right: -10px;
            background: #ff6b6b;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .card-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            display: block;
        }
        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        .card-desc {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #666;
            font-size: 0.9rem;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">📱</div>
        <h1>Mobile Apps Access</h1>
        <p class="subtitle">Access your mobile POS ecosystem - no iframes, no security issues</p>

        <div class="access-grid">
            <a href="mobile_clean.php" class="access-card new" target="_blank">
                <span class="card-icon">📱</span>
                <div class="card-title">Mobile POS</div>
                <div class="card-desc">Clean, reliable mobile POS with cart, checkout, inventory sync, and customer management.</div>
            </a>

            <a href="mobile_final_test.php" class="access-card" target="_blank">
                <span class="card-icon">🧪</span>
                <div class="card-title">System Test</div>
                <div class="card-desc">Run comprehensive tests to verify all mobile POS features are working.</div>
            </a>

            <a href="mobile_status.php" class="access-card" target="_blank">
                <span class="card-icon">📊</span>
                <div class="card-title">System Status</div>
                <div class="card-desc">Check API keys, database tables, and overall mobile ecosystem health.</div>
            </a>

            <a href="mobile_test.php" class="access-card" target="_blank">
                <span class="card-icon">🧪</span>
                <div class="card-title">API Tester</div>
                <div class="card-desc">Interactive API testing interface for all mobile endpoints.</div>
            </a>
        </div>

        <div class="footer">
            <p><strong>Quick Start:</strong> Click "Mobile POS" to start using your mobile interface immediately!</p>
            <p><strong>For Developers:</strong> Use "API Tester" to test endpoints before building mobile apps.</p>
            <a href="index.php" class="back-link">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>