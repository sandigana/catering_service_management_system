<?php
// app/Services/Sms/SemaphoreSms.php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** semaphore.co driver. The API key is never written to the log. */
class SemaphoreSms implements SmsGateway
{
    public function __construct(private ?string $apiKey, private string $sender)
    {
    }

    public function send(string $to, string $message): bool
    {
        if (! $this->apiKey) {
            Log::warning('SMS not sent: SEMAPHORE_API_KEY is empty.');

            return false;
        }

        $response = Http::asForm()->timeout(10)->post('https://api.semaphore.co/api/v4/messages', [
            'apikey' => $this->apiKey,
            'number' => $to,
            'message' => $message,
            'sendername' => $this->sender,
        ]);

        if (! $response->successful()) {
            Log::warning('SMS provider rejected the message (HTTP ' . $response->status() . ').');
        }

        return $response->successful();
    }
}
