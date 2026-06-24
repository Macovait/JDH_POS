<?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
// Jakababa POS - Contact Page
require_once __DIR__ . '/../config/config.php';

// Load brand colors from config (with defaults)
$brandPrimary = getenv('BRAND_PRIMARY') ?: '#1E3A8A';
$brandPrimaryDark = getenv('BRAND_PRIMARY_DARK') ?: '#0F2B5E';
$brandPrimaryLight = getenv('BRAND_PRIMARY_LIGHT') ?: '#3B82F6';
$brandAccent = getenv('BRAND_ACCENT') ?: '#FBBF24';
$brandAccentDark = getenv('BRAND_ACCENT_DARK') ?: '#F59E0B';
$appName = getenv('APP_NAME') ?: 'Jakababa POS';
$appUrl = getenv('APP_URL') ?: 'http://localhost/JDH_POS/public/';
$adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@jakababa.com';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - <?= htmlspecialchars($appName) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        :root {
            --brand-primary:
                <?= $brandPrimary ?>
            ;
            --brand-primary-dark:
                <?= $brandPrimaryDark ?>
            ;
            --brand-primary-light:
                <?= $brandPrimaryLight ?>
            ;
            --brand-accent:
                <?= $brandAccent ?>
            ;
            --brand-accent-dark:
                <?= $brandAccentDark ?>
            ;
            --brand-dark: #101828;
        }

        body {
            background: #f7f9fb;
            color: #111827;
        }

        .nav-link:hover {
            color: var(--brand-primary);
        }

        .brand-btn {
            background: var(--brand-primary);
            color: white;
        }

        .brand-btn:hover {
            background: var(--brand-primary-dark);
        }

        .outline-btn {
            border: 1.5px solid var(--brand-primary);
            color: var(--brand-primary);
        }

        .outline-btn:hover {
            background: var(--brand-primary);
            color: white;
        }

        /* Glassmorphism */
        .glass {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .bg-slate-800/40 border border-slate-700/60 rounded-xl {
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <header class="sticky top-0 glass z-40">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="index.php" class="flex items-center space-x-3">
                    <img src="/images/logo.9c89fc39.png" class="h-10" alt="<?= htmlspecialchars($appName) ?>">
                    <span class="font-bold text-xl"
                        style="color:var(--brand-primary)"><?= htmlspecialchars($appName) ?></span>
                </a>
            </div>
            <nav class="hidden md:flex items-center space-x-6 font-semibold text-gray-700">
                <a class="nav-link" href="index.php">Home</a>
                <a class="nav-link" href="index.php#modules">Modules</a>
                <a class="nav-link" href="index.php#features">Features</a>
                <a class="nav-link" href="pricing.php">Pricing</a>
                <a class="nav-link" href="contact.php" style="color:var(--brand-primary)">Contact</a>
            </nav>
            <div class="hidden md:flex items-center space-x-3">
                <button class="outline-btn px-4 py-2 rounded-lg text-sm font-semibold"
                    onclick="window.location.href='auth/login.php'">Sign In</button>
                <button class="brand-btn px-4 py-2 rounded-lg text-sm font-semibold"
                    onclick="window.location.href='auth/register.php'">Get Started</button>
            </div>
            <button class="md:hidden" onclick="document.getElementById('mobileMenu').classList.toggle('hidden')">
                <svg width="28" height="22" fill="none" stroke="#111" stroke-width="2">
                    <path d="M1 1h26M1 11h26M1 21h26" />
                </svg>
            </button>
        </div>
        <div id="mobileMenu" class="md:hidden hidden border-t glass">
            <div class="px-4 py-3 space-y-2 font-semibold text-gray-700">
                <a class="block" href="index.php">Home</a>
                <a class="block" href="index.php#modules">Modules</a>
                <a class="block" href="index.php#features">Features</a>
                <a class="block" href="pricing.php">Pricing</a>
                <a class="block" href="contact.php" style="color:var(--brand-primary)">Contact</a>
                <div class="pt-2 flex space-x-2">
                    <button class="outline-btn flex-1 px-4 py-2 rounded-lg text-sm font-semibold"
                        onclick="window.location.href='auth/login.php'">Sign In</button>
                    <button class="brand-btn flex-1 px-4 py-2 rounded-lg text-sm font-semibold"
                        onclick="window.location.href='auth/register.php'">Get Started</button>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 text-white py-20">
        <div class="max-w-6xl mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-6">Get in Touch</h1>
            <p class="text-xl text-blue-100 max-w-2xl mx-auto">Have questions? We'd love to hear from you. Send us a
                message and we'll respond as soon as possible.</p>
        </div>
    </section>

    <!-- Contact Form -->
    <section class="max-w-4xl mx-auto px-4 py-16 -mt-10">
        <div class="grid md:grid-cols-2 gap-8">
            <!-- Contact Form -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl rounded-xl p-8">
                <h2 class="text-2xl font-bold mb-6">Send us a Message</h2>
                <form id="contactForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input type="text" name="name" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Your full name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                        <input type="email" name="email" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="your@email.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="tel" name="phone"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="+254 700 000 000">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                        <select name="subject" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select a subject</option>
                            <option value="general">General Inquiry</option>
                            <option value="sales">Sales Question</option>
                            <option value="support">Technical Support</option>
                            <option value="demo">Request a Demo</option>
                            <option value="partnership">Partnership</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
                        <textarea name="message" rows="4" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="How can we help you?"></textarea>
                    </div>
                    <button type="submit" class="brand-btn w-full py-3 rounded-lg font-semibold">
                        Send Message
                    </button>
                </form>
                <div id="formMessage" class="mt-4 hidden"></div>
            </div>

            <!-- Contact Information -->
            <div class="space-y-4">
                <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl rounded-xl p-6">
                    <div class="flex items-start space-x-4">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0"
                            style="background:var(--brand-primary-light)">
                            <i class="fas fa-envelope text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-lg mb-1">Email Us</h3>
                            <p class="text-gray-600"><?= htmlspecialchars($adminEmail) ?></p>
                            <p class="text-sm text-gray-500 mt-1">We'll respond within 24 hours</p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl rounded-xl p-6">
                    <div class="flex items-start space-x-4">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0"
                            style="background:var(--brand-accent)">
                            <i class="fas fa-phone text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-lg mb-1">Call Us</h3>
                            <p class="text-gray-600">+254 700 000 000</p>
                            <p class="text-sm text-gray-500 mt-1">Mon-Fri 9am-6pm EAT</p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl rounded-xl p-6">
                    <div class="flex items-start space-x-4">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0"
                            style="background:var(--brand-primary)">
                            <i class="fas fa-map-marker-alt text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-lg mb-1">Visit Us</h3>
                            <p class="text-gray-600">Nairobi, Kenya</p>
                            <p class="text-sm text-gray-500 mt-1">Available for in-person meetings</p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl rounded-xl p-6">
                    <h3 class="font-semibold text-lg mb-3">Follow Us</h3>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 rounded-full flex items-center justify-center text-white"
                            style="background:var(--brand-primary)">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full flex items-center justify-center text-white"
                            style="background:var(--brand-primary)">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full flex items-center justify-center text-white"
                            style="background:var(--brand-primary)">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full flex items-center justify-center text-white"
                            style="background:var(--brand-primary)">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="max-w-4xl mx-auto px-4 py-16">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold mb-4">Frequently Asked Questions</h2>
            <p class="text-gray-600">Quick answers to common questions</p>
        </div>
        <div class="space-y-4">
            <?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
            $faqs = [
                ['q' => 'How do I get started?', 'a' => 'Simply click "Get Started" to create your free account. You can start using the system immediately with our Free plan.'],
                ['q' => 'Is there a free trial?', 'a' => 'Yes! All paid plans include a 14-day free trial. No credit card required to start.'],
                ['q' => 'Can I change plans later?', 'a' => 'Absolutely. You can upgrade or downgrade your plan at any time. Changes take effect immediately.'],
                ['q' => 'What payment methods do you accept?', 'a' => 'We accept M-Pesa, credit/debit cards, and bank transfers. All payments are processed securely.'],
            ];
            foreach ($faqs as $i => $faq): ?>
                <details class="bg-slate-800/40 border border-slate-700/60 rounded-xl rounded-xl px-4 py-3" <?= $i === 0 ? 'open' : ''; ?>>
                    <summary class="font-semibold cursor-pointer"><?= $faq['q'] ?></summary>
                    <p class="mt-2 text-gray-600"><?= $faq['a'] ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-white border-t py-10">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <img src="/images/logo_kilimax_footer.975e19bf.png" class="h-10"
                        alt="<?= htmlspecialchars($appName) ?>">
                    <span class="font-bold text-lg"
                        style="color:var(--brand-primary)"><?= htmlspecialchars($appName) ?></span>
                </div>
                <div class="flex space-x-4 text-sm text-gray-700">
                    <a href="index.php" class="hover:text-blue-700">Home</a>
                    <a href="pricing.php" class="hover:text-blue-700">Pricing</a>
                    <a href="contact.php" class="hover:text-blue-700">Contact</a>
                </div>
            </div>
            <div class="border-t mt-6 pt-6">
                <p class="text-sm text-gray-500 text-center">&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?>.
                    All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        document.getElementById('contactForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const messageDiv = document.getElementById('formMessage');

            // Show loading state
            messageDiv.innerHTML = '<p class="text-blue-600"><i class="fas fa-spinner fa-spin mr-2"></i>Sending message...</p>';
            messageDiv.classList.remove('hidden');

            // Simulate form submission (replace with actual AJAX call)
            setTimeout(function () {
                messageDiv.innerHTML = '<p class="text-green-600"><i class="fas fa-check-circle mr-2"></i>Thank you! Your message has been sent successfully. We\'ll get back to you soon.</p>';
                document.getElementById('contactForm').reset();
            }, 1500);
        });
    </script>
</body>

</html>