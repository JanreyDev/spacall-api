<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $url;
    protected string $apiKey;

    public function __construct()
    {
        $this->url = config('services.txtbox.url', 'https://ws-v2.txtbox.com/messaging/v1/sms/push');
        $this->apiKey = config('services.txtbox.api_key');
    }

    /**
     * Send SMS via TxtBox.
     *
     * @param string $mobileNumber
     * @param string $message
     * @return bool
     */
    public function sendSms(string $mobileNumber, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'X-TxtBox-Auth' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->url, [
                'message' => $message,
                'number' => $mobileNumber,
            ]);

            if ($response->successful()) {
                Log::info("SMS sent to {$mobileNumber}: {$message}");
                return true;
            }

            Log::error("Failed to send SMS to {$mobileNumber}. Status: {$response->status()}, Response: {$response->body()}");
            return false;
        } catch (\Exception $e) {
            Log::error("SmsService Exception: " . $e->getMessage());
            return false;
        }
    }
}
