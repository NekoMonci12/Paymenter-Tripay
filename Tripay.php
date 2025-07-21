<?php

namespace Paymenter\Extensions\Gateways\Tripay;

use App\Classes\Extension\Gateway;
use App\Helpers\ExtensionHelper;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;

class Tripay extends Gateway
{
    public function boot()
    {
        require __DIR__ . '/routes/web.php';
        View::addNamespace('gateways.tripay', __DIR__ . '/resources/views');
    }

    public function getMetadata(): array
    {
        return [
            'display_name' => 'Tripay',
            'version'      => '1.0.0',
            'author'       => 'NekoMonci12',
            'website'      => 'https://github.com/NekoMonci12',
        ];
    }

    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'text',
                'description' => 'Your Tripay API key.',
                'required' => true,
            ],
            [
                'name' => 'private_key',
                'label' => 'Private Key',
                'type' => 'text',
                'description' => 'Your Tripay private key.',
                'required' => true,
            ],
            [
                'name' => 'merchant_code',
                'label' => 'Merchant Code',
                'type' => 'text',
                'description' => 'Your Merchant Code.',
                'required' => true,
            ],
            [
                'name' => 'sandbox',
                'label' => 'Enable Sandbox Mode',
                'type' => 'checkbox',
                'description' => 'Use sandbox environment for testing.',
                'required' => false,
            ],
        ];
    }

    public function pay($invoice, $total)
    {
        $sandboxMode = $this->config('sandbox');
        $orderId = 'PAYMENTER-' . $invoice->id . '-' . substr(hash('sha256', time()), 0, 16);
        if ($sandboxMode) {
            Log::info('Starting Tripay payment process', ['invoice_id' => $invoice->id]);
            Log::debug('Generated order ID', ['order_id' => $orderId]);
        }

        $apiKey = $this->config('api_key');
        $privateKey = $this->config('private_key');
        $merchantCode = $this->config('merchant_code');

        // Generate merchant_ref
        $merchantRef = $orderId;

        // Generate signature
        $signature = hash_hmac('sha256', $merchantCode . $merchantRef . round($total, 2), $privateKey);

        // Endpoint URL
        $url = $sandboxMode
            ? 'https://tripay.co.id/api-sandbox/transaction/create'
            : 'https://tripay.co.id/api/transaction/create';
        
        if ($sandboxMode) {
            Log::debug('Merchant reference', ['merchant_ref' => $merchantRef]);
            Log::debug('Generated signature', ['signature' => $signature]);
            Log::info('API URL', ['url' => $url]);
        }

        // Prepare product details (items)
        $items = collect($invoice->items)->map(function ($item) {
            return [
                'sku' => $item->id ?? uniqid(),
                'name' => $item->description ?? 'Item',
                'price' => round($item->price, 2),
                'quantity' => $item->quantity ?? 1,
                'product_url' => 'https://yourwebsite.com/product/' . $item->id,
                'image_url' => 'https://yourwebsite.com/images/' . $item->id . '.jpg',
            ];
        })->toArray();

        if ($sandboxMode) {
            Log::info('Product items prepared', ['items' => $items]);
        }
        // Build data payload for request
        $data = [
            'method' => 'QRIS2',
            'merchant_ref' => $merchantRef,
            'amount' => round($total, 2),
            'customer_name' => $invoice->customer_name ?? 'Customer',
            'customer_email' => $invoice->customer_email ?? 'customer@example.com',
            'customer_phone' => $invoice->customer_phone ?? '1234567890',
            'order_items' => $items,
            'return_url' => url('/invoices/' . $invoice->id),
            'expired_time' => time() + (24 * 60 * 60), // 24 hours
            'signature' => $signature,
        ];
        if ($sandboxMode) {
            Log::debug('Request payload', ['data' => $data]);
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_FRESH_CONNECT  => true,
            CURLOPT_URL            => $sandboxMode
                ? 'https://tripay.co.id/api-sandbox/transaction/create'
                : 'https://tripay.co.id/api/transaction/create',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
            CURLOPT_FAILONERROR    => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        ]);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        if ($sandboxMode) {
            Log::info('Curl executed', ['response' => $response, 'error' => $error]);
        }

        if ($error) {
            Log::error('Curl error', ['error_message' => $error]);
            return redirect()->back()->with('error', 'Failed to create Tripay transaction.');
        }

        $json = json_decode($response, true);
        if ($sandboxMode) {
            Log::info('Response from Tripay', ['json' => $json]);
        }

        if (isset($json['data']['checkout_url'])) {
            if ($sandboxMode) {
                Log::info('Redirecting to checkout_url', ['checkout_url' => $json['data']['checkout_url']]);
            }
            return redirect($json['data']['checkout_url']);
        } else {
            $errorMsg = isset($json['message']) ? $json['message'] : 'Unknown error';
            Log::error('Failed to get checkout URL', ['response' => $json]);
            return redirect()->back()->with('error', 'Failed to get checkout URL: ' . $errorMsg);
        }
    }

    public function webhook(Request $request)
    {
        // Parse JSON payload
        $data = $request->json()->all();
        // Validate essential fields
        if (isset($data['reference'], $data['status'])) {
            $referenceParts = explode('-', $data['merchant_ref']);
            if (count($referenceParts) !== 3) {
                \Log::warning('Invalid reference format', ['reference' => $data['reference']]);
                return response('Invalid reference format', 400);
            }
            $invoiceId = $referenceParts[1];
            $invoice = \App\Models\Invoice::find($invoiceId);
            if (!$invoice) {
                \Log::error('Invoice not found for webhook', ['invoice_id' => $invoiceId]);
                return response('Invoice not found', 404);
            }

            if (!$invoiceId) {
                \Log::warning('Invalid reference format in webhook.', ['reference' => $data['reference']]);
                return response('Invalid reference', 400);
            }

            if ($data['status'] === 'PAID') {
                $amount = isset($data['amount_received']) ? floatval($data['amount_received']) :
                    (isset($data['total_amount']) ? floatval($data['total_amount']) : null);
                $transactionId = $data['pay_code'] ?? $data['reference'] ?? null;

                if (!$amount || !$transactionId) {
                    \Log::warning('Webhook missing amount or transaction reference.', [
                        'amount' => $amount,
                        'transaction_reference' => $transactionId,
                    ]);
                    return response('Invalid payload', 400);
                }

                try {

                    ExtensionHelper::addPayment($invoiceId, 'Tripay', $amount, null, $transactionId);
                } catch (\Throwable $e) {
                    return response('Error', 500);
                }
            }
        } 
        return response('OK', 200);
    }
}
