# Convert old-style layout includes to ob_start() / ob_get_clean() pattern
$files = @(
    "c:\xampp\htdocs\JDH_POS\public\billing\mpesa_settings.php",
    "c:\xampp\htdocs\JDH_POS\public\billing\payment_history.php",
    "c:\xampp\htdocs\JDH_POS\public\billing\sms_settings.php",
    "c:\xampp\htdocs\JDH_POS\public\branches\branches.php",
    "c:\xampp\htdocs\JDH_POS\public\categories\add_category.php",
    "c:\xampp\htdocs\JDH_POS\public\categories\category_hierarchy.php",
    "c:\xampp\htdocs\JDH_POS\public\categories\edit_category.php",
    "c:\xampp\htdocs\JDH_POS\public\categories\list_categories.php",
    "c:\xampp\htdocs\JDH_POS\public\crm\deals.php",
    "c:\xampp\htdocs\JDH_POS\public\crm\index.php",
    "c:\xampp\htdocs\JDH_POS\public\crm\leads.php",
    "c:\xampp\htdocs\JDH_POS\public\expenses\add_expense.php",
    "c:\xampp\htdocs\JDH_POS\public\expenses\expense_categories.php",
    "c:\xampp\htdocs\JDH_POS\public\expenses\expenses.php",
    "c:\xampp\htdocs\JDH_POS\public\hr\employees.php",
    "c:\xampp\htdocs\JDH_POS\public\hr\index.php",
    "c:\xampp\htdocs\JDH_POS\public\hr\leave_requests.php",
    "c:\xampp\htdocs\JDH_POS\public\hr\hr\employees.php",
    "c:\xampp\htdocs\JDH_POS\public\inventory\automated_inventory.php",
    "c:\xampp\htdocs\JDH_POS\public\inventory\inventory.php",
    "c:\xampp\htdocs\JDH_POS\public\inventory\inventory_report.php",
    "c:\xampp\htdocs\JDH_POS\public\inventory\item_expiry.php",
    "c:\xampp\htdocs\JDH_POS\public\inventory\suppliers.php",
    "c:\xampp\htdocs\JDH_POS\public\inventory\variants.php",
    "c:\xampp\htdocs\JDH_POS\public\inventory\products\import_products.php",
    "c:\xampp\htdocs\JDH_POS\public\inventory\products\products.php",
    "c:\xampp\htdocs\JDH_POS\public\inventory\products\product_form.php",
    "c:\xampp\htdocs\JDH_POS\public\inventory\tax\tax_rates.php",
    "c:\xampp\htdocs\JDH_POS\public\marketing\campaigns.php",
    "c:\xampp\htdocs\JDH_POS\public\marketing\campaign_builder.php",
    "c:\xampp\htdocs\JDH_POS\public\payments\stripe_gateway.php",
    "c:\xampp\htdocs\JDH_POS\public\products\attributes.php",
    "c:\xampp\htdocs\JDH_POS\public\purchases\list_draft.php",
    "c:\xampp\htdocs\JDH_POS\public\quotations\add_quotation.php",
    "c:\xampp\htdocs\JDH_POS\public\quotations\list_quotation.php",
    "c:\xampp\htdocs\JDH_POS\public\reports\profit_loss.php",
    "c:\xampp\htdocs\JDH_POS\public\reports\sales_report.php"
)

foreach ($file in $files) {
    if (-not (Test-Path $file)) { continue }
    $content = Get-Content $file -Raw

    # Determine path depth
    $depth = ($file -split "public\\")[1]
    $slashCount = ($depth -split "\\").Count - 1
    $pathPrefix = ""
    for ($i = 0; $i -lt $slashCount; $i++) { $pathPrefix += "../" }

    # Pattern 1: replace include/require of layouts/app.php (with or without __DIR__) with ob_start();
    $content = $content -replace "(?m)^(\s*)(include|require)(_once)?\s+(__DIR__\s*\.\s*)?'"""" + [regex]::Escape($pathPrefix) + "layouts/app\.php'\s*;\s*\r?\n", "`${1}ob_start();`r`n"
    $content = $content -replace "(?m)^(\s*)(include|require)(_once)?\s+(__DIR__\s*\.\s*)?'"""" + [regex]::Escape($pathPrefix) + "layouts/app\.php'\s*;\s*\r?\n", "`${1}ob_start();`r`n"

    # Pattern 2: replace closing layouts/app_close.php with ob_get_clean + layout includes
    $oldClose = "`$1$page_content = ob_get_clean();`r`n`$1require_once __DIR__ . '/" + $pathPrefix + "layouts/app.php';`r`n`$1require_once __DIR__ . '/" + $pathPrefix + "layouts/app_close.php';`r`n"
    $content = $content -replace "(?m)^(\s*)(include|require)(_once)?\s+(__DIR__\s*\.\s*)?'"""" + [regex]::Escape($pathPrefix) + "layouts/app_close\.php'\s*;\s*\r?\n", $oldClose

    # Backup original
    Copy-Item $file "$file.bak" -Force
    Set-Content $file $content -NoNewline
    Write-Host "Converted: $file"
}

Write-Host "Done converting layout patterns."
