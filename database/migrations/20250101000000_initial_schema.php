<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class InitialSchema extends AbstractMigration
{
    public function change(): void
    {
        // API Keys
        $this->table('api_keys', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true])
            ->addColumn('tenant_id', 'integer')
            ->addColumn('user_id', 'integer')
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('key_hash', 'string', ['limit' => 64])
            ->addColumn('permissions', 'text', ['null' => true])
            ->addColumn('usage_count', 'integer', ['default' => 0])
            ->addColumn('last_used_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('revoked_at', 'datetime', ['null' => true])
            ->addIndex(['tenant_id'])
            ->addIndex(['key_hash'], ['unique' => true])
            ->create();

        // Sync events (for SSE real-time)
        $this->table('sync_events', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true])
            ->addColumn('tenant_id', 'integer')
            ->addColumn('branch_id', 'integer')
            ->addColumn('event_type', 'string', ['limit' => 50])
            ->addColumn('event_data', 'text')
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['tenant_id', 'branch_id', 'id'])
            ->addIndex(['created_at'])
            ->create();

        // Invoice sequences
        $this->table('invoice_sequences', ['id' => false, 'primary_key' => ['year', 'month']])
            ->addColumn('year', 'integer')
            ->addColumn('month', 'integer')
            ->addColumn('last_number', 'integer', ['default' => 0])
            ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
