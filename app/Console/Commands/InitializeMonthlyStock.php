<?php

namespace App\Console\Commands;

use App\Services\StockService;
use Illuminate\Console\Command;

class InitializeMonthlyStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:initialize-stock {date? : The date to initialize (e.g. 2026-06-01)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initializes medicine stock for all users at the start of a month';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date');
        $displayDate = $date ?: 'current month';

        $this->info("Starting stock initialization for {$displayDate}...");
        StockService::initializeAllUsersMonthlyStock($date);
        $this->info('Stock initialization completed successfully.');
    }

}
