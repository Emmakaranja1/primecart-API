<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PesaPalService
{
    private string $consumerKey;
    private string $consumerSecret;
    private string $environment;
    private string $baseUrl;

    public function __construct()
    {
        $this->consumerKey = config('payment.pesapal.consumer_key');
        $this->consumerSecret = config('payment.pesapal.consumer_secret');
        $this->environment = config('payment.pesapal.environment', 'sandbox');

        $this->baseUrl = $this->environment === 'live'
            ? 'https://pay.pesapal.com/v3/api'
            : 'https://cybqa.pesapal.com/pesapalv3/api';
    }

    
    public function createPaymentOrder(Payment $payment, string $email, ?string $callbackUrl = null): array
    {
        try {
            
            if ($this->environment === 'sandbox' || env('ENABLE_PESAPAL_MOCK', false)) {
                Log::info('PesaPal Payment: Using mock response for testing');
                
                $merchantReference = 'ORD-' . $payment->order_id . '-' . strtoupper(Str::random(6));
                $mockTrackingId = $merchantReference;
                $mockCheckoutUrl = 'https://cybqa.pesapal.com/pesapalv3/checkout/' . $mockTrackingId;
                
                $payment->update([
                    'gateway_reference' => $mockTrackingId,
                    'gateway_response' => [
                        'mock' => true,
                        'tracking_id' => $mockTrackingId,
                        'checkout_url' => $mockCheckoutUrl,
                        'order_tracking_id' => $mockTrackingId
                    ],
                    'status' => 'processing'
                ]);

                return [
                    'success' => true,
                    'payment_link' => $mockCheckoutUrl,
                    'tracking_id' => $mockTrackingId,
                    'customer_message' => 'Please complete your payment using provided link (MOCK MODE)'
                ];
            }

            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new \Exception('Failed to obtain PesaPal access token');
            }

            
            $merchantReference = 'ORD-' . $payment->order_id . '-' . strtoupper(Str::random(6));

            $payload = [
                'id' => $merchantReference, 
                'currency' => $payment->currency,
                'amount' => $payment->amount,
                'description' => 'Payment for Order #' . $payment->order_id,
                'callback_url' => $callbackUrl ?? 'https://web-production-e6965.up.railway.app/api/payment/webhooks/pesapal',
                'redirect_mode' => '',
                'notification_id' => '',
                'branch' => 'PrimeCart Online',
                'billing_address' => [
                    'email_address' => $email,
                    'phone_number' => $payment->user->phone_number ?? '',
                    'country_code' => 'KE',
                    'first_name' => $payment->user->username ?? 'Customer',
                    'last_name' => '',
                    'line_1' => 'PrimeCart Online',
                    'line_2' => '',
                    'city' => 'Nairobi',
                    'state' => '',
                    'postal_code' => '',
                    'zip_code' => ''
                ]
            ];

            
            Log::info('PesaPal Request Payload', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'payload' => $payload
            ]);

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . '/Transactions/SubmitOrderRequest', $payload);

            $responseData = $response->json();
            
            
            Log::info('PesaPal API Response', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'status_code' => $response->status(),
                'response_body' => $responseData
            ]);

            if ($response->successful() && isset($responseData['checkout_url'])) {
                $payment->update([
                    'gateway_reference' => $merchantReference,
                    'gateway_response' => $responseData,
                    'status' => 'processing'
                ]);

                return [
                    'success' => true,
                    'payment_link' => $responseData['checkout_url'],
                    'tracking_id' => $responseData['tracking_id'] ?? $merchantReference,
                    'customer_message' => 'Please complete your payment using the provided link'
                ];
            }

            $errorMessage = $responseData['error']['message'] ?? 'Failed to create PesaPal payment order';
            throw new \Exception($errorMessage);

        } catch (\Exception $e) {
            Log::error('PesaPal Payment Order Error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'payload' => $payload ?? null,
                'response' => $responseData ?? null
            ]);

            $payment->update([
                'gateway_response' => $responseData ?? ['error' => $e->getMessage()],
                'failure_reason' => $e->getMessage(),
                'status' => 'failed'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'debug_info' => [
                    'payload' => $payload ?? null,
                    'response' => $responseData ?? null
                ]
            ];
        }
    }

    
    private function getAccessToken(): ?string
    {
        try {
            $payload = [
                'consumer_key' => $this->consumerKey,
                'consumer_secret' => $this->consumerSecret
            ];

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/Auth/RequestToken', $payload);

            $responseData = $response->json();

            return $response->successful() && isset($responseData['token'])
                ? $responseData['token']
                : null;

        } catch (\Exception $e) {
            Log::error('PesaPal Access Token Error', ['error' => $e->getMessage()]);
            return null;
        }
    }

  
    public function checkPaymentStatus(string $orderTrackingId): array
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new \Exception('Failed to obtain PesaPal access token');
            }

            $payload = ['orderTrackingId' => $orderTrackingId];

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . '/GetTransactionStatus', $payload);

            $responseData = $response->json();

            return [
                'success' => $response->successful(),
                'status' => $responseData['status'] ?? 'unknown',
                'data' => $responseData
            ];

        } catch (\Exception $e) {
            Log::error('PesaPal Status Check Error', [
                'order_tracking_id' => $orderTrackingId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    
    public function processIpn(array $ipnData): array
    {
        try {
            Log::info('Processing PesaPal IPN', $ipnData);

            $trackingId = $ipnData['pesapal_transaction_tracking_id'] ?? null;
            $status = $ipnData['pesapal_transaction_status'] ?? null;

            if (!$trackingId) {
                return ['success' => false, 'error' => 'Missing tracking ID'];
            }

            $payment = Payment::where('gateway_reference', $trackingId)->first();
            if (!$payment) {
                return ['success' => false, 'error' => 'Payment not found'];
            }

            if ($status === 'COMPLETED') {
                $payment->markAsCompleted($trackingId);
            } elseif (in_array($status, ['FAILED', 'INVALID'])) {
                $payment->markAsFailed('Payment failed: ' . $status);
            }

            $payment->update(['webhook_data' => $ipnData]);

            return ['success' => true];

        } catch (\Exception $e) {
            Log::error('PesaPal IPN Processing Error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    
    public function getPaymentUrl(int $paymentId): array
    {
        $payment = Payment::findOrFail($paymentId);

        if ($payment->gateway !== 'PesaPal') {
            return ['success' => false, 'error' => 'Invalid payment gateway'];
        }

        $gatewayResponse = $payment->gateway_response;

        return isset($gatewayResponse['checkout_url'])
            ? ['success' => true, 'payment_url' => $gatewayResponse['checkout_url']]
            : ['success' => false, 'error' => 'Payment URL not available'];
    }
}