<?php

namespace App\Http\Controllers;

use App\Actions\Order\SyncOrdersAction;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle Horoshop webhook
     */
    public function horoshop(Request $request, Shop $shop)
    {
        return $this->handleWebhook($request, $shop, 'horoshop');
    }

    /**
     * Handle Prom.ua webhook
     */
    public function prom(Request $request, Shop $shop)
    {
        return $this->handleWebhook($request, $shop, 'prom_ua');
    }

    private function handleWebhook(Request $request, Shop $shop, string $type): Response
    {
        $signature = $request->header('X-Signature');
        $event = $request->input('event');

        // Verify webhook signature if provided
        if ($signature && !$this->verifySignature($request, $shop, $signature)) {
            Log::warning('Invalid webhook signature', [
                'shop_id' => $shop->id,
                'event' => $event,
            ]);

            return response('Invalid signature', 403);
        }

        try {
            // Log webhook event
            Log::info('Webhook received', [
                'shop_id' => $shop->id,
                'type' => $type,
                'event' => $event,
            ]);

            // Handle specific events
            switch ($event) {
                case 'order.created':
                case 'order.updated':
                case 'order.status_changed':
                    // Sync orders for this shop
                    $action = app(SyncOrdersAction::class);
                    $action->execute($shop);
                    break;

                default:
                    Log::info('Unhandled webhook event', [
                        'shop_id' => $shop->id,
                        'event' => $event,
                    ]);
            }

            return response()->json(['success' => true], 200);
        } catch (\Throwable $e) {
            Log::error('Webhook processing failed', [
                'shop_id' => $shop->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Verify webhook signature using HMAC
     */
    private function verifySignature(Request $request, Shop $shop, string $signature): bool
    {
        $payload = $request->getContent();
        $apiSecret = $shop->api_credentials['api_secret'] ?? null;

        if (!$apiSecret) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $apiSecret);

        return hash_equals($expectedSignature, $signature);
    }
}
