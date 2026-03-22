<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ClearTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:tables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
            Schema::disableForeignKeyConstraints();

            DB::table('cart_items')->truncate();
            DB::table('carts')->truncate();
            DB::table('order_items')->truncate();
            DB::table('orders')->truncate();
            DB::table('conversations')->truncate();
            DB::table('customers')->truncate();
            DB::table('messages')->truncate();
            DB::table('payments')->truncate();
            DB::table('webhook_events')->truncate();

            Schema::enableForeignKeyConstraints();

            $this->info('Tables cleared successfully.');
    }
}
