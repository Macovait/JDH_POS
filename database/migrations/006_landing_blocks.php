<?php
/**
 * Landing Page Blocks Migration
 * Creates pos_landing_blocks table for editable landing page content.
 * Run: php scripts/migrate.php --migration=006_landing_blocks
 */

require_once __DIR__ . '/../../src/db.php';

class LandingBlocksMigration
{
    private PDO $db;
    private string $prefix;

    public function __construct(PDO $db = null)
    {
        $this->db = $db ?? get_db_connection();
        $this->prefix = 'pos_';
    }

    public function up(): bool
    {
        try {
            $this->db->beginTransaction();

            $this->createLandingBlocksTable();
            $this->createIndexes();
            $this->seedDefaultData();

            $this->db->commit();
            echo "Landing blocks migration completed successfully!\n";
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Landing Blocks Migration Error: " . $e->getMessage());
            echo "Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function createLandingBlocksTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}landing_blocks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            section VARCHAR(50) NOT NULL COMMENT 'trust_strip,how_it_works,products,ai_slides,testimonials,faq,cta,features,about',
            block_key VARCHAR(100) NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            subtitle VARCHAR(500) DEFAULT NULL,
            content TEXT DEFAULT NULL,
            image_url VARCHAR(500) DEFAULT NULL,
            icon_class VARCHAR(100) DEFAULT NULL,
            sort_order INT DEFAULT 0,
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_block (section, block_key),
            INDEX idx_section_sort (section, sort_order),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    private function createIndexes(): void
    {
        // Already created inline, but kept here for clarity
    }

    private function seedDefaultData(): void
    {
        $blocks = [
            // Trust Strip
            ['trust_strip', 'mpesa',       null, null, null, null, 'fas fa-mobile-alt',  1, 1],
            ['trust_strip', 'visa',        null, null, null, null, 'fab fa-cc-visa',     2, 1],
            ['trust_strip', 'mastercard',  null, null, null, null, 'fab fa-cc-mastercard', 3, 1],
            ['trust_strip', 'odpc',        null, null, null, null, 'fas fa-shield-alt',  4, 1],
            ['trust_strip', 'kra',         null, null, null, null, 'fas fa-landmark',    5, 1],
            ['trust_strip', 'ssl',         null, null, null, null, 'fas fa-lock',        6, 1],

            // How It Works
            ['how_it_works', 'step_1', 'Sign Up Free', null, 'Create your account in under 60 seconds. No credit card required, no commitments.', null, null, 1, 1],
            ['how_it_works', 'step_2', 'Set Up Your Store', null, 'Add your products, configure payment methods, and customize your settings with guided setup.', null, null, 2, 1],
            ['how_it_works', 'step_3', 'Start Selling', null, 'Process your first sale, track inventory, and watch your business insights grow in real time.', null, null, 3, 1],

            // Products Tabs
            ['products', 'pos', 'Sell Anywhere, Anytime', null, 'Sell offline seamlessly and continue serving customers without internet. Enjoy fast checkout, mobile payment options (M-Pesa, cards), multi-terminal access, and seamless inventory sync.', null, 'fas fa-cash-register', 1, 1],
            ['products', 'erp', 'Simplify Business with Unified ERP', null, 'Manage accounting, sales, purchasing, inventory, and daily operations in one connected platform — powered by AI to automate workflows, generate real-time insights, and enable faster decisions.', null, 'fas fa-boxes-stacked', 2, 1],
            ['products', 'ecommerce', 'Launch Online, Grow Without Limits', null, 'Connect to social channels, marketplaces, and POS in one place. Enable WhatsApp ordering, in-store pickup, and real-time data sync across all your sales channels.', null, 'fas fa-store', 3, 1],

            // AI Slides
            ['ai_slides', 'ai_smart_ordering', 'AI Smart Ordering', null, 'Create orders automatically from text or images, cutting manual entry and speeding up your entire ordering process. The AI understands product names, quantities, and even handwritten notes.', null, 'fas fa-wand-magic-sparkles', 1, 1],
            ['ai_slides', 'ai_smart_insights', 'AI Smart Insights', null, 'Turn data into action with AI — analyze trends, detect anomalies, and get intelligent suggestions to optimize decisions and operations. Know what\'s selling, what\'s not, and what to stock next.', null, 'fas fa-brain', 2, 1],
            ['ai_slides', 'predictive_analytics', 'Predictive Analytics', null, 'Forecast demand, identify seasonal patterns, and optimize stock levels automatically. Never overstock or run out of popular items again with intelligent predictions.', null, 'fas fa-chart-line', 3, 1],

            // Testimonials
            ['testimonials', 'amina', null, null, 'We switched to our POS and our checkout time dropped by 40%. The offline mode is a lifesaver — we never lose a sale even when internet is down.', null, null, 1, 1],
            ['testimonials', 'james', null, null, 'The inventory management alone saved us hours every week. Now we know exactly what to reorder and when. Game changer for our retail chain.', null, null, 2, 1],
            ['testimonials', 'sarah', null, null, 'From manual spreadsheets to automated reports in one day. The AI insights helped us identify our best-selling products and double down on them.', null, null, 3, 1],

            // FAQ
            ['faq', 'what_is', 'What is JDH POS?', 'JDH POS is an all-in-one business management platform designed for African retailers and wholesalers. It combines point-of-sale, inventory management, accounting, e-commerce, and AI-powered insights into a single, easy-to-use system.', null, null, 1, 1],
            ['faq', 'pricing', 'How does pricing work?', 'We offer flexible plans starting from a free tier for small businesses, up to enterprise plans for large operations. All paid plans include a free trial period. You can upgrade or downgrade anytime.', null, null, 2, 1],
            ['faq', 'offline', 'Does it work offline?', 'Yes! Our POS works fully offline. Sales are queued locally and sync automatically when connectivity returns. You never miss a sale due to network issues.', null, null, 3, 1],
            ['faq', 'payments', 'What payment methods are supported?', 'We support M-Pesa, card payments (Visa/Mastercard), bank transfers, cash, and mobile money across multiple African markets. Integration is seamless and reconciliation is automatic.', null, null, 4, 1],
            ['faq', 'support', 'What kind of support do you offer?', 'We provide 24/7 support via live chat, WhatsApp, email, and phone. Enterprise customers get a dedicated account manager and priority support.', null, null, 5, 1],
            ['faq', 'data_security', 'Is my business data secure?', 'Absolutely. We are registered with Kenya\'s Office of the Data Protection Commissioner (ODPC) as both a Data Controller and Data Processor. All data is encrypted in transit and at rest.', null, null, 6, 1],

            // Features
            ['features', 'reports', 'Make Smarter Decisions', 'Access detailed analytics on sales trends, stock levels, and product performance. Identify best-sellers and slow-moving items to drive stronger business decisions.', null, 'fas fa-chart-pie', 1, 1],
            ['features', 'inventory', 'Manage & Track Inventory', 'Keep full visibility of every item across stores and warehouses. Know what\'s in stock, selling fast, and needs replenishment.', null, 'fas fa-boxes-stacked', 2, 1],
            ['features', 'invoices', 'Send Better Invoices', 'Create branded invoices, accept multi-currency payments, and let customers view and pay online anywhere — with WhatsApp and email reminders.', null, 'fas fa-file-invoice-dollar', 3, 1],
            ['features', 'selling', 'Automate & Accelerate Selling', 'Serve customers faster with a POS that works offline and supports multiple devices and payment methods — including cash, cards, M-Pesa, MTN, and more.', null, 'fas fa-cash-register', 4, 1],
            ['features', 'accounting', 'Simple Integrated Accounting', 'Track expenses, revenue, and profit in real time. Generate financial reports, balance sheets, and tax-ready summaries with one click.', null, 'fas fa-calculator', 5, 1],
            ['features', 'funding', 'Flexible Business Funding', 'As your activity on our platform increases, you unlock access to business loans designed to support expansion and cash-flow needs.', null, 'fas fa-hand-holding-dollar', 6, 1],

            // CTA
            ['cta', 'main', 'Ready to Transform Your Business?', 'Join thousands of African businesses already using our platform to streamline operations and accelerate growth.', null, null, 1, 1],

            // About section title
            ['about', 'section_title', 'Built for African Businesses', 'From Nairobi to Lagos, we understand the unique challenges of running a business in Africa. Our platform is designed with local payment methods, compliance requirements, and business practices in mind.', null, null, 1, 1],
        ];

        $stmt = $this->db->prepare("INSERT INTO {$this->prefix}landing_blocks
            (section, block_key, title, subtitle, content, image_url, icon_class, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                subtitle = VALUES(subtitle),
                content = VALUES(content),
                image_url = VALUES(image_url),
                icon_class = VALUES(icon_class),
                sort_order = VALUES(sort_order),
                is_active = VALUES(is_active)");

        foreach ($blocks as $b) {
            $stmt->execute($b);
        }

        echo "Seeded " . count($blocks) . " default landing blocks.\n";
    }
}

// CLI runner
$migration = new LandingBlocksMigration();
$migration->up();
