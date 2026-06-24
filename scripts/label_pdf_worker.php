<?php
// CLI worker: process label PDF jobs queued in storage/label_jobs
chdir(__DIR__ . '/../');
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

$storageDir = __DIR__ . '/../storage/label_jobs';
$exportDir = __DIR__ . '/../storage/label_jobs/exports';
if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);
if (!is_dir($exportDir)) mkdir($exportDir, 0755, true);

$pdo = get_db_connection();

$files = glob($storageDir . '/*.json');
foreach ($files as $file) {
    $job = json_decode(file_get_contents($file), true);
    if (empty($job) || ($job['status'] ?? '') !== 'pending') {
        continue;
    }

    $jobId = $job['id'] ?? null;
    echo "Processing job: {$jobId}\n";

    // Mark processing
    $job['status'] = 'processing';
    $job['updated_at'] = time();
    file_put_contents($file, json_encode($job, JSON_PRETTY_PRINT));

    try {
        // Rehydrate services
        require_once __DIR__ . '/../src/Barcode/LabelTemplate.php';
        require_once __DIR__ . '/../src/Barcode/BarcodeService.php';
        require_once __DIR__ . '/../src/Barcode/LabelRenderer.php';
        require_once __DIR__ . '/../src/Barcode/PdfLabelGenerator.php';

        $labelSize = $job['label_size'] ?? 'k22';
        $template = \JDH\POS\Barcode\LabelTemplate::get($labelSize) ?? \JDH\POS\Barcode\LabelTemplate::get('k22');

        // Enrich product details from DB
        $enriched = [];
        foreach ($job['products'] as $p) {
            $stmt = $pdo->prepare("SELECT name, price, sku FROM products WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$p['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $enriched[] = [
                    'id' => (int)$p['id'],
                    'name' => $row['name'],
                    'price' => $row['price'],
                    'sku' => $row['sku'],
                    'qty' => (int)($p['qty'] ?? 1),
                ];
            }
        }

        $renderer = new \JDH\POS\Barcode\LabelRenderer(new \JDH\POS\Barcode\BarcodeService());
        $storeName = \JDH\POS\Barcode\LabelPrintService::resolveStoreName($pdo, $job['tenant_id'] ?? null);
        $pdfData = $renderer->prepareForPdf($enriched, $template, $storeName);

        $pdfGen = new \JDH\POS\Barcode\PdfLabelGenerator();
        $outfile = $exportDir . '/' . $jobId . '.pdf';
        $pdfGen->generatePdf($pdfData, $outfile);

        // Save relative path in job
        $job['status'] = 'done';
        $job['output_file'] = 'storage/label_jobs/exports/' . basename($outfile);
        $job['updated_at'] = time();
        file_put_contents($file, json_encode($job, JSON_PRETTY_PRINT));

        echo "Job {$jobId} completed -> {$outfile}\n";
    } catch (Exception $e) {
        $job['status'] = 'failed';
        $job['error'] = $e->getMessage();
        $job['updated_at'] = time();
        file_put_contents($file, json_encode($job, JSON_PRETTY_PRINT));
        echo "Job {$jobId} failed: {$e->getMessage()}\n";
    }
}

echo "Worker run complete.\n";
