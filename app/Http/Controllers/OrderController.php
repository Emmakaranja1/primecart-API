<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:MPESA,Flutterwave,DPO,PesaPal',
            'shipping_address' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $cart = $user->cart;

        if (!$cart || $cart->cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Your cart is empty'
            ], 400);
        }

        return DB::transaction(function () use ($request, $user, $cart) {
            $cartItems = $cart->cartItems()->with('product')->get();
            
            foreach ($cartItems as $cartItem) {
                if ($cartItem->product->quantity < $cartItem->quantity) {
                    throw new \Exception("Insufficient quantity for product: {$cartItem->product->title}");
                }
            }

            $totalAmount = $cartItems->sum(function ($cartItem) {
                return $cartItem->quantity * $cartItem->price;
            });

            $order = Order::create([
                'user_id' => $user->id,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $request->payment_method,
                'shipping_address' => $request->shipping_address,
                'transaction_reference' => 'TXN-' . strtoupper(Str::random(10)),
            ]);

            foreach ($cartItems as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->price,
                ]);

                $cartItem->product->decrement('quantity', $cartItem->quantity);
            }

            $cart->cartItems()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'data' => [
                    'order_id' => $order->id,
                    'transaction_reference' => $order->transaction_reference,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                ]
            ], 201);
        });
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        
        $orders = Order::with(['orderItems.product'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        $ordersData = $orders->getCollection()->map(function ($order) {
            return [
                'id' => $order->id,
                'transaction_reference' => $order->transaction_reference,
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'created_at' => $order->created_at,
                'items_count' => $order->orderItems->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $ordersData,
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ]
            ]
        ]);
    }

    public function show($id)
    {
        $user = auth()->user();
        
        $order = Order::with(['user', 'orderItems.product'])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $orderItems = $order->orderItems->map(function ($orderItem) {
            return [
                'id' => $orderItem->id,
                'product' => [
                    'id' => $orderItem->product->id,
                    'title' => $orderItem->product->title,
                    'description' => $orderItem->product->description,
                    'image' => $orderItem->product->image,
                ],
                'quantity' => $orderItem->quantity,
                'price' => $orderItem->price,
                'subtotal' => $orderItem->subtotal,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'transaction_reference' => $order->transaction_reference,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'shipping_address' => $order->shipping_address,
                    'created_at' => $order->created_at,
                    'approved_at' => $order->approved_at,
                    'delivered_at' => $order->delivered_at,
                ],
                'items' => $orderItems,
            ]
        ]);
    }

    public function adminIndex(Request $request)
    {
        $orders = Order::with(['user:id,username,email', 'orderItems.product'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        $ordersData = $orders->getCollection()->map(function ($order) {
            return [
                'id' => $order->id,
                'transaction_reference' => $order->transaction_reference,
                'user' => [
                    'id' => $order->user->id,
                    'username' => $order->user->username,
                    'email' => $order->user->email,
                ],
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'created_at' => $order->created_at,
                'items_count' => $order->orderItems->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $ordersData,
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ]
            ]
        ]);
    }
}