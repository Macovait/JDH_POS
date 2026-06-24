<?php
/**
 * Mobile POS Access - Direct Link
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>🚀 Mobile POS - Ready to Use</title>
    <meta http-equiv='refresh' content='0; url=mobile_pos.php'>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .container { max-width: 600px; margin: 0 auto; background: rgba(255,255,255,0.1); padding: 40px; border-radius: 20px; }
        h1 { font-size: 2.5rem; margin-bottom: 20px; }
        p { font-size: 1.2rem; margin-bottom: 30px; }
        .loading { font-size: 1.5rem; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🚀 Mobile POS</h1>
        <p>Your full-featured mobile POS is loading...</p>
        <div class='loading'>⏳</div>
        <p style='margin-top: 30px; font-size: 1rem; opacity: 0.8;'>If this doesn't redirect automatically, <a href='mobile_pos.php' style='color: #fff; text-decoration: underline;'>click here</a>.</p>
    </div>
</body>
</html>";
?>