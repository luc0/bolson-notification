<?php

namespace App\Console\Commands;

use App\Contracts\IWhatsapp;
use App\Models\Rental;
use App\Models\WhatsappUser;
use App\Services\AiProcessingService;
use App\Services\ScraperService;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Twilio\Rest\Client;

class AnalyzeRentals extends Command
{
    protected $signature = 'analyze:rentals';
    protected $description = 'Analiza alquileres en distintas inmobiliarias';

    public function __construct(private ScraperService $scraperService, private AiProcessingService $aiProcessingService, private IWhatsapp $IWhatsapp)
    {
        parent::__construct();
    }

    public function handle()
    {
        Log::info("📦 Comando analyze:rentals ejecutado.", ['timestamp' => now()]);

        $allItems = $this->scraperService->scrape();

        // Guardar todo en rental.json
        Log::info('💾 Guardando resultados combinados...');
        Storage::put('rental.json', json_encode($allItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Log::info('✅ Datos guardados con éxito a un json.');

        if (empty($allItems)) {
            Log::warning('⚠️ No hay alquileres para enviar.');
            return;
        }

        $this->IWhatsapp->sendMessage($allItems);

        return Command::SUCCESS;
    }
}
