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
     * Allowed IP addresses for webhooks
     * Note: Add actual IPs from Horoshop and Prom.ua support
     */
    private const ALLOWED_IPS = [
        // Horoshop IPs (to be confirmed with support)
        // '194.44.xxx.xxx',

        // Prom.ua IPs (to be confirmed with support)
        // '195.248.xxx.xxx',

        // Allow localhost for testing
        '127.0.0.1',
        '::1',
    ];

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
        $clientIp = $request->ip();

        // Security: Either IP must be whitelisted OR signature must be valid
        $isIpAllowed = $this->isAllowedIp($clientIp);
        $isSignatureValid = $signature && $this->verifySignature($request, $shop, $signature);

        if (!$isIpAllowed && !$isSignatureValid) {
            Log::warning('Webhook authorization failed', [
                'shop_id' => $shop->id,
                'event' => $event,
                'ip' => $clientIp,
                'has_signature' => (bool) $signature,
            ]);

            return response('Unauthorized', 403);
        }

        // Log if signature was invalid but IP was allowed (for debugging)
        if ($signature && !$isSignatureValid && $isIpAllowed) {
            Log::info('Webhook signature invalid but IP is whitelisted', [
                'shop_id' => $shop->id,
                'ip' => $clientIp,
            ]);
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

    /**
     * Check if IP is in the allowed list
     */
    private function isAllowedIp(string $ip): bool
    {
        // Also check for IPs behind proxies (Cloudflare, load balancers)
        // In production, configure trusted proxies in TrustProxies middleware

        return in_array($ip, self::ALLOWED_IPS, true);
    }
}
