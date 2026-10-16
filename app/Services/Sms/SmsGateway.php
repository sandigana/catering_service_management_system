<?php
// app/Services/Sms/SmsGateway.php

namespace App\Services\Sms;

interface SmsGateway
{
    /** Returns true when the provider accepted the message. */
    public function send(string $to, string $message): bool;
}
