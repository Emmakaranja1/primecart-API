<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    private $consumerKey;
    private $consumerSecret;
    private $shortcode;
    private $passkey;
    private $environment;
    private $baseUrl;

    public function __construct()
    {
        $this->consumerKey = config('services.mpesa.consumer_key');
        $this->consumerSecret = config('services.mpesa.consumer_secret');
        $this->shortcode = config('services.mpesa.shortcode');
        $this->passkey = config('services.mpesa.passkey');
        $this->environment = config('services.mpesa.environment', 'sandbox');

        $this->baseUrl = $this->environment === 'live' 
            ? 'https://api.safaricom.co.ke' 
            : 'https://sandbox.safaricom.co.ke';
    }

    public function initiateStkPush(Payment $payment, $phoneNumber, $callbackUrl = null)
    {
        try {
            $accessToken = $this->getAccessToken();
            
            if (!$accessToken) {
                throw new \Exception('Failed to obtain M-PESA access token');
            }

            $timestamp = date('YmdHis');
            $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

            $requestBody = [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => $payment->amount,
                'PartyA' => $this->formatPhoneNumber($phoneNumber),
                'PartyB' => $this->shortcode,
                'PhoneNumber' => $this->formatPhoneNumber($phoneNumber),
                'CallBackURL' => $callbackUrl ?? config('app.url') . '/api/payment/webhooks/mpesa',
                'AccountReference' => $payment->transaction_id,
                'TransactionDesc' => 'Payment for Order #' . $payment->order_id,
                'Remark' => 'PrimeCart Payment'
            ];

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . '/mpesa/stkpush/v1/processrequest', $requestBody);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['ResponseCode']) && $responseData['ResponseCode'] === '0') {
                $payment->update([
                    'checkout_request_id' => $responseData['CheckoutRequestID'],
                    'gateway_response' => $responseData,
                    'status' => 'processing'
                ]);

                return [
                    'success' => true,
                    'checkout_request_id' => $responseData['CheckoutRequestID'],
                    'merchant_request_id' => $responseData['MerchantRequestID'],
                    'customer_message' => $responseData['CustomerMessage'] ?? 'Please check your phone to complete the payment'
                ];
            } else {
                $errorMessage = $responseData['errorMessage'] ?? $responseData['CustomerMessage'] ?? 'Failed to initiate M-PESA payment';
                
                $payment->update([
                    'gateway_response' => $responseData,
                    'failure_reason' => $errorMessage,
                    'status' => 'failed'
                ]);

                throw new \Exception($errorMessage);
            }

        } catch (\Exception $e) {
            Log::error('M-PESA STK Push Error', [
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

    public function checkTransactionStatus(Payment $payment)
    {
        try {
            if (!$payment->checkout_request_id) {
                throw new \Exception('No checkout request ID found for this payment');
            }

            $accessToken = $this->getAccessToken();
            
            if (!$accessToken) {
                throw new \Exception('Failed to obtain M-PESA access token');
            }

            $timestamp = date('YmdHis');
            $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

            $requestBody = [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $payment->checkout_request_id
            ];

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . '/mpesa/stkpushquery/v1/query', $requestBody);

            $responseData = $response->json();

            if ($response->successful()) {
                $this->processStatusResponse($payment, $responseData);
                
                return [
                    'success' => true,
                    'status' => $payment->status,
                    'response' => $responseData
                ];
            } else {
                throw new \Exception('Failed to check transaction status');
            }

        } catch (\Exception $e) {
            Log::error('M-PESA Status Check Error', [
                'payment_id' => $payment->id,
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
            Log::info('Processing M-PESA Webhook', $webhookData);

            $body = $webhookData['Body'] ?? [];
            $stkCallback = $body['stkCallback'] ?? [];

            $merchantRequestId = $stkCallback['MerchantRequestID'] ?? null;
            $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;
            $resultCode = $stkCallback['ResultCode'] ?? null;
            $resultDesc = $stkCallback['ResultDesc'] ?? '';

            if (!$checkoutRequestId) {
                Log::error('M-PESA Webhook: Missing CheckoutRequestID', $webhookData);
                return ['success' => false, 'error' => 'Missing CheckoutRequestID'];
            }

            $payment = Payment::where('checkout_request_id', $checkoutRequestId)->first();

            if (!$payment) {
                Log::error('M-PESA Webhook: Payment not found', ['checkout_request_id' => $checkoutRequestId]);
                return ['success' => false, 'error' => 'Payment not found'];
            }

            if ($resultCode === '0') {
                $payment->markAsCompleted();
                
                $callbackMetadata = $stkCallback['CallbackMetadata'] ?? [];
                $items = $callbackMetadata['Item'] ?? [];
                
                $mpesaReceipt = null;
                $transactionDate = null;
                $phoneNumber = null;

                foreach ($items as $item) {
                    switch ($item['Name'] ?? '') {
                        case 'MpesaReceiptNumber':
                            $mpesaReceipt = $item['Value'] ?? null;
                            break;
                        case 'TransactionDate':
                            $transactionDate = $item['Value'] ?? null;
                            break;
                        case 'PhoneNumber':
                            $phoneNumber = $item['Value'] ?? null;
                            break;
                    }
                }

                $payment->update([
                    'gateway_reference' => $mpesaReceipt,
                    'webhook_data' => $webhookData
                ]);

                Log::info('M-PESA Payment Completed', [
                    'payment_id' => $payment->id,
                    'mpesa_receipt' => $mpesaReceipt
                ]);

            } else {
                $payment->markAsFailed($resultDesc);
                $payment->update(['webhook_data' => $webhookData]);

                Log::error('M-PESA Payment Failed', [
                    'payment_id' => $payment->id,
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc
                ]);
            }

            return ['success' => true];

        } catch (\Exception $e) {
            Log::error('M-PESA Webhook Processing Error', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getAccessToken()
    {
        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get($this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials');

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('M-PESA Access Token Error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function formatPhoneNumber($phoneNumber)
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        if (strlen($phoneNumber) === 10 && $phoneNumber[0] === '0') {
            return '254' . substr($phoneNumber, 1);
        }
        
        if (str_starts_with($phoneNumber, '+254')) {
            return substr($phoneNumber, 1);
        }
        
        if (strlen($phoneNumber) === 12 && substr($phoneNumber, 0, 3) === '254') {
            return $phoneNumber;
        }
        
        return $phoneNumber;
    }

    private function processStatusResponse(Payment $payment, $responseData)
    {
        $resultCode = $responseData['ResultCode'] ?? null;
        $resultDesc = $responseData['ResultDesc'] ?? '';

        if ($resultCode === '0') {
            $payment->markAsCompleted();
            
            $callbackMetadata = $responseData['CallbackMetadata'] ?? [];
            $items = $callbackMetadata['Item'] ?? [];
            
            $mpesaReceipt = null;
            
            foreach ($items as $item) {
                if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                    $mpesaReceipt = $item['Value'] ?? null;
                    break;
                }
            }

            if ($mpesaReceipt) {
                $payment->update(['gateway_reference' => $mpesaReceipt]);
            }

        } else {
            $payment->markAsFailed($resultDesc);
        }

        $payment->update(['gateway_response' => $responseData]);
    }
}