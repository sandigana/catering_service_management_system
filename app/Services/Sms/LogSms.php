<?php
// app/Services/Sms/LogSms.php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/** Default driver: no provider needed, messages just land in the log. */
class LogSms implements SmsGateway
{
    public function send(string $to, string $message): bool
    {
        Log::info("SMS to {$to}: {$message}");

        return true;
    }
}
