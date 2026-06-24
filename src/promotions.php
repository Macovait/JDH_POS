<?php
/**
 * Advanced Promotions Engine for Jakababa POS
 * Supports: BOGO, bundles, tiered pricing, time-based deals, 
 *           buy-X-get-Y, category discounts, customer group pricing
 *
 * @package Jakababa
 * @subpackage Promotions
 * @version 3.0
 */

if (defined('PROMOTIONS_LOADED')) {
    return;
}
define('PROMOTIONS_LOADED', true);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cache.php';

// =============================================================================
// Promotion Rule Types
// =============================================================================

class PromotionType
{
    const PERCENTAGE = 'percentage';
    const FIXED_AMOUNT = 'fixed_amount';
    const BOGO = 'buy_one_get_one';
    const BOGO_PERCENT = 'buy_one_get_one_percent';
    const BUY_X_GET_Y = 'buy_x_get_y';
    const BUNDLE = 'bundle';
    const TIERED = 'tiered';
    const TIME_BASED = 'time_based';
    const CATEGORY = 'category_discount';
    const CUSTOMER_GROUP = 'customer_group';
    const QUANTITY_DISCOUNT = 'quantity_discount';
    const FIRST_PURCHASE = 'first_purchase';
    const SPEND_THRESHOLD = 'spend_threshold';
}

// =============================================================================
// Promotion Rule
// =============================================================================

class PromotionRule
{
    public int $id;
    public string $name;
    public string $type;
    public array $conditions;
    public array $actions;
    public ?string $validFrom;
    public ?string $validUntil;
    public ?array $validDays;        // ['monday', 'tuesday', ...]
    public ?string $validTimeFrom;   // '09:00'
    public ?string $validTimeUntil;  // '17:00'
    public int $priority;
    public bool $exclusive;          // Can't combine with other promos
    public int $maxUses;
    public int $currentUses;
    public ?array $applicableProducts;
    public ?array $applicableCategories;
    public ?array $applicableCustomerGroups;
    public float $minPurchaseAmount;
    public int $minQuantity;
    public int $maxDiscountQuantity;
    public bool $active;
    public ?int $branchId;
    public ?int $companyId;

    public function __construct(array $data = [])
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->name = $data['name'] ?? '';
        $this->type = $data['type'] ?? PromotionType::PERCENTAGE;
        $this->conditions = json_decode($data['conditions'] ?? '{}', true) ?: [];
        $this->actions = json_decode($data['actions'] ?? '{}', true) ?: [];
        $this->validFrom = $data['valid_from'] ?? null;
        $this->validUntil = $data['valid_until'] ?? null;
        $this->validDays = json_decode($data['valid_days'] ?? 'null', true);
        $this->validTimeFrom = $data['valid_time_from'] ?? null;
        $this->validTimeUntil = $data['valid_time_until'] ?? null;
        $this->priority = (int) ($data['priority'] ?? 0);
        $this->exclusive = (bool) ($data['exclusive'] ?? false);
        $this->maxUses = (int) ($data['max_uses'] ?? 0);
        $this->currentUses = (int) ($data['current_uses'] ?? 0);
        $this->applicableProducts = json_decode($data['applicable_products'] ?? 'null', true);
        $this->applicableCategories = json_decode($data['applicable_categories'] ?? 'null', true);
        $this->applicableCustomerGroups = json_decode($data['applicable_customer_groups'] ?? 'null', true);
        $this->minPurchaseAmount = (float) ($data['min_purchase_amount'] ?? 0);
        $this->minQuantity = (int) ($data['min_quantity'] ?? 0);
        $this->maxDiscountQuantity = (int) ($data['max_discount_quantity'] ?? 0);
        $this->active = (bool) ($data['active'] ?? true);
        $this->branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        $this->companyId = isset($data['tenant_id']) ? (int) $data['tenant_id'] : null;
    }

    /**
     * Check if this promotion is currently valid
     */
    public function isValid(): bool
    {
        if (!$this->active) return false;

        // Check date range
        $now = new DateTime();
        if ($this->validFrom && $now < new DateTime($this->validFrom)) return false;
        if ($this->validUntil && $now > new DateTime($this->validUntil)) return false;

        // Check day of week
        if ($this->validDays && !empty($this->validDays)) {
            $today = strtolower($now->format('l'));
            if (!in_array($today, $this->validDays)) return false;
        }

        // Check time of day
        if ($this->validTimeFrom && $this->validTimeUntil) {
            $currentTime = $now->format('H:i');
            if ($currentTime < $this->validTimeFrom || $currentTime > $this->validTimeUntil) {
                return false;
            }
        }

        // Check max uses
        if ($this->maxUses > 0 && $this->currentUses >= $this->maxUses) return false;

        return true;
    }

    /**
     * Check if this promotion applies to a specific product
     */
    public function appliesToProduct(array $product): bool
    {
        // Check product ID
        if ($this->applicableProducts && !empty($this->applicableProducts)) {
            if (!in_array($product['id'], $this->applicableProducts)) {
                return false;
            }
        }

        // Check category
        if ($this->applicableCategories && !empty($this->applicableCategories)) {
            if (!in_array($product['category_id'] ?? 0, $this->applicableCategories)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if this promotion applies to a customer
     */
    public function appliesToCustomer(?array $customer): bool
    {
        if (!$this->applicableCustomerGroups || empty($this->applicableCustomerGroups)) {
            return true;
        }

        if (!$customer) return false;

        $group = $customer['customer_group'] ?? 'regular';
        return in_array($group, $this->applicableCustomerGroups);
    }
}

// =============================================================================
// Applied Discount Result
// =============================================================================

class AppliedDiscount
{
    public int $promotionId;
    public string $promotionName;
    public string $type;
    public float $discountAmount;
    public string $discountType;   // 'fixed' or 'percent'
    public ?int $productId;
    public ?string $description;
    public array $freeItems;       // For BOGO promotions

    public function __construct(array $data = [])
    {
        $this->promotionId = (int) ($data['promotion_id'] ?? 0);
        $this->promotionName = $data['promotion_name'] ?? '';
        $this->type = $data['type'] ?? '';
        $this->discountAmount = (float) ($data['discount_amount'] ?? 0);
        $this->discountType = $data['discount_type'] ?? 'fixed';
        $this->productId = isset($data['product_id']) ? (int) $data['product_id'] : null;
        $this->description = $data['description'] ?? null;
        $this->freeItems = $data['free_items'] ?? [];
    }

    public function toArray(): array
    {
        return [
            'promotion_id' => $this->promotionId,
            'promotion_name' => $this->promotionName,
            'type' => $this->type,
            'discount_amount' => $this->discountAmount,
            'discount_type' => $this->discountType,
            'product_id' => $this->productId,
            'description' => $this->description,
            'free_items' => $this->freeItems
        ];
    }
}

// =============================================================================
// Promotions Engine
// =============================================================================

class PromotionsEngine
{
    private ?int $companyId;
    private ?int $branchId;
    private array $rules = [];
    private array $appliedPromotions = [];
    private float $totalDiscount = 0;

    public function __construct(?int $companyId = null, ?int $branchId = null)
    {
        $this->companyId = $companyId ?? (function_exists('get_current_tenant_id') ? get_current_tenant_id() : null);
        $this->branchId = $branchId ?? (function_exists('get_current_branch_id') ? get_current_branch_id() : null);
        $this->loadRules();
    }

    /**
     * Load promotion rules from database (with caching)
     */
    private function loadRules(): void
    {
        $cacheKey = cache()->promotionKey($this->companyId);
        $cached = cache_get($cacheKey);

        if ($cached !== null) {
            $this->rules = array_map(fn($r) => new PromotionRule($r), $cached);
            return;
        }

        try {
            $sql = "SELECT * FROM promotions 
                    WHERE active = 1 
                    AND (tenant_id = ? OR tenant_id IS NULL)
                    AND (branch_id = ? OR branch_id IS NULL)
                    AND (valid_until IS NULL OR valid_until >= CURDATE())
                    AND (valid_from IS NULL OR valid_from <= CURDATE())
                    ORDER BY priority DESC, id ASC";
            $rows = db_fetch_all($sql, [$this->companyId, $this->branchId]);

            $this->rules = [];
            $toCache = [];
            foreach ($rows as $row) {
                $rule = new PromotionRule($row);
                if ($rule->isValid()) {
                    $this->rules[] = $rule;
                    $toCache[] = $row;
                }
            }

            // Cache for 5 minutes
            cache_set($cacheKey, $toCache, 300);
        } catch (Exception $e) {
            error_log("Failed to load promotion rules: " . $e->getMessage());
        }
    }

    /**
     * Apply all applicable promotions to a cart
     *
     * @param array $cartItems  Array of ['product_id', 'name', 'price', 'quantity', 'category_id']
     * @param array|null $customer  Customer data
     * @return array  ['discounts' => AppliedDiscount[], 'total_discount' => float, 'cart' => array]
     */
    public function applyPromotions(array $cartItems, ?array $customer = null): array
    {
        $this->appliedPromotions = [];
        $this->totalDiscount = 0;
        $modifiedCart = $cartItems;
        $subtotal = $this->calculateSubtotal($cartItems);

        // Sort rules by priority (already sorted in loadRules)
        foreach ($this->rules as $rule) {
            if (!$rule->isValid()) continue;
            if (!$rule->appliesToCustomer($customer)) continue;

            // Check minimum purchase
            if ($subtotal < $rule->minPurchaseAmount) continue;

            switch ($rule->type) {
                case PromotionType::PERCENTAGE:
                    $this->applyPercentageDiscount($rule, $modifiedCart, $subtotal);
                    break;

                case PromotionType::FIXED_AMOUNT:
                    $this->applyFixedDiscount($rule, $modifiedCart, $subtotal);
                    break;

                case PromotionType::BOGO:
                    $this->applyBOGO($rule, $modifiedCart);
                    break;

                case PromotionType::BOGO_PERCENT:
                    $this->applyBOGOPercent($rule, $modifiedCart);
                    break;

                case PromotionType::BUY_X_GET_Y:
                    $this->applyBuyXGetY($rule, $modifiedCart);
                    break;

                case PromotionType::BUNDLE:
                    $this->applyBundle($rule, $modifiedCart);
                    break;

                case PromotionType::TIERED:
                    $this->applyTiered($rule, $modifiedCart, $subtotal);
                    break;

                case PromotionType::TIME_BASED:
                    $this->applyTimeBased($rule, $modifiedCart, $subtotal);
                    break;

                case PromotionType::CATEGORY:
                    $this->applyCategoryDiscount($rule, $modifiedCart);
                    break;

                case PromotionType::QUANTITY_DISCOUNT:
                    $this->applyQuantityDiscount($rule, $modifiedCart);
                    break;

                case PromotionType::SPEND_THRESHOLD:
                    $this->applySpendThreshold($rule, $modifiedCart, $subtotal);
                    break;
            }

            // If exclusive, stop after first applied
            if ($rule->exclusive && !empty($this->appliedPromotions)) {
                break;
            }
        }

        return [
            'discounts' => array_map(fn($d) => $d->toArray(), $this->appliedPromotions),
            'total_discount' => $this->totalDiscount,
            'cart' => $modifiedCart
        ];
    }

    // =========================================================================
    // Discount Type Implementations
    // =========================================================================

    private function applyPercentageDiscount(PromotionRule $rule, array &$cart, float $subtotal): void
    {
        $percent = (float) ($rule->actions['percent'] ?? 0);
        if ($percent <= 0) return;

        $applicableTotal = 0;
        $applicableItems = [];

        foreach ($cart as $i => $item) {
            if ($rule->appliesToProduct($item)) {
                $lineTotal = $item['price'] * $item['quantity'];
                $applicableTotal += $lineTotal;
                $applicableItems[] = $i;
            }
        }

        if ($applicableTotal <= 0) return;

        // Limit quantity if specified
        if ($rule->maxDiscountQuantity > 0) {
            $qtyCount = 0;
            $limitedTotal = 0;
            foreach ($applicableItems as $i) {
                $item = $cart[$i];
                $remaining = $rule->maxDiscountQuantity - $qtyCount;
                if ($remaining <= 0) break;
                $useQty = min($item['quantity'], $remaining);
                $limitedTotal += $item['price'] * $useQty;
                $qtyCount += $useQty;
            }
            $applicableTotal = $limitedTotal;
        }

        $discount = round($applicableTotal * ($percent / 100), 2);

        $this->addDiscount(new AppliedDiscount([
            'promotion_id' => $rule->id,
            'promotion_name' => $rule->name,
            'type' => $rule->type,
            'discount_amount' => $discount,
            'discount_type' => 'percent',
            'description' => "{$percent}% off"
        ]));
    }

    private function applyFixedDiscount(PromotionRule $rule, array &$cart, float $subtotal): void
    {
        $amount = (float) ($rule->actions['amount'] ?? 0);
        if ($amount <= 0) return;

        $discount = min($amount, $subtotal);

        $this->addDiscount(new AppliedDiscount([
            'promotion_id' => $rule->id,
            'promotion_name' => $rule->name,
            'type' => $rule->type,
            'discount_amount' => $discount,
            'discount_type' => 'fixed',
            'description' => "KSh {$amount} off"
        ]));
    }

    private function applyBOGO(PromotionRule $rule, array &$cart): void
    {
        $buyQty = (int) ($rule->conditions['buy_quantity'] ?? 1);
        $getQty = (int) ($rule->actions['get_quantity'] ?? 1);
        $discountPercent = (float) ($rule->actions['discount_percent'] ?? 100); // 100 = free

        foreach ($cart as &$item) {
            if (!$rule->appliesToProduct($item)) continue;

            $sets = floor($item['quantity'] / ($buyQty + $getQty));
            if ($sets <= 0) continue;

            $freeQty = $sets * $getQty;
            $freeValue = round($item['price'] * $freeQty * ($discountPercent / 100), 2);

            if ($freeValue > 0) {
                $item['_promo_free_qty'] = ($item['_promo_free_qty'] ?? 0) + $freeQty;

                $this->addDiscount(new AppliedDiscount([
                    'promotion_id' => $rule->id,
                    'promotion_name' => $rule->name,
                    'type' => $rule->type,
                    'discount_amount' => $freeValue,
                    'discount_type' => 'fixed',
                    'product_id' => $item['id'],
                    'description' => "Buy {$buyQty} Get {$getQty}" . ($discountPercent == 100 ? ' FREE' : " {$discountPercent}% off"),
                    'free_items' => [['product_id' => $item['id'], 'quantity' => $freeQty]]
                ]));
            }
        }
        unset($item);
    }

    private function applyBOGOPercent(PromotionRule $rule, array &$cart): void
    {
        // BOGO with percentage discount (e.g., buy 1 get 1 at 50% off)
        $rule->actions['discount_percent'] = $rule->actions['discount_percent'] ?? 50;
        $this->applyBOGO($rule, $cart);
    }

    private function applyBuyXGetY(PromotionRule $rule, array &$cart): void
    {
        // Buy product X, get product Y free/discounted
        $buyProductId = (int) ($rule->conditions['buy_product_id'] ?? 0);
        $getProductId = (int) ($rule->actions['get_product_id'] ?? 0);
        $buyQty = (int) ($rule->conditions['buy_quantity'] ?? 1);
        $discountPercent = (float) ($rule->actions['discount_percent'] ?? 100);

        if (!$buyProductId || !$getProductId) return;

        $buyCount = 0;
        $getItem = null;

        foreach ($cart as $item) {
            if ($item['id'] == $buyProductId) {
                $buyCount += $item['quantity'];
            }
            if ($item['id'] == $getProductId) {
                $getItem = &$item;
            }
        }

        if ($buyCount < $buyQty || !$getItem) return;

        $sets = floor($buyCount / $buyQty);
        $freeQty = min($sets, $getItem['quantity']);
        $freeValue = round($getItem['price'] * $freeQty * ($discountPercent / 100), 2);

        if ($freeValue > 0) {
            $this->addDiscount(new AppliedDiscount([
                'promotion_id' => $rule->id,
                'promotion_name' => $rule->name,
                'type' => $rule->type,
                'discount_amount' => $freeValue,
                'discount_type' => 'fixed',
                'product_id' => $getProductId,
                'description' => "Buy {$buyQty}, get {$getProductId} at {$discountPercent}% off"
            ]));
        }
    }

    private function applyBundle(PromotionRule $rule, array &$cart): void
    {
        // Bundle: specific products together at a special price
        $bundleProducts = $rule->conditions['product_ids'] ?? [];
        $bundlePrice = (float) ($rule->actions['bundle_price'] ?? 0);
        $bundleDiscount = (float) ($rule->actions['discount_amount'] ?? 0);

        if (empty($bundleProducts)) return;

        // Check if all bundle products are in cart
        $bundleCount = PHP_INT_MAX;
        $regularTotal = 0;

        foreach ($bundleProducts as $pid) {
            $found = false;
            foreach ($cart as $item) {
                if ($item['id'] == $pid) {
                    $bundleCount = min($bundleCount, $item['quantity']);
                    $regularTotal += $item['price'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $bundleCount = 0;
                break;
            }
        }

        if ($bundleCount <= 0) return;

        if ($bundlePrice > 0) {
            $discount = round(($regularTotal - $bundlePrice) * $bundleCount, 2);
        } else {
            $discount = round($bundleDiscount * $bundleCount, 2);
        }

        if ($discount > 0) {
            $this->addDiscount(new AppliedDiscount([
                'promotion_id' => $rule->id,
                'promotion_name' => $rule->name,
                'type' => $rule->type,
                'discount_amount' => $discount,
                'discount_type' => 'fixed',
                'description' => "Bundle discount"
            ]));
        }
    }

    private function applyTiered(PromotionRule $rule, array &$cart, float $subtotal): void
    {
        // Tiered: discount increases with quantity or spend
        $tiers = $rule->actions['tiers'] ?? [];
        if (empty($tiers)) return;

        // Sort tiers by threshold descending
        usort($tiers, fn($a, $b) => ($b['threshold'] ?? 0) <=> ($a['threshold'] ?? 0));

        $applicableTotal = 0;
        foreach ($cart as $item) {
            if ($rule->appliesToProduct($item)) {
                $applicableTotal += $item['price'] * $item['quantity'];
            }
        }

        foreach ($tiers as $tier) {
            $threshold = (float) ($tier['threshold'] ?? 0);
            if ($applicableTotal >= $threshold) {
                $discountType = $tier['type'] ?? 'percent';
                $value = (float) ($tier['value'] ?? 0);

                if ($discountType === 'percent') {
                    $discount = round($applicableTotal * ($value / 100), 2);
                } else {
                    $discount = min($value, $applicableTotal);
                }

                if ($discount > 0) {
                    $this->addDiscount(new AppliedDiscount([
                        'promotion_id' => $rule->id,
                        'promotion_name' => $rule->name,
                        'type' => $rule->type,
                        'discount_amount' => $discount,
                        'discount_type' => $discountType,
                        'description' => "Tier discount: {$value}" . ($discountType === 'percent' ? '%' : ' off')
                    ]));
                }
                break;
            }
        }
    }

    private function applyTimeBased(PromotionRule $rule, array &$cart, float $subtotal): void
    {
        // Time-based flash sale / happy hour
        $discountPercent = (float) ($rule->actions['percent'] ?? 0);
        $discountAmount = (float) ($rule->actions['amount'] ?? 0);

        if ($discountPercent > 0) {
            $this->applyPercentageDiscount($rule, $cart, $subtotal);
        } elseif ($discountAmount > 0) {
            $this->applyFixedDiscount($rule, $cart, $subtotal);
        }
    }

    private function applyCategoryDiscount(PromotionRule $rule, array &$cart): void
    {
        $discountPercent = (float) ($rule->actions['percent'] ?? 0);
        if ($discountPercent <= 0) return;

        $applicableTotal = 0;
        foreach ($cart as $item) {
            if ($rule->appliesToProduct($item)) {
                $applicableTotal += $item['price'] * $item['quantity'];
            }
        }

        if ($applicableTotal <= 0) return;

        $discount = round($applicableTotal * ($discountPercent / 100), 2);

        $this->addDiscount(new AppliedDiscount([
            'promotion_id' => $rule->id,
            'promotion_name' => $rule->name,
            'type' => $rule->type,
            'discount_amount' => $discount,
            'discount_type' => 'percent',
            'description' => "Category discount: {$discountPercent}%"
        ]));
    }

    private function applyQuantityDiscount(PromotionRule $rule, array &$cart): void
    {
        // Buy more, save more (per-product quantity tiers)
        $qtyTiers = $rule->actions['quantity_tiers'] ?? [];
        if (empty($qtyTiers)) return;

        // Sort by quantity descending
        usort($qtyTiers, fn($a, $b) => ($b['quantity'] ?? 0) <=> ($a['quantity'] ?? 0));

        foreach ($cart as &$item) {
            if (!$rule->appliesToProduct($item)) continue;

            foreach ($qtyTiers as $tier) {
                if ($item['quantity'] >= (int) ($tier['quantity'] ?? 0)) {
                    $tierPrice = (float) ($tier['price'] ?? $item['price']);
                    $discount = round(($item['price'] - $tierPrice) * $item['quantity'], 2);

                    if ($discount > 0) {
                        $item['_tier_price'] = $tierPrice;

                        $this->addDiscount(new AppliedDiscount([
                            'promotion_id' => $rule->id,
                            'promotion_name' => $rule->name,
                            'type' => $rule->type,
                            'discount_amount' => $discount,
                            'discount_type' => 'fixed',
                            'product_id' => $item['id'],
                            'description' => "Qty {$tier['quantity']}+: {$tierPrice} each"
                        ]));
                    }
                    break;
                }
            }
        }
        unset($item);
    }

    private function applySpendThreshold(PromotionRule $rule, array &$cart, float $subtotal): void
    {
        // Spend X, get Y discount (e.g., spend 5000, get 500 off)
        $threshold = (float) ($rule->conditions['spend_amount'] ?? 0);
        $discountType = $rule->actions['type'] ?? 'fixed';
        $discountValue = (float) ($rule->actions['value'] ?? 0);

        if ($subtotal < $threshold || $discountValue <= 0) return;

        if ($discountType === 'percent') {
            $discount = round($subtotal * ($discountValue / 100), 2);
        } else {
            $discount = min($discountValue, $subtotal);
        }

        $this->addDiscount(new AppliedDiscount([
            'promotion_id' => $rule->id,
            'promotion_name' => $rule->name,
            'type' => $rule->type,
            'discount_amount' => $discount,
            'discount_type' => $discountType,
            'description' => "Spend {$threshold}, save {$discountValue}"
        ]));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function calculateSubtotal(array $cart): float
    {
        $total = 0;
        foreach ($cart as $item) {
            $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        }
        return $total;
    }

    private function addDiscount(AppliedDiscount $discount): void
    {
        $this->appliedPromotions[] = $discount;
        $this->totalDiscount += $discount->discountAmount;
    }

    /**
     * Get all active promotions for display
     */
    public function getActivePromotions(): array
    {
        return array_map(fn($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'type' => $r->type,
            'description' => $this->describePromotion($r),
            'valid_until' => $r->validUntil,
            'exclusive' => $r->exclusive
        ], $this->rules);
    }

    private function describePromotion(PromotionRule $rule): string
    {
        switch ($rule->type) {
            case PromotionType::PERCENTAGE:
                $pct = $rule->actions['percent'] ?? 0;
                return "{$pct}% off on selected items";

            case PromotionType::FIXED_AMOUNT:
                $amt = $rule->actions['amount'] ?? 0;
                return "KSh {$amt} off your purchase";

            case PromotionType::BOGO:
                $buy = $rule->conditions['buy_quantity'] ?? 1;
                $get = $rule->actions['get_quantity'] ?? 1;
                return "Buy {$buy}, Get {$get} FREE";

            case PromotionType::BUNDLE:
                return "Special bundle price";

            case PromotionType::TIERED:
                return "Buy more, save more";

            case PromotionType::TIME_BASED:
                return "Limited time offer";

            case PromotionType::SPEND_THRESHOLD:
                $spend = $rule->conditions['spend_amount'] ?? 0;
                $save = $rule->actions['value'] ?? 0;
                return "Spend {$spend}, save {$save}";

            default:
                return $rule->name;
        }
    }

    /**
     * Invalidate promotion cache
     */
    public static function invalidateCache(?int $companyId = null): void
    {
        cache_forget(cache()->promotionKey($companyId));
    }
}

// =============================================================================
// SQL Schema for promotions table (run once)
// =============================================================================

function getPromotionsSchema(): string
{
    return "
    CREATE TABLE IF NOT EXISTS promotions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT,
        branch_id INT,
        name VARCHAR(200) NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'percentage',
        conditions JSON,
        actions JSON,
        valid_from DATE NULL,
        valid_until DATE NULL,
        valid_days JSON NULL,
        valid_time_from TIME NULL,
        valid_time_until TIME NULL,
        priority INT DEFAULT 0,
        exclusive TINYINT(1) DEFAULT 0,
        max_uses INT DEFAULT 0,
        current_uses INT DEFAULT 0,
        applicable_products JSON NULL,
        applicable_categories JSON NULL,
        applicable_customer_groups JSON NULL,
        min_purchase_amount DECIMAL(10,2) DEFAULT 0,
        min_quantity INT DEFAULT 0,
        max_discount_quantity INT DEFAULT 0,
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_company (tenant_id),
        INDEX idx_active_dates (active, valid_from, valid_until),
        INDEX idx_priority (priority DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
}

// =============================================================================
// Global Helper
// =============================================================================

if (!function_exists('apply_promotions')) {
    /**
     * Apply promotions to a cart
     *
     * @param array $cartItems
     * @param array|null $customer
     * @return array
     */
    function apply_promotions(array $cartItems, ?array $customer = null): array
    {
        $engine = new PromotionsEngine();
        return $engine->applyPromotions($cartItems, $customer);
    }
}
