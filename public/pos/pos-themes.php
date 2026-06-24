<?php
/**
 * Business Type Themes for Modern POS
 * Returns theme configuration based on business type
 */

function getBusinessTheme(string $businessType): array {
    $themes = [
        'retail' => [
            'name' => 'Retail Store',
            'icon' => 'fa-store',
            'color' => '#3B82F6',
            'colorDark' => '#1D4ED8',
            'gradient' => 'linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%)',
            'bgGradient' => 'linear-gradient(135deg, #0F172A 0%, #1E3A5F 100%)',
            'cardBg' => 'rgba(30, 58, 95, 0.6)',
            'accent' => '#60A5FA',
            'pattern' => 'none',
            'font' => 'Inter',
            'features' => ['quick-sale', 'inventory', 'discounts'],
            'orderTypes' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'color' => '#3B82F6'],
                'online' => ['label' => 'Online Order', 'icon' => 'fa-globe', 'color' => '#10B981'],
                'delivery' => ['label' => 'Delivery', 'icon' => 'fa-truck', 'color' => '#F59E0B']
            ]
        ],
        
        'pharmacy' => [
            'name' => 'Pharmacy',
            'icon' => 'fa-prescription-bottle-medical',
            'color' => '#10B981',
            'colorDark' => '#047857',
            'gradient' => 'linear-gradient(135deg, #10B981 0%, #047857 100%)',
            'bgGradient' => 'linear-gradient(135deg, #0F1A14 0%, #1E3D2F 100%)',
            'cardBg' => 'rgba(30, 61, 47, 0.7)',
            'accent' => '#6EE7B7',
            'pattern' => 'none',
            'font' => 'Inter',
            'features' => ['prescription', 'batch-tracking', 'expiry-alerts'],
            'orderTypes' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'color' => '#10B981'],
                'prescription' => ['label' => 'Prescription', 'icon' => 'fa-file-medical', 'color' => '#3B82F6'],
                'phone' => ['label' => 'Phone Order', 'icon' => 'fa-phone', 'color' => '#F59E0B']
            ]
        ],
        
        'hardware' => [
            'name' => 'Hardware Store',
            'icon' => 'fa-hammer',
            'color' => '#F59E0B',
            'colorDark' => '#B45309',
            'gradient' => 'linear-gradient(135deg, #F59E0B 0%, #B45309 100%)',
            'bgGradient' => 'linear-gradient(135deg, #1A150F 0%, #3D301E 100%)',
            'cardBg' => 'rgba(61, 48, 30, 0.7)',
            'accent' => '#FCD34D',
            'pattern' => 'url("data:image/svg+xml,%3Csvg width=\'20\' height=\'20\' viewBox=\'0 0 20 20\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cpath d=\'M0 0h20v20H0V0zm10 10h10v10H10V10zM0 10h10v10H0V10z\' fill=\'%23F59E0B\' fill-opacity=\'0.03\'/%3E%3C/svg%3E")',
            'font' => 'Inter',
            'features' => ['weight', 'bulk-pricing', 'measurements', 'quotes'],
            'orderTypes' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'color' => '#F59E0B'],
                'quote' => ['label' => 'Quote', 'icon' => 'fa-file-invoice', 'color' => '#3B82F6'],
                'contractor' => ['label' => 'Contractor', 'icon' => 'fa-hard-hat', 'color' => '#EF4444']
            ]
        ],
        
        'butchery' => [
            'name' => 'Butchery',
            'icon' => 'fa-drumstick-bite',
            'color' => '#DC2626',
            'colorDark' => '#991B1B',
            'gradient' => 'linear-gradient(135deg, #DC2626 0%, #991B1B 100%)',
            'bgGradient' => 'linear-gradient(135deg, #1A0F0F 0%, #3D1E1E 100%)',
            'cardBg' => 'rgba(61, 30, 30, 0.7)',
            'accent' => '#FCA5A5',
            'pattern' => 'url("data:image/svg+xml,%3Csvg width=\'30\' height=\'30\' viewBox=\'0 0 30 30\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cpath d=\'M0 15h30M15 0v30\' stroke=\'%23DC2626\' stroke-opacity=\'0.05\' stroke-width=\'1\'/%3E%3C/svg%3E")',
            'font' => 'Inter',
            'features' => ['weight', 'cut-types', 'freshness-tracking', 'pre-orders'],
            'orderTypes' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'color' => '#DC2626'],
                'preorder' => ['label' => 'Pre-order', 'icon' => 'fa-clock', 'color' => '#F59E0B'],
                'bulk' => ['label' => 'Bulk Order', 'icon' => 'fa-boxes', 'color' => '#10B981']
            ]
        ],
        
        'bakery' => [
            'name' => 'Bakery',
            'icon' => 'fa-bread-slice',
            'color' => '#FBBF24',
            'colorDark' => '#D97706',
            'gradient' => 'linear-gradient(135deg, #FBBF24 0%, #D97706 100%)',
            'bgGradient' => 'linear-gradient(135deg, #1A170F 0%, #3D3015 100%)',
            'cardBg' => 'rgba(61, 48, 21, 0.7)',
            'accent' => '#FDE68A',
            'pattern' => 'url("data:image/svg+xml,%3Csvg width=\'20\' height=\'20\' viewBox=\'0 0 20 20\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Ccircle cx=\'2\' cy=\'2\' r=\'2\' fill=\'%23FBBF24\' fill-opacity=\'0.05\'/%3E%3Ccircle cx=\'12\' cy=\'12\' r=\'2\' fill=\'%23FBBF24\' fill-opacity=\'0.05\'/%3E%3C/svg%3E")',
            'font' => 'Inter',
            'features' => ['expiry', 'fresh-batch', 'custom-orders', 'morning-rush'],
            'orderTypes' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'color' => '#FBBF24'],
                'custom' => ['label' => 'Custom Order', 'icon' => 'fa-cake-candles', 'color' => '#EC4899'],
                'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-store', 'color' => '#10B981']
            ]
        ],
        
        'electronics' => [
            'name' => 'Electronics',
            'icon' => 'fa-mobile-screen',
            'color' => '#06B6D4',
            'colorDark' => '#0891B2',
            'gradient' => 'linear-gradient(135deg, #06B6D4 0%, #0891B2 100%)',
            'bgGradient' => 'linear-gradient(135deg, #0F1A1A 0%, #1E3D3D 100%)',
            'cardBg' => 'rgba(30, 61, 61, 0.7)',
            'accent' => '#67E8F9',
            'pattern' => 'none',
            'font' => 'Inter',
            'features' => ['serial', 'warranty', 'repair-tracking'],
            'orderTypes' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'color' => '#06B6D4'],
                'repair' => ['label' => 'Repair', 'icon' => 'fa-screwdriver-wrench', 'color' => '#EF4444'],
                'tradein' => ['label' => 'Trade-in', 'icon' => 'fa-rotate', 'color' => '#10B981']
            ]
        ],
        
        'grocery' => [
            'name' => 'Grocery Store',
            'icon' => 'fa-basket-shopping',
            'color' => '#22C55E',
            'colorDark' => '#15803D',
            'gradient' => 'linear-gradient(135deg, #22C55E 0%, #15803D 100%)',
            'bgGradient' => 'linear-gradient(135deg, #0F1A0F 0%, #1E3D1E 100%)',
            'cardBg' => 'rgba(30, 61, 30, 0.7)',
            'accent' => '#86EFAC',
            'pattern' => 'none',
            'font' => 'Inter',
            'features' => ['quick-sale', 'loyalty', 'promotions', 'inventory'],
            'orderTypes' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'color' => '#22C55E'],
                'online' => ['label' => 'Online', 'icon' => 'fa-globe', 'color' => '#3B82F6'],
                'pickup' => ['label' => 'Pickup', 'icon' => 'fa-bag-shopping', 'color' => '#F59E0B']
            ]
        ],
        
        'other' => [
            'name' => 'Business',
            'icon' => 'fa-briefcase',
            'color' => '#6B7280',
            'colorDark' => '#374151',
            'gradient' => 'linear-gradient(135deg, #6B7280 0%, #374151 100%)',
            'bgGradient' => 'linear-gradient(135deg, #0F172A 0%, #1F2937 100%)',
            'cardBg' => 'rgba(31, 41, 55, 0.7)',
            'accent' => '#9CA3AF',
            'pattern' => 'none',
            'font' => 'Inter',
            'features' => ['quick-sale', 'inventory'],
            'orderTypes' => [
                'walkin' => ['label' => 'Walk-in', 'icon' => 'fa-user', 'color' => '#6B7280'],
                'phone' => ['label' => 'Phone', 'icon' => 'fa-phone', 'color' => '#3B82F6']
            ]
        ]
    ];
    
    return $themes[$businessType] ?? $themes['other'];
}

/**
 * Generate CSS variables for the theme
 */
function generateThemeCSS(array $theme): string {
    $css = ":root {\n";
    $css .= "    --business-name: '{$theme['name']}';\n";
    $css .= "    --business-icon: '{$theme['icon']}';\n";
    $css .= "    --business-color: {$theme['color']};\n";
    $css .= "    --business-color-dark: {$theme['colorDark']};\n";
    $css .= "    --business-gradient: {$theme['gradient']};\n";
    $css .= "    --business-bg: {$theme['bgGradient']};\n";
    $css .= "    --business-card-bg: {$theme['cardBg']};\n";
    $css .= "    --business-accent: {$theme['accent']};\n";
    $css .= "    --business-pattern: {$theme['pattern']};\n";
    $css .= "}\n";
    return $css;
}
