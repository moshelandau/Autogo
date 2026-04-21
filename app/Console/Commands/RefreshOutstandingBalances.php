<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;

class RefreshOutstandingBalances extends Command
{
    protected $signature = 'balances:refresh';
    protected $description = 'Recompute cached_outstanding_balance for every customer.';

    public function handle(): int
    {
        $count = 0;
        $withBalance = 0;
        Customer::with(['reservations'])->chunkById(200, function ($chunks) use (&$count, &$withBalance) {
            foreach ($chunks as $c) {
                $bal = $c->computeOutstandingBalance();
                $c->update(['cached_outstanding_balance' => $bal]);
                $count++;
                if ($bal > 0) $withBalance++;
            }
        });
        $this->info("Refreshed {$count} customers · {$withBalance} have outstanding balance");
        return 0;
    }
}
