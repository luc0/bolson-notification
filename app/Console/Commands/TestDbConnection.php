<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class TestDbConnection extends Command
{
    protected $signature = 'db:test';
    protected $description = 'Test the database connection and display basic config info';

    public function handle()
    {
        $this->info('🔍 Checking DB configuration...');
        $this->line('DB_HOST: ' . config('database.connections.mysql.host'));
        $this->line('DB_PORT: ' . config('database.connections.mysql.port'));
        $this->line('DB_DATABASE: ' . config('database.connections.mysql.database'));
        $this->line('DB_USERNAME: ' . config('database.connections.mysql.username'));

        $this->line("\n📡 Testing DB connection...");
        try {
            $this->line('Using database: ' . DB::connection()->getDatabaseName());
            $result = DB::select('SELECT NOW()');
            $this->info("✅ Connection successful. Current DB time: " . $result[0]->{'NOW()'});
            dump($result);
        } catch (Throwable $e) {
            $this->error("❌ Connection failed: " . $e->getMessage());
        }
    }
}
