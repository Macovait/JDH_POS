<?php
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_once __DIR__ . '/../../src/Security/SecurityBootstrap.php';
require_once __DIR__ . '/../../src/Security/DatabaseSecurityPatches.php';
SecurityBootstrap::initialize();
require_login();
if (!has_permission('products.manage')) enforce_permission('products.manage');
$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
if (!$tenant_id) { http_response_code(403); exit('Company context missing.'); }
$branch_id = get_current_branch_id();
$branch_name = get_current_branch_name();
$id = intval($_GET['id'] ?? 0);
$fields = ['name'=>'','sku'=>'','barcode'=>'','price'=>0,'cost_price'=>0,'category_id'=>null,'brand_id'=>null,'image'=>null,'active'=>1,'description'=>'','unit'=>'pcs','tax_rate'=>0,'reorder_level'=>5,'stock'=>0,'minimum_stock'=>0,'maximum_stock'=>0];
foreach ($fields as $k=>$v) $$k=$v;
$errors=[];
if ($id) {
    try {
        $product = secure_db_find('products', $id);
        if ($product) { foreach (['name','sku','barcode','price','cost_price','category_id','brand_id','image','active','description','unit','tax_rate','reorder_level'] as $k) if (isset($product[$k])) $$k=$product[$k]; }
        else $errors[]='Product not found';
        $invStmt=$pdo->prepare("SELECT stock,minimum_stock,maximum_stock FROM inventory WHERE product_id=? AND tenant_id=? AND branch_id=? LIMIT 1");
        $invStmt->execute([$id,$tenant_id,$branch_id]);
        if ($inv=$invStmt->fetch(PDO::FETCH_ASSOC)) { $stock=$inv['stock']??0; $minimum_stock=$inv['minimum_stock']??0; $maximum_stock=$inv['maximum_stock']??0; }
    } catch (Exception $e) { $errors[]='Failed to load product'; error_log($e->getMessage()); }
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name=trim($_POST['name']??''); $sku=trim($_POST['sku']??''); $barcode=trim($_POST['barcode']??''); $price=floatval($_POST['price']??0); $cost_price=floatval($_POST['cost_price']??0);
    $category_id=!empty($_POST['category_id'])?intval($_POST['category_id']):null; $brand_id=!empty($_POST['brand_id'])?intval($_POST['brand_id']):null;
    $active=isset($_POST['active'])?1:0; $description=trim($_POST['description']??''); $unit=trim($_POST['unit']??'pcs'); $tax_rate=floatval($_POST['tax_rate']??0); $reorder_level=intval($_POST['reorder_level']??5);
    $stock=intval($_POST['stock']??0); $minimum_stock=intval($_POST['minimum_stock']??0); $maximum_stock=intval($_POST['maximum_stock']??0);
    if (empty($name)) $errors[]='Product name is required'; if ($price<0) $errors[]='Price cannot be negative'; if ($cost_price<0) $errors[]='Cost price cannot be negative';
    if ($sku) { $chk=$id?$pdo->prepare("SELECT id FROM products WHERE sku=? AND id!=? AND tenant_id=? AND deleted_at IS NULL"):$pdo->prepare("SELECT id FROM products WHERE sku=? AND tenant_id=? AND deleted_at IS NULL"); $id?$chk->execute([$sku,$id,$tenant_id]):$chk->execute([$sku,$tenant_id]); if ($chk->fetch()) $errors[]='SKU already exists'; }
    $imagePath=$image;
    if (isset($_FILES['image']) && $_FILES['image']['error']===UPLOAD_ERR_OK) {
        $allowed=['image/jpeg','image/png','image/gif','image/webp']; $max=2*1024*1024;
        $finfo=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($finfo,$_FILES['image']['tmp_name']); finfo_close($finfo);
        if (!in_array($mime,$allowed)) $errors[]='Invalid image type'; elseif ($_FILES['image']['size']>$max) $errors[]='Image max 2MB';
        else { $dir=PUBLIC_PATH.'/uploads/product_images/'; if (!is_dir($dir)) mkdir($dir,0755,true); $fn='prod_'.time().'_'.uniqid().'.'.pathinfo($_FILES['image']['name'],PATHINFO_EXTENSION); if (move_uploaded_file($_FILES['image']['tmp_name'],$dir.$fn)) { if ($image) { $op=ltrim($image,'/'); if (strpos($op,'public/')===0) $op=substr($op,7); $fp=PUBLIC_PATH.'/'.str_replace('/',DIRECTORY_SEPARATOR,$op); if (file_exists($fp)) @unlink($fp); } $imagePath='uploads/product_images/'.$fn; } else $errors[]='Upload failed'; }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image']==='1') { if ($image) { $op=ltrim($image,'/'); if (strpos($op,'public/')===0) $op=substr($op,7); $fp=PUBLIC_PATH.'/'.str_replace('/',DIRECTORY_SEPARATOR,$op); if (file_exists($fp)) @unlink($fp); } $imagePath=null; }
    if (empty($errors)) {
        try {
            if (!validate_csrf_token($_POST['csrf_token']??'')) $errors[]='CSRF validation failed';
            if (empty($errors)) {
                if ($id) {
                    $pdo->prepare("UPDATE products SET name=?,sku=?,barcode=?,price=?,cost_price=?,category_id=?,brand_id=?,description=?,active=?,unit=?,tax_rate=?,reorder_level=?,image=?,updated_at=NOW() WHERE id=? AND tenant_id=? AND deleted_at IS NULL")
                        ->execute([$name,$sku,$barcode,$price,$cost_price,$category_id,$brand_id,$description,$active,$unit,$tax_rate,$reorder_level,$imagePath,$id,$tenant_id]);
                    $invCheck=$pdo->prepare("SELECT 1 FROM inventory WHERE product_id=? AND tenant_id=? AND branch_id=? LIMIT 1"); $invCheck->execute([$id,$tenant_id,$branch_id]);
                    if ($invCheck->fetch()) $pdo->prepare("UPDATE inventory SET stock=?,reorder_level=?,minimum_stock=?,maximum_stock=?,updated_at=NOW() WHERE product_id=? AND tenant_id=? AND branch_id=?")->execute([max(0,$stock),max(0,$reorder_level),max(0,$minimum_stock),max(0,$maximum_stock),$id,$tenant_id,$branch_id]);
                    else $pdo->prepare("INSERT INTO inventory (product_id,tenant_id,branch_id,stock,reorder_level,minimum_stock,maximum_stock,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())")->execute([$id,$tenant_id,$branch_id,max(0,$stock),max(0,$reorder_level),max(0,$minimum_stock),max(0,$maximum_stock)]);
                    $redirect_msg='Product updated successfully';
                } else {
                    $pdo->prepare("INSERT INTO products (tenant_id,name,sku,barcode,price,cost_price,category_id,brand_id,description,active,unit,tax_rate,reorder_level,image,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
                        ->execute([$tenant_id,$name,$sku,$barcode,$price,$cost_price,$category_id,$brand_id,$description,$active,$unit,$tax_rate,$reorder_level,$imagePath]);
                    $new_id=$pdo->lastInsertId();
                    $brStmt=$pdo->prepare("SELECT id FROM branches WHERE tenant_id=? AND active=1 AND deleted_at IS NULL"); $brStmt->execute([$tenant_id]);
                    $invIns=$pdo->prepare("INSERT INTO inventory (tenant_id,product_id,branch_id,stock,reorder_level,minimum_stock,maximum_stock,created_at,updated_at) VALUES (?,?,?,0,?,?,?,NOW(),NOW())");
                    foreach ($brStmt->fetchAll(PDO::FETCH_COLUMN) as $bid) $invIns->execute([$tenant_id,$new_id,$bid,$reorder_level,$minimum_stock,$maximum_stock]);
                    $redirect_msg='Product created successfully';
                }
                header('Location: products.php?branch_id='.$branch_id.'&success='.urlencode($redirect_msg)); exit;
            }
        } catch (Exception $e) { $errors[]='Database error: '.$e->getMessage(); error_log($e->getMessage()); }
    }
}
$categories=[]; try { $s=$pdo->prepare("SELECT id,name FROM categories WHERE tenant_id=? AND (status='active' OR status IS NULL) AND deleted_at IS NULL ORDER BY name"); $s->execute([$tenant_id]); $categories=$s->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) {}
$brands=[]; try { $s=$pdo->prepare("SELECT id,name FROM brands WHERE tenant_id=? AND active=1 ORDER BY name"); $s->execute([$tenant_id]); $brands=$s->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) {}
$page_title=($id?'Edit':'Add').' Product'; ob_start();
?>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <a href="products.php?branch_id=<?php echo $branch_id; ?>" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-amber-400 transition-colors mb-2"><i class="fas fa-arrow-left text-xs"></i> Back to Products</a>
        <h1 class="text-lg font-bold text-white flex items-center gap-2"><i class="fas fa-<?php echo $id?'pen':'plus-circle'; ?> text-amber-400"></i> <?php echo $id?'Edit Product':'Add New Product'; ?></h1>
        <p class="text-sm text-slate-500 mt-0.5"><?php echo $id?'Update product information':'Create a new product in your catalog'; ?></p>
    </div>
</div>
<?php if (!empty($errors)): ?>
<div class="mb-6 bg-red-500/10 border border-red-500 rounded-xl p-4">
    <div class="flex items-start gap-3 text-red-400"><i class="fas fa-exclamation-triangle flex-shrink-0 mt-0.5"></i><div class="text-white"><h3 class="font-semibold mb-2">Please fix the following errors:</h3><ul class="list-disc list-inside text-sm space-y-1"><?php foreach ($errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?></ul></div></div>
</div>
<?php endif; ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
    <form method="post" enctype="multipart/form-data" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']??''); ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label for="name" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-cube text-amber-500 mr-1"></i>Product Name <span class="text-red-400">*</span></label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($name); ?>" placeholder="Enter product name" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
            </div>
            <div>
                <label for="sku" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-barcode text-amber-500 mr-1"></i>SKU</label>
                <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($sku); ?>" placeholder="e.g., PRD-001" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
                <p class="text-xs text-slate-600 mt-1">Leave empty for auto-generated SKU</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label for="barcode" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-barcode text-amber-500 mr-1"></i>Barcode</label>
                <input type="text" id="barcode" name="barcode" value="<?php echo htmlspecialchars($barcode); ?>" placeholder="Scan or enter barcode" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
            </div>
            <div>
                <label for="unit" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-ruler text-amber-500 mr-1"></i>Unit</label>
                <input type="text" id="unit" name="unit" value="<?php echo htmlspecialchars($unit); ?>" placeholder="e.g., pcs, kg, box" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label for="category_id" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-folder text-amber-500 mr-1"></i>Category</label>
                <select id="category_id" name="category_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors"><option value="">-- Select Category --</option><?php foreach ($categories as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo $c['id']==$category_id?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select>
            </div>
            <div>
                <label for="brand_id" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-copyright text-amber-500 mr-1"></i>Brand</label>
                <select id="brand_id" name="brand_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors"><option value="">-- Select Brand --</option><?php foreach ($brands as $b): ?><option value="<?php echo $b['id']; ?>" <?php echo $b['id']==$brand_id?'selected':''; ?>><?php echo htmlspecialchars($b['name']); ?></option><?php endforeach; ?></select>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label for="price" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-dollar-sign text-amber-500 mr-1"></i>Selling Price <span class="text-red-400">*</span></label>
                <div class="relative"><span class="absolute left-3 top-2 text-slate-500 text-sm">KSh</span><input type="number" id="price" name="price" required step="0.01" min="0" value="<?php echo htmlspecialchars($price); ?>" placeholder="0.00" class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-10 pr-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors"></div>
            </div>
            <div>
                <label for="cost_price" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-coins text-amber-500 mr-1"></i>Cost Price</label>
                <div class="relative"><span class="absolute left-3 top-2 text-slate-500 text-sm">KSh</span><input type="number" id="cost_price" name="cost_price" step="0.01" min="0" value="<?php echo htmlspecialchars($cost_price); ?>" placeholder="0.00" class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-10 pr-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors"></div>
                <p class="text-xs text-slate-600 mt-1">Your purchase cost from supplier</p>
            </div>
            <div>
                <label for="tax_rate" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-percent text-amber-500 mr-1"></i>Tax Rate</label>
                <div class="relative"><input type="number" id="tax_rate" name="tax_rate" step="0.01" min="0" value="<?php echo htmlspecialchars($tax_rate); ?>" placeholder="0.00" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors"><span class="absolute right-3 top-2 text-slate-500 text-sm">%</span></div>
            </div>
        </div>
        <div>
            <label for="description" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-align-left text-amber-500 mr-1"></i>Description</label>
            <textarea id="description" name="description" rows="3" placeholder="Enter product description (optional)" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors resize-none"><?php echo htmlspecialchars($description); ?></textarea>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-image text-amber-500 mr-1"></i>Product Image</label>
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1"><input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-400 text-sm file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-500 file:text-slate-900 hover:file:bg-amber-400 transition-all focus:outline-none"><p class="text-xs text-slate-600 mt-1.5">JPG, PNG, GIF, WebP &middot; max 2MB</p></div>
                <?php if ($image): ?><div class="relative"><img src="<?php echo htmlspecialchars(base_url($image)); ?>" alt="Product preview" class="w-28 h-28 object-cover rounded-xl border border-slate-700/60"><button type="button" onclick="removeImage()" class="absolute -top-1.5 -right-1.5 w-5 h-5 flex items-center justify-center rounded-full bg-red-500 text-white text-xs hover:bg-red-600 transition-colors"><i class="fas fa-times"></i></button><input type="hidden" name="remove_image" id="remove_image" value="0"></div><?php endif; ?>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label for="reorder_level" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-bell text-amber-500 mr-1"></i>Reorder Level</label>
                <input type="number" id="reorder_level" name="reorder_level" min="0" value="<?php echo htmlspecialchars($reorder_level); ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
            </div>
            <div>
                <label for="minimum_stock" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-arrow-down text-amber-500 mr-1"></i>Minimum Stock</label>
                <input type="number" id="minimum_stock" name="minimum_stock" min="0" value="<?php echo htmlspecialchars($minimum_stock); ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
            </div>
            <div>
                <label for="maximum_stock" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-arrow-up text-amber-500 mr-1"></i>Maximum Stock</label>
                <input type="number" id="maximum_stock" name="maximum_stock" min="0" value="<?php echo htmlspecialchars($maximum_stock); ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
            </div>
        </div>
        <?php if ($id): ?>
        <div>
            <label for="stock" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1"><i class="fas fa-warehouse text-amber-500 mr-1"></i>Current Stock (<?php echo htmlspecialchars($branch_name); ?>)</label>
            <input type="number" id="stock" name="stock" min="0" value="<?php echo htmlspecialchars($stock); ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
        </div>
        <div class="flex items-center gap-3 px-4 py-3 bg-slate-900/50 border border-slate-700/40 rounded-xl">
            <i class="fas fa-power-off text-amber-500 text-xs"></i>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="active" value="1" class="sr-only peer" <?php echo $active?'checked':''; ?>>
                <div class="w-9 h-5 bg-slate-600 rounded-full peer peer-checked:bg-emerald-500 transition-colors"></div>
                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-4"></div>
            </label>
            <span class="text-sm text-slate-300">Product Active</span>
            <span class="text-xs text-slate-500 ml-auto">Inactive products won't appear in POS</span>
        </div>
        <?php endif; ?>
        <div class="flex gap-3 pt-4 border-t border-slate-700/60">
            <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors"><i class="fas fa-<?php echo $id?'save':'plus'; ?> text-xs"></i><?php echo $id?'Update Product':'Create Product'; ?></button>
            <a href="products.php?branch_id=<?php echo $branch_id; ?>" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</a>
        </div>
    </form>
</div>
<?php if (!$id): ?>
<div class="mt-6 bg-amber-500/5 rounded-xl border border-amber-500/20 p-4 text-sm">
    <div class="flex items-start gap-3"><i class="fas fa-info-circle text-amber-400 mt-1"></i><div class="text-slate-400"><p class="text-amber-400 font-semibold mb-1">About New Products</p><ul class="space-y-1 list-disc list-inside"><li>New products are created as <span class="text-emerald-400">Active</span> by default</li><li>Inventory will be initialized with <span class="text-amber-400">0 stock</span> for <strong>all branches</strong></li><li>You can add stock in the Inventory section</li></ul></div></div>
</div>
<?php endif; ?>
<script>
function removeImage(){ document.getElementById('remove_image').value='1'; const img=document.querySelector('.relative img'); if(img){ img.style.opacity='0.3'; img.style.pointerEvents='none'; } }
document.getElementById('image')?.addEventListener('change',function(e){ const file=e.target.files[0]; if(file&&file.size>2*1024*1024){ alert('Image size must be less than 2MB'); this.value=''; } });
document.getElementById('name')?.addEventListener('blur',function(){ const skuField=document.getElementById('sku'); if(!skuField.value&&this.value){ const suggestion=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'').substring(0,8); if(suggestion) skuField.value=suggestion+'-'+Math.floor(Math.random()*1000); } });
document.addEventListener('keydown',function(e){ if((e.ctrlKey||e.metaKey)&&e.key==='s'){ e.preventDefault(); document.querySelector('form').submit(); } if(e.key==='Escape'){ e.preventDefault(); window.location.href='products.php?branch_id=<?php echo $branch_id; ?>'; } });
</script>
<?php $page_content=ob_get_clean(); require_once __DIR__ . '/../layouts/app.php'; ?>
