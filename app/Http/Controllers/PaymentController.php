<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\MpesaService;
use App\Services\Payment\FlutterwaveService;
use App\Services\Payment\DpoService;
use App\Services\Payment\PesaPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    private $mpesaService;
    private $flutterwaveService;
    private $dpoService;
    private $pesapalService;

    public function __construct(MpesaService $mpesaService, FlutterwaveService $flutterwaveService, DpoService $dpoService, PesaPalService $pesapalService)
    {
        $this->mpesaService = $mpesaService;
        $this->flutterwaveService = $flutterwaveService;
        $this->dpoService = $dpoService;
        $this->pesapalService = $pesapalService;
    }
    public function initiate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'gateway' => 'required|in:MPESA,Flutterwave,DPO,PesaPal',
            'phone_number' => 'required_if:gateway,MPESA|string|max:20',
            'email' => 'required_if:gateway,Flutterwave,PesaPal|email|max:255',
            'callback_url' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Order has already been paid'
            ], 400);
        }

        $existingPayment = Payment::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existingPayment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment already in progress for this order',
                'payment_id' => $existingPayment->id
            ], 400);
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => $request->gateway,
            'status' => 'pending',
            'amount' => $order->total_amount,
            'currency' => 'KES',
            'transaction_id' => 'PAY-' . strtoupper(Str::random(12)),
        ]);

        $gatewayResponse = $this->processGatewayPayment($payment, $request);

        return response()->json([
            'success' => true,
            'message' => 'Payment initiated successfully',
            'data' => [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'gateway' => $payment->gateway,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'gateway_response' => $gatewayResponse,
            ]
        ], 201);
    }

    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|exists:payments,id',
            'reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $payment = Payment::where('id', $request->payment_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        $verificationResult = $this->verifyGatewayPayment($payment, $request->reference);

        return response()->json([
            'success' => true,
            'message' => 'Payment status retrieved',
            'data' => [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'gateway' => $payment->gateway,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at,
                'verification_result' => $verificationResult,
            ]
        ]);
    }

    public function methods()
    {
        $paymentMethods = [
            [
                'name' => 'MPESA',
                'display_name' => 'M-PESA Mobile Money',
                'description' => 'Pay using M-PESA mobile money service',
                'currencies' => ['KES'],
                'requires_phone' => true,
                'requires_email' => false,
                'active' => true,
            ],
            [
                'name' => 'Flutterwave',
                'display_name' => 'Flutterwave',
                'description' => 'Pay using Flutterwave payment gateway',
                'currencies' => ['KES', 'USD', 'EUR', 'GBP'],
                'requires_phone' => false,
                'requires_email' => true,
                'active' => true,
            ],
            [
                'name' => 'DPO',
                'display_name' => 'DPO Group',
                'description' => 'Pay using DPO payment gateway',
                'currencies' => ['KES', 'USD', 'EUR'],
                'requires_phone' => false,
                'requires_email' => true,
                'active' => true,
            ],
            [
                'name' => 'PesaPal',
                'display_name' => 'PesaPal',
                'description' => 'Pay using PesaPal payment gateway',
                'currencies' => ['KES', 'USD', 'EUR'],
                'requires_phone' => false,
                'requires_email' => true,
                'active' => true,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'payment_methods' => $paymentMethods
            ]
        ]);
    }

    public function status($paymentId)
    {
        $user = auth()->user();
        $payment = Payment::where('id', $paymentId)
            ->where('user_id', $user->id)
            ->with('order')
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'payment' => [
                    'id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'gateway' => $payment->gateway,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'paid_at' => $payment->paid_at,
                    'failed_at' => $payment->failed_at,
                    'failure_reason' => $payment->failure_reason,
                    'created_at' => $payment->created_at,
                ],
                'order' => [
                    'id' => $payment->order->id,
                    'transaction_reference' => $payment->order->transaction_reference,
                    'payment_status' => $payment->order->payment_status,
                    'status' => $payment->order->status,
                ]
            ]
        ]);
    }

    public function mpesaStkPush(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'phone_number' => 'required|string|max:20',
            'callback_url' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            Log::error('M-Pesa Validation Failed', [
                'request_data' => $request->all(),
                'errors' => $validator->errors(),
                'order_id' => $request->order_id,
                'phone_number' => $request->phone_number,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
                'debug' => [
                    'request_data' => $request->all(),
                    'order_id' => $request->order_id,
                    'phone_number' => $request->phone_number,
                    'user_id' => auth()->id(),
                    'order_exists' => Order::where('id', $request->order_id)->where('user_id', auth()->id())->exists()
                ]
            ], 422);
        }

        $user = auth()->user();
        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Order has already been paid'
            ], 400);
        }

        $existingPayment = Payment::where('order_id', $order->id)
            ->where('gateway', 'MPESA')
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existingPayment) {
            return response()->json([
                'success' => false,
                'message' => 'M-PESA payment already in progress for this order',
                'payment_id' => $existingPayment->id
            ], 400);
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'MPESA',
            'status' => 'pending',
            'amount' => $order->total_amount,
            'currency' => 'KES',
            'transaction_id' => 'MPESA-' . strtoupper(Str::random(12)),
        ]);

        $result = $this->mpesaService->initiateStkPush(
            $payment, 
            $request->phone_number, 
            $request->callback_url
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'M-PESA STK Push initiated successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'checkout_request_id' => $result['checkout_request_id'],
                    'merchant_request_id' => $result['merchant_request_id'],
                    'customer_message' => $result['customer_message'],
                ]
            ], 201);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate M-PESA payment',
                'error' => $result['error']
            ], 400);
        }
    }

    public function mpesaStatus(Request $request, $paymentId)
    {
        $user = auth()->user();
        $payment = Payment::where('id', $paymentId)
            ->where('user_id', $user->id)
            ->where('gateway', 'MPESA')
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'M-PESA payment not found'
            ], 404);
        }

        $result = $this->mpesaService->checkTransactionStatus($payment);

        return response()->json([
            'success' => $result['success'],
            'message' => 'M-PESA payment status retrieved',
            'data' => [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at,
                'failed_at' => $payment->failed_at,
                'failure_reason' => $payment->failure_reason,
                'verification_result' => $result,
            ]
        ]);
    }

    public function flutterwavePay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'email' => 'required|email|max:255',
            'callback_url' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Order has already been paid'
            ], 400);
        }

        $existingPayment = Payment::where('order_id', $order->id)
            ->where('gateway', 'Flutterwave')
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existingPayment) {
            $paymentLink = $this->flutterwaveService->getPaymentLink($existingPayment->id);
            
            if ($paymentLink['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Flutterwave payment already in progress',
                    'data' => [
                        'payment_id' => $existingPayment->id,
                        'transaction_id' => $existingPayment->transaction_id,
                        'payment_link' => $paymentLink['payment_link'],
                        'customer_message' => 'Please complete your payment using the provided link'
                    ]
                ]);
            }
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'Flutterwave',
            'status' => 'pending',
            'amount' => $order->total_amount,
            'currency' => 'KES',
            'transaction_id' => 'FW-' . strtoupper(Str::random(12)),
        ]);

        $result = $this->flutterwaveService->initiatePayment(
            $payment, 
            $request->email, 
            $request->callback_url
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Flutterwave payment initiated successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'payment_link' => $result['payment_link'],
                    'flutterwave_id' => $result['flutterwave_id'],
                    'customer_message' => $result['customer_message'],
                ]
            ], 201);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate Flutterwave payment',
                'error' => $result['error']
            ], 400);
        }
    }

    public function flutterwaveVerify($reference)
    {
        $user = auth()->user();
        
        
        $payment = Payment::where(function($query) use ($reference) {
            $query->where('transaction_id', $reference)
                  ->orWhere('gateway_reference', $reference);
        })
            ->where('user_id', $user->id)
            ->where('gateway', 'Flutterwave')
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Flutterwave payment not found',
                'debug' => [
                    'reference_searched' => $reference,
                    'user_id' => $user->id,
                    'note' => 'Searched both internal transaction ID (FW-XXXX...) and external Flutterwave transaction ID'
                ]
            ], 404);
        }

        $result = $this->flutterwaveService->verifyPayment($reference);

        return response()->json([
            'success' => $result['success'],
            'message' => 'Flutterwave payment verification completed',
            'data' => [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'gateway_reference' => $payment->gateway_reference,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at,
                'failed_at' => $payment->failed_at,
                'failure_reason' => $payment->failure_reason,
                'verification_result' => $result,
            ]
        ]);
    }

    private function processGatewayPayment(Payment $payment, Request $request)
    {
        $gatewayResponse = null;

        switch ($payment->gateway) {
            case 'MPESA':
                $gatewayResponse = $this->processMpesaPayment($payment, $request);
                break;
            case 'Flutterwave':
                $gatewayResponse = $this->processFlutterwavePayment($payment, $request);
                break;
            case 'DPO':
                $gatewayResponse = $this->processDpoPayment($payment, $request);
                break;
            case 'PesaPal':
                $gatewayResponse = $this->processPesapalPayment($payment, $request);
                break;
        }

        return $gatewayResponse;
    }

    private function verifyGatewayPayment(Payment $payment, $reference = null)
    {
        $verificationResult = null;

        switch ($payment->gateway) {
            case 'MPESA':
                $verificationResult = $this->verifyMpesaPayment($payment, $reference);
                break;
            case 'Flutterwave':
                $verificationResult = $this->verifyFlutterwavePayment($payment, $reference);
                break;
            case 'DPO':
                $verificationResult = $this->verifyDpoPayment($payment, $reference);
                break;
            case 'PesaPal':
                $verificationResult = $this->verifyPesapalPayment($payment, $reference);
                break;
        }

        return $verificationResult;
    }

    private function processMpesaPayment(Payment $payment, Request $request)
    {
        return $this->mpesaService->initiateStkPush(
            $payment, 
            $request->phone_number, 
            $request->callback_url
        );
    }

    private function processFlutterwavePayment(Payment $payment, Request $request)
    {
        return $this->flutterwaveService->initiatePayment(
            $payment, 
            $request->email, 
            $request->callback_url
        );
    }

    private function verifyMpesaPayment(Payment $payment, $reference = null)
    {
        return $this->mpesaService->checkTransactionStatus($payment);
    }

    private function verifyFlutterwavePayment(Payment $payment, $reference = null)
    {
        return $this->flutterwaveService->verifyPayment($reference ?? $payment->transaction_id);
    }

    private function processDpoPayment(Payment $payment, Request $request)
    {
        return $this->dpoService->createPaymentToken(
            $payment, 
            $request->email, 
            $request->callback_url
        );
    }

    private function verifyDpoPayment(Payment $payment, $reference = null)
    {
        return $this->dpoService->verifyPayment($reference ?? $payment->gateway_reference);
    }

    private function processPesapalPayment(Payment $payment, Request $request)
    {
        return $this->pesapalService->createPaymentOrder(
            $payment, 
            $request->email, 
            $request->callback_url
        );
    }

    private function verifyPesapalPayment(Payment $payment, $reference = null)
    {
        return $this->pesapalService->checkPaymentStatus($reference ?? $payment->gateway_reference);
    }

    public function mpesaWebhook(Request $request)
    {
        $webhookData = $request->all();
        
        $result = $this->mpesaService->processWebhook($webhookData);
        
        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'M-PESA webhook processed successfully' : 'Failed to process webhook'
        ]);
    }

    public function flutterwaveCallback(Request $request)
    {
        Log::info('Flutterwave Webhook Received', [
            'request_data' => $request->all(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all()
        ]);

        $transactionId = $request->get('transaction_id');
        $status = $request->get('status');
        
        if ($transactionId && $status === 'successful') {
            
            $payment = Payment::where('transaction_id', $transactionId)
                ->orWhere('gateway_reference', $transactionId)
                ->orWhere('gateway_response->data->id', $transactionId)
                ->first();
                
            if ($payment && $status === 'successful') {
            
                if (!$payment->isCompleted()) {
                    $payment->markAsCompleted($transactionId);
                    
                    Log::info('Flutterwave Payment Completed', [
                        'payment_id' => $payment->id,
                        'transaction_id' => $transactionId,
                        'amount' => $payment->amount
                    ]);
                }
                
                return redirect()->away('/api/payment/success?payment_id=' . $payment->id);
            }
        }
        
        Log::warning('Flutterwave Webhook - Payment not completed', [
            'transaction_id' => $transactionId,
            'status' => $status,
            'payment_found' => isset($payment) ? 'yes' : 'no'
        ]);
        
        return redirect()->away('/api/payment/failed');
    }

    public function dpoCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'email' => 'required|email|max:255',
            'callback_url' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Order has already been paid'
            ], 400);
        }

        $existingPayment = Payment::where('order_id', $order->id)
            ->where('gateway', 'DPO')
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existingPayment) {
            $paymentUrl = $this->dpoService->getPaymentUrl($existingPayment->id);
            
            if ($paymentUrl['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'DPO payment already in progress',
                    'data' => [
                        'payment_id' => $existingPayment->id,
                        'transaction_id' => $existingPayment->transaction_id,
                        'payment_url' => $paymentUrl['payment_url'],
                        'customer_message' => 'Please complete your payment using the provided link'
                    ]
                ]);
            }
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'DPO',
            'status' => 'pending',
            'amount' => $order->total_amount,
            'currency' => 'KES',
            'transaction_id' => 'DPO-' . strtoupper(Str::random(12)),
        ]);

        $result = $this->dpoService->createPaymentToken(
            $payment, 
            $request->email, 
            $request->callback_url
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'DPO payment token created successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'payment_url' => $result['payment_url'],
                    'trans_token' => $result['trans_token'],
                    'customer_message' => $result['customer_message'],
                ]
            ], 201);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create DPO payment token',
                'error' => $result['error']
            ], 400);
        }
    }

    public function dpoVerify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trans_token' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $payment = Payment::where('gateway_reference', $request->trans_token)
            ->where('user_id', $user->id)
            ->where('gateway', 'DPO')
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'DPO payment not found'
            ], 404);
        }

        $result = $this->dpoService->verifyPayment($request->trans_token);

        return response()->json([
            'success' => $result['success'],
            'message' => 'DPO payment verification completed',
            'data' => [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at,
                'failed_at' => $payment->failed_at,
                'failure_reason' => $payment->failure_reason,
                'verification_result' => $result,
            ]
        ]);
    }

    public function flutterwaveWebhook(Request $request)
    {
        $webhookData = $request->all();
        
        $result = $this->flutterwaveService->processWebhook($webhookData);
        
        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Flutterwave webhook processed successfully' : 'Failed to process webhook'
        ]);
    }

    public function dpoWebhook(Request $request)
    {
        $webhookData = $request->all();
        
        $result = $this->dpoService->processWebhook($webhookData);
        
        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'DPO webhook processed successfully' : 'Failed to process webhook'
        ]);
    }

    public function pesapalWebhook(Request $request)
    {
        $ipnData = $request->all();
        
        $result = $this->pesapalService->processIpn($ipnData);
        
        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'PesaPal IPN processed successfully' : 'Failed to process IPN'
        ]);
    }

    public function pesapalCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'email' => 'required|email|max:255',
            'callback_url' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Order has already been paid'
            ], 400);
        }

        $existingPayment = Payment::where('order_id', $order->id)
            ->where('gateway', 'PesaPal')
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existingPayment) {
            $paymentUrl = $this->pesapalService->getPaymentUrl($existingPayment->id);
            
            if ($paymentUrl['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'PesaPal payment already in progress',
                    'data' => [
                        'payment_id' => $existingPayment->id,
                        'transaction_id' => $existingPayment->transaction_id,
                        'payment_url' => $paymentUrl['payment_url'],
                        'customer_message' => 'Please complete your payment using the provided link'
                    ]
                ]);
            }
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'PesaPal',
            'status' => 'pending',
            'amount' => $order->total_amount,
            'currency' => 'KES',
            'transaction_id' => 'PESA-' . strtoupper(Str::random(12)),
        ]);

        $result = $this->pesapalService->createPaymentOrder(
            $payment, 
            $request->email, 
            $request->callback_url
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'PesaPal payment order created successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'payment_url' => $result['payment_url'],
                    'order_tracking_id' => $result['order_tracking_id'],
                    'merchant_reference' => $result['merchant_reference'],
                    'customer_message' => $result['customer_message'],
                ]
            ], 201);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create PesaPal payment order',
                'error' => $result['error']
            ], 400);
        }
    }

    public function pesapalStatus($orderTrackingId)
    {
        $user = auth()->user();
        $payment = Payment::where('gateway_reference', $orderTrackingId)
            ->where('user_id', $user->id)
            ->where('gateway', 'PesaPal')
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'PesaPal payment not found'
            ], 404);
        }

        $result = $this->pesapalService->checkPaymentStatus($orderTrackingId);

        return response()->json([
            'success' => $result['success'],
            'message' => 'PesaPal payment status retrieved',
            'data' => [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at,
                'failed_at' => $payment->failed_at,
                'failure_reason' => $payment->failure_reason,
                'status_result' => $result,
            ]
        ]);
    }

    public function paymentSuccess(Request $request)
    {
        $paymentId = $request->get('payment_id');
        
        if ($paymentId) {
            $payment = Payment::find($paymentId);
            if ($payment && $payment->isCompleted()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment completed successfully',
                    'data' => [
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'paid_at' => $payment->paid_at,
                    ]
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment not found or not completed'
        ], 404);
    }

    public function paymentFailed(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Payment failed or was cancelled',
            'data' => [
                'retry_url' => url('/api/payment/initiate'),
                'support_email' => config('app.support_email', 'support@example.com'),
            ]
        ]);
    }
}
