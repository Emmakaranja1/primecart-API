<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FlutterwaveService
{
    private $publicKey;
    private $secretKey;
    private $encryptionKey;
    private $environment;
    private $baseUrl;

    public function __construct()
    {
        $this->publicKey = config('payment.flutterwave.public_key');
        $this->secretKey = config('payment.flutterwave.secret_key');
        $this->encryptionKey = config('payment.flutterwave.encryption_key');
        $this->environment = config('payment.flutterwave.environment', 'sandbox');

        $this->baseUrl = $this->environment === 'live' 
            ? 'https://api.flutterwave.com/v3' 
            : 'https://api.flutterwave.com/v3';
    }

    public function initiatePayment(Payment $payment, $email, $callbackUrl = null)
    {
        try {
            $txRef = $payment->transaction_id;
            $amount = $payment->amount;
            $currency = $payment->currency;

            $payload = [
                'tx_ref' => $txRef,
                'amount' => $amount,
                'currency' => $currency,
                'email' => $email,
                'customer' => [
                    'email' => $email,
                    'name' => $payment->user->username ?? 'Customer',
                ],
                'customizations' => [
                    'title' => 'PrimeCart Payment',
                    'description' => 'Payment for Order #' . $payment->order_id,
                    'logo' => null
                ],
                'redirect_url' => $callbackUrl ?? 'https://web-production-e6965.up.railway.app/api/payment/callbacks/flutterwave',
                'payment_options' => 'card,banktransfer,ussd,mpesa',
                'meta' => [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'user_id' => $payment->user_id
                ]
            ];

           

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/payments', $payload);

            $responseData = $response->json();

            if ($response->successful() && $responseData['status'] === 'success') {
                
                $payment->update([
                    'gateway_response' => $responseData,
                    'status' => 'processing'
                ]);

                return [
                    'success' => true,
                    'payment_link' => $responseData['data']['link'] ?? null,
                    'transaction_id' => $txRef,
                    'flutterwave_id' => null, 
                    'customer_message' => 'Please complete your payment using the provided link'
                ];
            } else {
                $errorMessage = $responseData['message'] ?? 'Failed to initiate Flutterwave payment';
                
                $payment->update([
                    'gateway_response' => $responseData,
                    'failure_reason' => $errorMessage,
                    'status' => 'failed'
                ]);

                throw new \Exception($errorMessage);
            }

        } catch (\Exception $e) {
            Log::error('Flutterwave Payment Initiation Error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $payment->update([
                'failure_reason' => $e->getMessage(),
                'status' => 'failed'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function verifyPayment($transactionId)
    {
        try {
            
            $payment = Payment::where('transaction_id', $transactionId)
                ->orWhere('gateway_reference', $transactionId)
                ->orWhere('gateway_response->data->tx_ref', $transactionId)
                ->orWhere('gateway_response->data->id', $transactionId)
                ->first();
            
            if (!$payment) {
                return [
                    'success' => false,
                    'error' => 'Payment not found'
                ];
            }

            
            if ($payment->status === 'processing' && !$payment->gateway_reference) {
                return [
                    'success' => false,
                    'error' => 'Payment not yet completed. Please complete the payment using the provided link first.',
                    'payment_status' => $payment->status,
                    'note' => 'Complete payment via Flutterwave link, then verify again.'
                ];
            }

            
            $flutterwaveId = null;
            if ($payment->gateway_reference) {
                
                if (strpos($payment->gateway_reference, 'FW-') === 0) {
                    
                    if (is_numeric($payment->transaction_id)) {
                        $flutterwaveId = $payment->transaction_id;
                    } elseif ($payment->gateway_response && isset($payment->gateway_response['data']['id'])) {
                        $flutterwaveId = $payment->gateway_response['data']['id'];
                    }
                } else {
                    
                    $flutterwaveId = $payment->gateway_reference;
                }
            }
            
            if ($flutterwaveId) {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/json'
                ])->get($this->baseUrl . "/transactions/{$flutterwaveId}/verify");

                $responseData = $response->json();

                if ($response->successful() && $responseData['status'] === 'success') {
                    $transactionData = $responseData['data'];
                    $txRef = $transactionData['tx_ref'] ?? null;
                    
                    if ($txRef) {
                        $payment = Payment::where('transaction_id', $txRef)->first();
                        
                        if ($payment) {
                            $this->processVerificationResponse($payment, $transactionData);
                        }
                    }

                    return [
                        'success' => true,
                        'status' => $transactionData['status'] ?? 'unknown',
                        'amount' => $transactionData['amount'] ?? 0,
                        'currency' => $transactionData['currency'] ?? 'NGN',
                        'payment_status' => $transactionData['status'] === 'successful' ? 'completed' : 'pending',
                        'transaction_data' => $transactionData
                    ];
                } else {
                    throw new \Exception('Failed to verify Flutterwave payment');
                }
            }

            
            return [
                'success' => false,
                'error' => 'Payment verification not available. Payment may still be processing.',
                'payment_status' => $payment->status,
                'gateway_reference' => $payment->gateway_reference
            ];

        } catch (\Exception $e) {
            Log::error('Flutterwave Payment Verification Error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function processWebhook($webhookData)
    {
        try {
            Log::info('Processing Flutterwave Webhook', $webhookData);

            $event = $webhookData['event'] ?? null;
            $data = $webhookData['data'] ?? [];

            if ($event === 'charge.completed') {
                return $this->processSuccessfulCharge($data);
            } elseif ($event === 'charge.failed') {
                return $this->processFailedCharge($data);
            } elseif ($event === 'transfer.completed') {
                return $this->processTransfer($data);
            }

            return ['success' => true, 'message' => 'Webhook event processed'];

        } catch (\Exception $e) {
            Log::error('Flutterwave Webhook Processing Error', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function processSuccessfulCharge($chargeData)
    {
        $txRef = $chargeData['tx_ref'] ?? null;
        
        if (!$txRef) {
            Log::error('Flutterwave Webhook: Missing transaction reference', $chargeData);
            return ['success' => false, 'error' => 'Missing transaction reference'];
        }

        $payment = Payment::where('transaction_id', $txRef)->first();

        if (!$payment) {
            Log::error('Flutterwave Webhook: Payment not found', ['tx_ref' => $txRef]);
            return ['success' => false, 'error' => 'Payment not found'];
        }

        if ($payment->isCompleted()) {
            Log::info('Flutterwave Webhook: Payment already completed', ['payment_id' => $payment->id]);
            return ['success' => true, 'message' => 'Payment already completed'];
        }

        $payment->markAsCompleted($chargeData['id'] ?? null);
        $payment->update([
            'gateway_reference' => $chargeData['id'] ?? null,
            'webhook_data' => $chargeData
        ]);

        Log::info('Flutterwave Payment Completed', [
            'payment_id' => $payment->id,
            'flutterwave_id' => $chargeData['id'] ?? null,
            'amount' => $chargeData['amount'] ?? 0
        ]);

        return ['success' => true, 'message' => 'Payment completed successfully'];
    }

    private function processFailedCharge($chargeData)
    {
        $txRef = $chargeData['tx_ref'] ?? null;
        
        if (!$txRef) {
            Log::error('Flutterwave Webhook: Missing transaction reference', $chargeData);
            return ['success' => false, 'error' => 'Missing transaction reference'];
        }

        $payment = Payment::where('transaction_id', $txRef)->first();

        if (!$payment) {
            Log::error('Flutterwave Webhook: Payment not found', ['tx_ref' => $txRef]);
            return ['success' => false, 'error' => 'Payment not found'];
        }

        $failureReason = $chargeData['gateway_response']['message'] ?? 'Payment failed';
        
        $payment->markAsFailed($failureReason);
        $payment->update(['webhook_data' => $chargeData]);

        Log::error('Flutterwave Payment Failed', [
            'payment_id' => $payment->id,
            'failure_reason' => $failureReason
        ]);

        return ['success' => true, 'message' => 'Payment failure processed'];
    }

    private function processTransfer($transferData)
    {
        Log::info('Flutterwave Transfer Webhook', $transferData);
        return ['success' => true, 'message' => 'Transfer webhook processed'];
    }

    private function processVerificationResponse(Payment $payment, $transactionData)
    {
        $status = $transactionData['status'] ?? 'unknown';

        if ($status === 'successful') {
            $payment->markAsCompleted($transactionData['id'] ?? null);
            $payment->update(['gateway_reference' => $transactionData['id'] ?? null]);
        } elseif ($status === 'failed') {
            $failureReason = $transactionData['gateway_response']['message'] ?? 'Payment failed';
            $payment->markAsFailed($failureReason);
        }

        $payment->update(['gateway_response' => $transactionData]);
    }

    private function encryptPayload($payload)
    {
        if (!$this->encryptionKey) {
            return $payload;
        }

        $encrypted = openssl_encrypt(
            json_encode($payload),
            'AES-256-ECB',
            $this->encryptionKey,
            OPENSSL_RAW_DATA
        );

        return [
            'client' => base64_encode($encrypted)
        ];
    }

    public function getPaymentLink($paymentId)
    {
        try {
            $payment = Payment::findOrFail($paymentId);
            
            if ($payment->gateway !== 'Flutterwave') {
                throw new \Exception('Invalid payment gateway');
            }

            $gatewayResponse = $payment->gateway_response;
            
            if (isset($gatewayResponse['data']['link'])) {
                return [
                    'success' => true,
                    'payment_link' => $gatewayResponse['data']['link']
                ];
            }

            return [
                'success' => false,
                'error' => 'Payment link not available'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}