<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ActivityLog;
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


            ActivityLog::log(
                $user->id,
                'order_placed',
                'order',
                $order->id,
                $request->ip()
            );


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
        
        $orders = Order::with(['orderItems.product', 'latestPayment'])
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
                'payment_transaction_id' => $order->latestPayment?->transaction_id,
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
        
        $order = Order::with(['user', 'orderItems.product', 'latestPayment'])
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
                    'payment_transaction_id' => $order->latestPayment?->transaction_id,
                ],
                'items' => $orderItems,
            ]
        ]);
    }

    public function adminIndex(Request $request)
    {
       $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,approved,rejected,delivered',
            'payment_status' => 'nullable|in:pending,paid,failed',
            'payment_method' => 'nullable|in:MPESA,Flutterwave,DPO,PesaPal',
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Order::with(['user:id,username,email', 'orderItems.product']);

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $limit = $request->get('limit', 10);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $limit;

        $orders = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $total = $query->count();

        $ordersData = $orders->map(function ($order) {

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
                   'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                ],
                'filters' => [
                    'search' => $request->get('search'),
                    'status' => $request->get('status'),
                    'payment_status' => $request->get('payment_status'),
                    'payment_method' => $request->get('payment_method'),
                    'start_date' => $request->get('start_date'),
                    'end_date' => $request->get('end_date'),
                ]
            ]
        ]);
    }

    public function updateOrderStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,approved,rejected,processing,shipped,delivered,cancelled',
            'payment_status' => 'nullable|in:pending,paid,failed,refunded',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $admin = auth()->user();

        
        $order->status = $request->status;
        
        if ($request->has('payment_status')) {
            $order->payment_status = $request->payment_status;
        }

        
        if ($request->status === 'delivered') {
            $order->delivered_at = now();
        } elseif ($request->status === 'processing') {
            $order->approved_at = now();
        }

        $order->save();

        
        ActivityLog::log(
            $admin->id,
            'order_status_updated',
            'order',
            $order->id,
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => [
                'id' => $order->id,
                'transaction_reference' => $order->transaction_reference,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'updated_at' => $order->updated_at,
            ]
        ]);
    }

    public function deleteOrder(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $admin = auth()->user();

        
        if (!in_array($order->status, ['cancelled', 'pending'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete orders with status: ' . $order->status
            ], 400);
        }

        $orderId = $order->id;
        $order->delete();

        
        ActivityLog::log(
            $admin->id,
            'order_deleted',
            'order',
            $orderId,
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully'
        ]);
    }
}