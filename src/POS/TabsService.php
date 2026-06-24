<?php
declare(strict_types=1);

namespace App\Pos;

use PDO;
use PDOException;

/**
 * TabsService — manages open tabs (running tables) for restaurants/bars.
 *
 * Open tab lifecycle:
 *   1. Server opens a tab for a table or guest    → openTab()
 *   2. Items are added during the meal             → addItem()
 *   3. Each batch of new items sent to kitchen/bar → sendToStation()
 *   4. When guest is ready to pay                  → settle()
 *
 * KOT (Kitchen Order Ticket) routing groups items by `station`
 * (kitchen, bar, grill, pizza) so each station only receives what
 * it should prepare.
 */
class TabsService
{
    private PDO $pdo;
    private int $tenant_id;
    private int $branch_id;
    private ?int $user_id;

    public function __construct(PDO $pdo, int $tenant_id, int $branch_id, ?int $user_id = null)
    {
        $this->pdo       = $pdo;
        $this->tenant_id = $tenant_id;
        $this->branch_id = $branch_id;
        $this->user_id   = $user_id;
    }

    // ---------------------------------------------------------------------
    // Tab management
    // ---------------------------------------------------------------------

    public function openTab(?string $table = null, ?string $guest = null, ?string $notes = null): int
    {
        $number = $this->generateTabNumber();
        $stmt = $this->pdo->prepare(
            "INSERT INTO pos_tabs
                (tenant_id, branch_id, tab_number, table_number, guest_name,
                 server_user_id, status, notes, opened_at)
             VALUES (?, ?, ?, ?, ?, ?, 'open', ?, NOW())"
        );
        $stmt->execute([
            $this->tenant_id, $this->branch_id, $number,
            $table, $guest, $this->user_id, $notes
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getTab(int $tab_id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM pos_tabs WHERE id = ? AND tenant_id = ? AND branch_id = ?"
        );
        $stmt->execute([$tab_id, $this->tenant_id, $this->branch_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listOpenTabs(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.*,
                    (SELECT COUNT(*) FROM pos_tab_items i WHERE i.tab_id = t.id AND i.voided = 0) AS item_count
             FROM pos_tabs t
             WHERE t.tenant_id = ? AND t.branch_id = ? AND t.status = 'open'
             ORDER BY t.opened_at DESC"
        );
        $stmt->execute([$this->tenant_id, $this->branch_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addItem(int $tab_id, array $item): int
    {
        $tab = $this->getTab($tab_id);
        if (!$tab || $tab['status'] !== 'open') {
            throw new \RuntimeException('Tab is not open');
        }

        $qty   = (float) ($item['qty'] ?? 1);
        $price = (float) ($item['price'] ?? 0);
        $disc  = (float) ($item['discount'] ?? 0);
        $total = max(0, $qty * $price - $disc);

        $stmt = $this->pdo->prepare(
            "INSERT INTO pos_tab_items
                (tenant_id, tab_id, product_id, name, qty, unit_price, discount,
                 line_total, course, station, seat_number, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $this->tenant_id, $tab_id,
            $item['product_id'] ?? null,
            (string) ($item['name'] ?? 'Item'),
            $qty, $price, $disc, $total,
            $item['course']  ?? null,
            $item['station'] ?? 'kitchen',
            $item['seat_number'] ?? null,
            $item['notes']   ?? null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->recalculateTab($tab_id);
        return $id;
    }

    public function voidItem(int $item_id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE pos_tab_items SET voided = 1
             WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$item_id, $this->tenant_id]);

        $tab_id = (int) $this->pdo->query("SELECT tab_id FROM pos_tab_items WHERE id = " . $item_id)->fetchColumn();
        if ($tab_id) $this->recalculateTab($tab_id);
    }

    public function getItems(int $tab_id, bool $includeVoided = false): array
    {
        $where = $includeVoided ? '' : ' AND voided = 0';
        $stmt = $this->pdo->prepare(
            "SELECT * FROM pos_tab_items
             WHERE tab_id = ? AND tenant_id = ? {$where}
             ORDER BY id"
        );
        $stmt->execute([$tab_id, $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function recalculateTab(int $tab_id): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(line_total), 0) AS subtotal
             FROM pos_tab_items
             WHERE tab_id = ? AND voided = 0"
        );
        $stmt->execute([$tab_id]);
        $subtotal = (float) $stmt->fetchColumn();
        $tax_rate = $this->getTaxRate();
        $tax      = round($subtotal * $tax_rate / 100, 2);
        $total    = $subtotal + $tax;

        $stmt = $this->pdo->prepare(
            "UPDATE pos_tabs SET subtotal = ?, tax = ?, total = ?
             WHERE id = ?"
        );
        $stmt->execute([$subtotal, $tax, $total, $tab_id]);
    }

    /**
     * Settle a tab — converts it into a sale and closes the tab.
     * Returns the new sale_id (or null if your sale-creation flow is external).
     */
    public function settle(int $tab_id, ?int $sale_id = null): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE pos_tabs
                SET status = 'settled', settled_at = NOW(), sale_id = COALESCE(?, sale_id)
              WHERE id = ? AND tenant_id = ? AND branch_id = ?"
        );
        $stmt->execute([$sale_id, $tab_id, $this->tenant_id, $this->branch_id]);
    }

    public function voidTab(int $tab_id, ?string $reason = null): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE pos_tabs
                SET status = 'voided',
                    notes  = CONCAT(COALESCE(notes,''), ' [VOID: ', ?, ']'),
                    settled_at = NOW()
              WHERE id = ? AND tenant_id = ? AND branch_id = ?"
        );
        $stmt->execute([$reason ?? '', $tab_id, $this->tenant_id, $this->branch_id]);
    }

    // ---------------------------------------------------------------------
    // KOT routing
    // ---------------------------------------------------------------------

    /**
     * Send all not-yet-sent items of a tab to their respective stations,
     * grouped into one ticket per station.
     * Returns map of station => kot_job_id.
     */
    public function sendToStation(int $tab_id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM pos_tab_items
             WHERE tab_id = ? AND voided = 0 AND sent_to_kot_at IS NULL
             ORDER BY id"
        );
        $stmt->execute([$tab_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($items)) return [];

        $by_station = [];
        foreach ($items as $i) {
            $st = $i['station'] ?: 'kitchen';
            $by_station[$st][] = $i;
        }

        $jobs = [];
        foreach ($by_station as $station => $rows) {
            $ticket = $this->generateTicketNumber($station);
            $stmt = $this->pdo->prepare(
                "INSERT INTO pos_kot_jobs
                    (tenant_id, branch_id, tab_id, station, ticket_number,
                     items_json, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())"
            );
            $stmt->execute([
                $this->tenant_id, $this->branch_id, $tab_id,
                $station, $ticket, json_encode($rows, JSON_UNESCAPED_UNICODE)
            ]);
            $jobs[$station] = (int) $this->pdo->lastInsertId();
        }

        // Mark items as sent
        $ids = array_column($items, 'id');
        if (!empty($ids)) {
            $in = implode(',', array_map('intval', $ids));
            $this->pdo->exec("UPDATE pos_tab_items SET sent_to_kot_at = NOW() WHERE id IN ($in)");
        }

        return $jobs;
    }

    public function getPendingKots(?string $station = null): array
    {
        $sql = "SELECT * FROM pos_kot_jobs
                WHERE tenant_id = ? AND branch_id = ? AND status = 'pending'";
        $params = [$this->tenant_id, $this->branch_id];
        if ($station) { $sql .= " AND station = ?"; $params[] = $station; }
        $sql .= " ORDER BY created_at";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markKotPrinted(int $job_id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE pos_kot_jobs SET status = 'printed', printed_at = NOW()
             WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$job_id, $this->tenant_id]);
    }

    public function markKotAck(int $job_id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE pos_kot_jobs SET status = 'ack', acknowledged_at = NOW()
             WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$job_id, $this->tenant_id]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function generateTabNumber(): string
    {
        return 'TAB-' . date('ymd') . '-' . substr((string) microtime(true), -4) . '-' . random_int(10, 99);
    }

    private function generateTicketNumber(string $station): string
    {
        return strtoupper(substr($station, 0, 3)) . '-' . date('Hi') . '-' . random_int(100, 999);
    }

    private function getTaxRate(): float
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT setting_value FROM settings
                 WHERE setting_key = 'tax_rate' AND (tenant_id = ? OR tenant_id IS NULL)
                 LIMIT 1"
            );
            $stmt->execute([$this->tenant_id]);
            $v = $stmt->fetchColumn();
            return is_numeric($v) ? (float) $v : 0.0;
        } catch (PDOException $e) {
            return 0.0;
        }
    }
}
