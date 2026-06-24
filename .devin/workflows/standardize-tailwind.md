---
description: Standardize Tailwind CSS styling across all POS return management pages
---

# Standardize Tailwind CSS Styling

Apply consistent compact styling to all return management PHP files.

## Container Changes
- Change `max-w-5xl` or `max-w-6xl` to `max-w-7xl`
- Change `py-6 space-y-6` to `py-4 space-y-4`

## Card Changes
- Change `rounded-xl` to `rounded-lg`
- Remove `backdrop-blur-sm`
- Keep `bg-gray-800/40 border border-gray-700`

## Header Pattern
```html
<div class="flex items-center gap-2 text-xs text-amber-400 mb-0.5">
    <i class="fas fa-icon text-xs"></i>
    <span class="font-semibold tracking-wider">RETURNS MANAGEMENT</span>
</div>
<h1 class="text-2xl font-bold text-white">Page Title</h1>
<p class="text-xs text-gray-500 mt-0.5">Description</p>
```

## Button Pattern
- Use `px-3 py-1.5` for compact buttons
- Structure: `<i class="fas fa-icon mr-1 text-amber-400"></i> Label`

## Alert Pattern (Success)
```html
<div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-400 text-xs flex justify-between items-center">
    <span><i class="fas fa-check-circle mr-2"></i> Message</span>
    <button onclick="this.parentElement.remove()" class="text-gray-500 hover:text-gray-300">&times;</button>
</div>
```

## Alert Pattern (Error)
```html
<div class="p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-xs flex justify-between items-center">
    <span><i class="fas fa-exclamation-circle mr-2"></i> Message</span>
    <button onclick="this.parentElement.remove()" class="text-gray-500 hover:text-gray-300">&times;</button>
</div>
```

## Typography
- Labels: `text-[10px] text-gray-500 uppercase`
- Body: `text-xs`
- Section headers: `text-xs font-semibold text-amber-400 uppercase tracking-wider`

## Status Badges
```html
<span class="inline-block px-2 py-0.5 rounded text-[10px] font-medium border bg-emerald-500/15 text-emerald-400 border-emerald-500/30">
    COMPLETED
</span>
```

## Files to Update
1. edit_sell_return.php ✓
2. view_sell_return.php ✓
3. create_sell_return.php ✓
4. view_return.php ✓
5. process_return.php ✓
6. select_sale_for_return.php (already consistent)
7. return_sale.php (already consistent)
8. list_sell_return.php (check for any inconsistencies)
