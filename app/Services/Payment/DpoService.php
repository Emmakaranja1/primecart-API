<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DpoService
{
    private string $companyToken;
    private string $serviceType;
    private string $environment;
    private string $baseUrl;

    public function __construct()
    {
        $this->companyToken = config('payment.dpo.company_token');
        $this->serviceType = config('payment.dpo.service_type', '54841');
        $this->environment = config('payment.dpo.environment', 'sandbox');

        
        $this->baseUrl = 'https://secure.3gdirectpay.com';
    }

   
    public function createPaymentToken(Payment $payment, string $email, ?string $callbackUrl = null): array
    {
        try {
            $callbackUrl ??= config('app.url') . '/api/payment/callbacks/dpo';
            $backUrl = config('app.url') . '/payment/cancelled';
            $serviceDate = now()->format('Y/m/d H:i');

            
            if ($this->environment === 'sandbox' && app()->environment('local')) {
                Log::info('DPO Payment: Using mock response for local testing');
                
                $mockTransToken = 'MOCK_' . strtoupper(Str::random(12));
                $paymentUrl = $this->baseUrl . '/payv2.php?ID=' . $mockTransToken;

                $payment->update([
                    'gateway_reference' => $mockTransToken,
                    'gateway_response' => ['mock' => true, 'token' => $mockTransToken],
                    'status' => 'processing'
                ]);

                return [
                    'success' => true,
                    'payment_url' => $paymentUrl,
                    'trans_token' => $mockTransToken,
                    'customer_message' => 'Please complete your payment using the provided link (MOCK MODE)'
                ];
            }

            $xmlPayload = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<API3G>
    <CompanyToken>{$this->companyToken}</CompanyToken>
    <Request>createToken</Request>
    <Transaction>
        <PaymentAmount>{$payment->amount}</PaymentAmount>
        <PaymentCurrency>{$payment->currency}</PaymentCurrency>
        <CompanyRef>{$payment->transaction_id}</CompanyRef>
        <RedirectURL>{$callbackUrl}</RedirectURL>
        <BackURL>{$backUrl}</BackURL>
        <CompanyRefUnique>0</CompanyRefUnique>
    </Transaction>
    <Services>
        <Service>
            <ServiceType>{$this->serviceType}</ServiceType>
            <ServiceDescription>Payment for Order #{$payment->order_id}</ServiceDescription>
            <ServiceDate>{$serviceDate}</ServiceDate>
        </Service>
    </Services>
</API3G>
XML;

            $response = Http::withHeaders([
                'Content-Type' => 'application/xml'
            ])->withBody($xmlPayload, 'application/xml')
              ->post($this->baseUrl . '/API/v6/');

            
            Log::info('DPO API Response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);

            
            if (!$response->successful()) {
                throw new \Exception("DPO API returned HTTP {$response->status()}: {$response->body()}");
            }

            
            $xml = @simplexml_load_string($response->body());

            if (!$xml) {
                throw new \Exception('Invalid XML response from DPO API: ' . $response->body());
            }

            $resultCode = (string)$xml->Result ?? '';
            $transToken = (string)$xml->TransToken ?? null;
            $resultExplanation = (string)$xml->ResultExplanation ?? 'Unknown error';

            if ($resultCode === '000' && $transToken) {
                $paymentUrl = $this->baseUrl . '/payv2.php?ID=' . $transToken;

                $payment->update([
                    'gateway_reference' => $transToken,
                    'gateway_response' => $response->body(),
                    'status' => 'processing'
                ]);

                return [
                    'success' => true,
                    'payment_url' => $paymentUrl,
                    'trans_token' => $transToken
                ];
            }

            throw new \Exception($resultExplanation);

        } catch (\Exception $e) {
            Log::error('DPO Payment Token Error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'response' => $response->body() ?? null,
                'payload' => $xmlPayload ?? null,
            ]);

            $payment->update([
                'failure_reason' => $e->getMessage(),
                'status' => 'failed',
                'gateway_response' => $response->body() ?? null
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    
    public function verifyPayment(string $transToken): array
    {
        try {
            
            if ($this->environment === 'sandbox' && app()->environment('local')) {
                Log::info('DPO Verification: Using mock response for local testing');
                
                $payment = Payment::where('gateway_reference', $transToken)->first();
                if ($payment) {
                    
                    $payment->markAsCompleted($transToken);
                    
                    return [
                        'success' => true,
                        'status' => 'success',
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'verification_data' => ['mock' => true, 'status' => 'success']
                    ];
                }
                
                return [
                    'success' => false,
                    'error' => 'Payment not found'
                ];
            }

            $payload = [
                'companyToken' => $this->companyToken,
                'transactionToken' => $transToken
            ];

            $response = Http::asForm()->post($this->baseUrl . '/api/v2/verify-payment', $payload);
            $responseData = $response->json();

            if ($response->successful() && ($responseData['success'] ?? false)) {
                $payment = Payment::where('gateway_reference', $transToken)->first();
                if ($payment) {
                    $status = $responseData['transactionStatus'] ?? 'unknown';
                    if ($status === 'success') {
                        $payment->markAsCompleted($transToken);
                    } elseif ($status === 'failed') {
                        $payment->markAsFailed('Payment failed');
                    }
                    $payment->update(['gateway_response' => $responseData]);
                }

                return [
                    'success' => true,
                    'status' => $responseData['transactionStatus'] ?? 'unknown',
                    'amount' => $responseData['amount'] ?? 0,
                    'currency' => $responseData['currency'] ?? 'KES',
                    'verification_data' => $responseData
                ];
            }

            throw new \Exception($responseData['message'] ?? 'Failed to verify DPO payment');

        } catch (\Exception $e) {
            Log::error('DPO Payment Verification Error', [
                'trans_token' => $transToken,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    
    public function processWebhook(array $webhookData): array
    {
        try {
            $transToken = $webhookData['transToken'] ?? null;
            $status = $webhookData['transactionStatus'] ?? null;

            if (!$transToken) {
                return ['success' => false, 'error' => 'Missing transToken'];
            }

            $payment = Payment::where('gateway_reference', $transToken)->first();

            if (!$payment) {
                return ['success' => false, 'error' => 'Payment not found'];
            }

            if ($status === 'success') {
                $payment->markAsCompleted($transToken);
            } else {
                $payment->markAsFailed('Payment failed: ' . $status);
            }

            $payment->update(['webhook_data' => $webhookData]);

            return ['success' => true];

        } catch (\Exception $e) {
            Log::error('DPO Webhook Processing Error', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    
    public function getPaymentUrl(int $paymentId): array
    {
        try {
            $payment = Payment::findOrFail($paymentId);

            if ($payment->gateway !== 'DPO') {
                throw new \Exception('Invalid payment gateway');
            }

            $gatewayResponse = $payment->gateway_response;

            if (isset($gatewayResponse['paymentURL'])) {
                return [
                    'success' => true,
                    'payment_url' => $gatewayResponse['paymentURL']
                ];
            }

            return [
                'success' => false,
                'error' => 'Payment URL not available'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}