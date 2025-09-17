<?php

namespace App\Console\Commands;

use App\Models\SyncJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendPendingFailedSyncJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-jobs:send-pending-failed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send SyncJob records with pending or failed status to the external API every 10 minutes';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $syncJobs = SyncJob::whereIn('status', ['pending', 'failed'])->get();

        if ($syncJobs->isEmpty()) {
            $this->info('No pending or failed SyncJobs found.');
            Log::info('No pending or failed SyncJobs found.');
            return;
        }

        $apiUrl = env('TASWERA_API_URL') . '/api/sync-jobs';
        $apiToken = env('TASWERA_API_TOKEN');

        $this->info("🔍 API URL: {$apiUrl}");
        $this->info("🔍 API Token: {$apiToken}");

        foreach ($syncJobs as $syncJob) {
            $payload = [
                'employeeName'    => $syncJob->employeeName,
                'pay_amount'      => (float) $syncJob->pay_amount,
                'orderprefixcode' => $syncJob->orderprefixcode,
                'status'          => 'synced', 
                'shift_name'      => $syncJob->shift_name,
                'orderphone'      => $syncJob->orderphone,
                'number_of_photos' => (int) $syncJob->number_of_photos,
            ];

            try {
                $response = Http::withToken($apiToken)
                    ->timeout(15)
                    ->retry(3, 2000)
                    ->withoutVerifying() // Disable SSL verification
                    ->post($apiUrl, $payload);

                if ($response->successful()) {
                    $this->info("✅ Successfully sent SyncJob ID {$syncJob->id} to API.");
                    Log::info("Successfully sent SyncJob ID {$syncJob->id} to API.", [
                        'payload'  => $payload,
                        'response' => $response->json(),
                    ]);

                    $syncJob->update(['status' => 'synced']);
                } else {
                    $this->error("❌ Failed to send SyncJob ID {$syncJob->id} to API. Status: {$response->status()}");
                    Log::error("Failed to send SyncJob ID {$syncJob->id} to API.", [
                        'payload'  => $payload,
                        'status'   => $response->status(),
                        'response' => $response->body(),
                    ]);

                    $syncJob->update(['status' => 'failed']);
                }
            } catch (\Exception $e) {
                $this->error("⚠️ Exception occurred while sending SyncJob ID {$syncJob->id}: {$e->getMessage()}");
                Log::error("Exception occurred while sending SyncJob ID {$syncJob->id}: {$e->getMessage()}");

                $syncJob->update(['status' => 'failed']);
            }
        }
    }
}
