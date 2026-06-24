<?php
/**
 * Payment Reconciliation API
 * 
 * Automatically reconciles pending payments with provider data
 * Handles discrepancies and updates payment statuses
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$company_id = intval($input['company_id'] ?? 0);
$branch_id = intval($input['branch_id'] ?? 0);

if (!$company_id) {
    echo json_encode(['success' => false, 'error' => 'Company ID required']);
    exit;
}

try {
    $pdo = get_db_connection();
    $reconciled = [];
    $discrepancies = [];

    // Get pending payments older than 2 minutes (give time for webhooks)
    $sql = "
        SELECT 
            id,
            reference,
            amount,
            payment_method,
            transaction_ref,
            mpesa_receipt_number,
            stripe_payment_intent_id,
            created_at
        FROM qr_payments
        WHERE company_id = ?
        AND status = 'pending'
        AND created_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ORDER BY created_at ASC
        LIMIT 50
    ";
    
    if ($branch_id) {
        $sql = str_replace('company_id = ?', 'company_id = ? AND branch_id = ?', $sql);
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$company_id, $branch_id]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$company_id]);
    }
    
    $pending_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pending_payments as $payment) {
        $status_from_provider = null;
        $reconciled_successfully = false;

        // Check with respective payment provider
        switch ($payment['payment_method']) {
            case 'mpesa':
                if ($payment['mpesa_receipt_number']) {
                    // Would query M-Pesa API here
                    // For now, mark as potentially completed if receipt exists
                    $status_from_provider = 'unknown';
                }
                break;

            case 'stripe':
                if ($payment['stripe_payment_intent_id']) {
                    // Would query Stripe API here
                    $status_from_provider = 'unknown';
                }
                break;

            case 'card':
            case 'cash':
                // These should have immediate confirmation
                // If still pending after 5 minutes, likely an error
                if (strtotime($payment['created_at']) < strtotime('-5 minutes')) {
                    $discrepancies[] = [
                        'payment_id' => $payment['id'],
                        'reference' => $payment['reference'],
                        'issue' => 'Offline payment still pending after 5 minutes',
                        'amount' => $payment['amount']
                    ];
                }
                break;
        }

        // Auto-reconcile if we have a sale with matching transaction ref
        if ($payment['transaction_ref']) {
            $sql = "
                SELECT id, status, total 
                FROM sales 
                WHERE transaction_ref = ? AND company_id = ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$payment['transaction_ref'], $company_id]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($sale) {
                // Found matching sale - update payment status
                $sql = "
                    UPDATE qr_payments 
                    SET status = 'completed', sale_id = ? 
                    WHERE id = ? AND company_id = ?
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$sale['id'], $payment['id'], $company_id]);

                $reconciled[] = [
                    'payment_id' => $payment['id'],
                    'reference' => $payment['reference'],
                    'sale_id' => $sale['id'],
                    'amount' => $payment['amount']
                ];
                $reconciled_successfully = true;
            }
        }

        // Check for orphaned sales (payment completed but no sale record linked)
        if (!$reconciled_successfully) {
            $sql = "
                SELECT id, payment_method, total, status
                FROM sales
                WHERE company_id = ?
                AND ABS(total - ?) < 0.01
                AND created_at BETWEEN DATE_SUB(?, INTERVAL 1 MINUTE) AND DATE_ADD(?, INTERVAL 1 MINUTE)
                AND (payment_reference = ? OR notes LIKE ?)
                AND status = 'completed'
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $company_id, 
                $payment['amount'],
                $payment['created_at'],
                $payment['created_at'],
                $payment['reference'],
                '%' . $payment['reference'] . '%'
            ]);
            $orphaned_sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($orphaned_sale) {
                // Link the payment to the orphaned sale
                $sql = "
                    UPDATE qr_payments 
                    SET status = 'completed', sale_id = ?, transaction_ref = ?
                    WHERE id = ? AND company_id = ?
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $orphaned_sale['id'],
                    $orphaned_sale['payment_method'] . '-' . $orphaned_sale['id'],
                    $payment['id'],
                    $company_id
                ]);

                $reconciled[] = [
                    'payment_id' => $payment['id'],
                    'reference' => $payment['reference'],
                    'sale_id' => $orphaned_sale['id'],
                    'amount' => $payment['amount'],
                    'note' => 'Orphaned sale linked'
                ];
            }
        }
    }

    // Check for payments that should be expired
    $sql = "
        UPDATE qr_payments 
        SET status = 'expired' 
        WHERE company_id = ? 
        AND status = 'pending' 
        AND expires_at < NOW()
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$company_id]);
    $expired_count = $stmt->rowCount();

    // Log reconciliation activity
    $sql = "
        INSERT INTO payment_reconciliation_log (company_id, branch_id, payments_checked, reconciled_count, expired_count, checked_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $company_id,
        $branch_id,
        count($pending_payments),
        count($reconciled),
        $expired_count
    ]);

    echo json_encode([
        'success' => true,
        'checked' => count($pending_payments),
        'reconciled' => $reconciled,
        'discrepancies' => $discrepancies,
        'expired' => $expired_count,
        'checked_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log('Payment reconciliation error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Reconciliation failed'
    ]);
}
