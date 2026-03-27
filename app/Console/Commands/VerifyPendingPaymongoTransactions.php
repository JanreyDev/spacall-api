<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\User;
use App\Events\WalletUpdated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyPendingPaymongoTransactions extends Command
{
    protected $signature = 'paymongo:verify-pending';
    protected $description = 'Check pending PayMongo transactions and complete any that were actually paid';

    public function handle()
    {
        $paymongoSecret = config('services.paymongo.secret');
        $baseUrl = env('PAYMONGO_BASE_URL', 'https://api.paymongo.com/v1');

        if (!$paymongoSecret) {
            $this->error('PayMongo secret key not configured.');
            return 1;
        }

        // Get all pending deposit transactions that have a paymongo_checkout_id
        $pendingTransactions = Transaction::where('status', 'pending')
            ->where('type', 'deposit')
            ->get()
            ->filter(function ($t) {
                $meta = is_array($t->meta) ? $t->meta : json_decode($t->meta, true);
                return !empty($meta['paymongo_checkout_id']);
            });

        $this->info("Found {$pendingTransactions->count()} pending PayMongo transactions.");

        foreach ($pendingTransactions as $transaction) {
            $meta = is_array($transaction->meta) ? $transaction->meta : json_decode($transaction->meta, true);
            $checkoutId = $meta['paymongo_checkout_id'];

            $this->info("Checking checkout session: {$checkoutId} (Transaction: {$transaction->transaction_id})");

            try {
                // Retrieve the checkout session from PayMongo
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($paymongoSecret . ':'),
                    'Accept' => 'application/json',
                ])->get("{$baseUrl}/checkout_sessions/{$checkoutId}");

                if (!$response->successful()) {
                    $this->warn("  ✗ Failed to retrieve checkout session: HTTP {$response->status()}");
                    continue;
                }

                $sessionData = $response->json()['data'] ?? null;
                if (!$sessionData) {
                    $this->warn("  ✗ No data in response");
                    continue;
                }

                $paymentStatus = $sessionData['attributes']['payment_intent']['attributes']['status'] ?? null;
                $paymentsArr = $sessionData['attributes']['payments'] ?? [];

                // Check if payment was successful
                $isPaid = $paymentStatus === 'succeeded'
                    || !empty($paymentsArr)
                    || ($sessionData['attributes']['status'] ?? '') === 'active'; // active with payments means paid

                // More robust: check if any payment in the payments array has status "paid"
                $hasPaidPayment = false;
                foreach ($paymentsArr as $payment) {
                    $pStatus = $payment['attributes']['status'] ?? '';
                    if ($pStatus === 'paid') {
                        $hasPaidPayment = true;
                        break;
                    }
                }

                if ($hasPaidPayment || $paymentStatus === 'succeeded') {
                    $this->info("  ✓ Payment confirmed! Completing transaction...");

                    DB::transaction(function () use ($transaction) {
                        $transaction->status = 'completed';
                        $transaction->completed_at = now();
                        $transaction->save();

                        $user = $transaction->transactable;
                        if ($user instanceof User) {
                            $oldBalance = $user->wallet_balance;
                            $user->increment('wallet_balance', $transaction->amount);
                            $user->refresh();

                            $this->info("  ✓ Wallet updated: ₱{$oldBalance} → ₱{$user->wallet_balance}");

                            try {
                                broadcast(new WalletUpdated($user->id, (float) $user->wallet_balance));
                            } catch (\Exception $e) {
                                $this->warn("  ! Failed to broadcast: " . $e->getMessage());
                            }
                        }
                    });
                } else {
                    $status = $sessionData['attributes']['status'] ?? 'unknown';
                    $this->info("  - Not yet paid. Session status: {$status}, Payment intent: " . ($paymentStatus ?? 'N/A'));
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Error checking transaction: " . $e->getMessage());
            }
        }

        $this->info('Done.');
        return 0;
    }
}
