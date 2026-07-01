<?php

namespace App\Console\Commands;

use App\Models\ServiceAgreement;
use App\Models\Tenant;
use Illuminate\Console\Command;

class GenerateAgreementJobs extends Command
{
    protected $signature = 'agreements:run';

    protected $description = 'Spawn scheduled jobs for service agreements that are due (per tenant).';

    public function handle(): int
    {
        $created = 0;

        Tenant::all()->each(function (Tenant $tenant) use (&$created) {
            tenancy()->initialize($tenant);

            try {
                ServiceAgreement::query()
                    ->where('is_active', true)
                    ->whereDate('next_run_at', '<=', now()->toDateString())
                    ->with('items')
                    ->get()
                    ->each(function (ServiceAgreement $agreement) use (&$created) {
                        $agreement->generateDueJob();
                        $created++;
                    });
            } finally {
                tenancy()->end();
            }
        });

        $this->info("Generated {$created} job(s) from service agreements.");

        return self::SUCCESS;
    }
}
