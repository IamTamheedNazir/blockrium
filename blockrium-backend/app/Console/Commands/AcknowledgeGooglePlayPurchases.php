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

        return self::SUCCESS;
    }
}
