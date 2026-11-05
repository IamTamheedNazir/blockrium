<?php

namespace App\Console\Commands;

use App\Services\GooglePlayBillingService;
use Illuminate\Console\Command;

class AcknowledgeGooglePlayPurchases extends Command
{
    protected $signature = 'google-play:acknowledge-pending {--limit=100}';

    protected $description = 'Retry acknowledgement for recently purchased Google Play items';

    public function handle(GooglePlayBillingService $billing): int
    {
        $stats = $billing->retryPendingAcknowledgements((int) $this->option('limit'));
        $this->info(sprintf(
            'Checked %d; acknowledged %d; still pending %d.',
            $stats['checked'],
            $stats['acknowledged'],
            $stats['pending']
        ));

        foreach ($stats['results'] ?? [] as $result) {
            $this->line(sprintf(
                'Purchase #%d: %s%s%s',
                $result['purchase_id'],
                $result['outcome'] ?? 'unknown',
                isset($result['http_status']) ? ' (HTTP ' . $result['http_status'] . ')' : '',
                isset($result['exception']) ? ' — ' . $result['exception'] . (isset($result['source']) ? ' at ' . $result['source'] : '') : ''
            ));
        }

        return self::SUCCESS;
    }
}
