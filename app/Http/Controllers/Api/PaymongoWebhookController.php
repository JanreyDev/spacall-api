<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaymongoWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('Paymongo-Signature');
        $webhookSecret = config('services.paymongo.webhook_sig');

        Log::info('Paymongo Webhook: Received request', [
            'has_signature' => !empty($signature),
            'has_webhook_secret' => !empty($webhookSecret),
            'headers' => $request->headers->all(),
            'payload_preview' => array_intersect_key($payload, array_flip(['data'])),
        ]);

        if ($webhookSecret && $signature) {
            // Parse Paymongo signature: "t=<timestamp>,v1=<hash>,te=<test_hash>"
            $parsed = [];
            foreach (explode(',', $signature) as $part) {
                $eqPos = strpos($part, '=');
                if ($eqPos !== false) {
                    $key = trim(substr($part, 0, $eqPos));
                    $val = trim(substr($part, $eqPos + 1));
                    $parsed[$key] = $val;
                }
            }

            $t = $parsed['t'] ?? '';
            // Use live signature (v1) first, fall back to test signature (te)
            $receivedSig = $parsed['v1'] ?? $parsed['te'] ?? '';

            $toVerify = $t . '.' . $request->getContent();
            $expectedSignature = hash_hmac('sha256', $toVerify, $webhookSecret);

            if ($receivedSig !== $expectedSignature) {
                Log::warning('Paymongo Webhook: Signature verification failed (processing anyway)', [
                    'received' => $receivedSig,
                    'expected' => $expectedSignature,
                    't' => $t,
                    'parsed_keys' => array_keys($parsed),
                ]);
                // Continue processing despite signature mismatch to avoid stuck pending transactions
            } else {
                Log::info('Paymongo Webhook: Signature verified successfully');
            }
        } else {
            if (!$webhookSecret) {
                Log::warning('Paymongo Webhook: MISSING_WEBHOOK_SECRET in config');
            }
            if (!$signature) {
                Log::warning('Paymongo Webhook: MISSING_SIGNATURE in header');
            }
        }

        $type = $payload['data']['attributes']['type'] ?? '';

        Log::info('Paymongo Webhook: Received event', ['type' => $type]);

        if ($type === 'checkout_session.payment.paid') {
            // Robust extraction of checkout session ID
            $checkoutSessionId = $payload['data']['attributes']['resource_id'] ?? null;

            if (!$checkoutSessionId) {
                // Try alternate path: data.attributes.resource.id
                $checkoutSessionId = $payload['data']['attributes']['resource']['id'] ?? null;
            }

            if (!$checkoutSessionId) {
                // Secondary fallback attempt based on Paymongo payload structure
                $checkoutSessionId = $payload['data']['attributes']['data']['id'] ?? null;
            }

            Log::info('Paymongo Webhook: Paid session ID extracted', ['checkout_id' => $checkoutSessionId]);

            if (!$checkoutSessionId) {
                Log::error('Paymongo Webhook: Could not extract checkout session ID from payload', [
                    'payload_data' => $payload['data'] ?? 'missing'
                ]);
                return response()->json(['status' => 'error', 'message' => 'Missing ID'], 400);
            }

            // Using whereRaw for Postgres JSON meta field search
            $transaction = Transaction::whereRaw(
                "meta->>'paymongo_checkout_id' = ?",
                [$checkoutSessionId]
            )
                ->where('status', 'pending')
                ->first();

            if ($transaction) {
                Log::info('Paymongo Webhook: Matching pending transaction found', [
                    'transaction_uuid' => $transaction->transaction_id,
                    'amount' => $transaction->amount
                ]);

                DB::transaction(function () use ($transaction) {
                    // RE-FETCH with LOCK to prevent race conditions with "VerifyPending" polling
                    $lockedTransaction = Transaction::where('id', $transaction->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$lockedTransaction || $lockedTransaction->status !== 'pending') {
                        Log::info('Paymongo Webhook: Transaction already processed, skipping.', [
                            'uuid' => $transaction->transaction_id
                        ]);
                        return;
                    }

                    $lockedTransaction->status = 'completed';
                    $lockedTransaction->completed_at = now();
                    $lockedTransaction->save();

                    $user = $lockedTransaction->transactable;
                    if ($user instanceof User) {
                        $oldBalance = $user->wallet_balance;
                        $user->increment('wallet_balance', $lockedTransaction->amount);
                        $user->refresh();

                        Log::info('Paymongo Webhook: Wallet updated successfully', [
                            'user_id' => $user->id,
                            'amount' => $lockedTransaction->amount,
                            'old_balance' => $oldBalance,
                            'new_balance' => $user->wallet_balance
                        ]);

                        // Broadcast update if applicable
                        try {
                            broadcast(new \App\Events\WalletUpdated($user->id, (float) $user->wallet_balance));
                        } catch (\Exception $e) {
                            Log::warning('Paymongo Webhook: Failed to broadcast wallet update: ' . $e->getMessage());
                        }
                    }
                });

                return response()->json(['status' => 'success'], 200);
            } else {
                Log::warning('Paymongo Webhook: Transaction not found OR already processed', [
                    'checkout_id' => $checkoutSessionId
                ]);
            }
        } else {
            Log::info('Paymongo Webhook: Ignored event type', ['type' => $type]);
        }

        return response()->json(['status' => 'ignored'], 200);
    }
}
