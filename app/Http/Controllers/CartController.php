<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $product = Product::findOrFail($request->product_id);

        if (!$product->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Product is not available'
            ], 400);
        }

        if ($product->quantity < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient product quantity'
            ], 400);
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $existingCartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existingCartItem) {
            $newQuantity = $existingCartItem->quantity + $request->quantity;
            
            if ($product->quantity < $newQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient product quantity'
                ], 400);
            }

            $existingCartItem->update(['quantity' => $newQuantity]);
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'price' => $product->price,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart successfully'
        ]);
    }

    public function index()
    {
        $user = auth()->user();
        $cart = $user->cart;

        if (!$cart) {
            return response()->json([
                'success' => true,
                'data' => [
                    'cart_items' => [],
                    'total_price' => 0,
                    'total_items' => 0,
                ]
            ]);
        }

        $cartItems = $cart->cartItems()->with('product')->get();

        $cartItemsData = $cartItems->map(function ($cartItem) {
            return [
                'id' => $cartItem->id,
                'product' => [
                    'id' => $cartItem->product->id,
                    'title' => $cartItem->product->title,
                    'description' => $cartItem->product->description,
                    'image' => $cartItem->product->image,
                ],
                'quantity' => $cartItem->quantity,
                'price' => $cartItem->price,
                'subtotal' => $cartItem->subtotal,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'cart_items' => $cartItemsData,
                'total_price' => $cart->total,
                'total_items' => $cart->total_items,
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
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

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found'
            ], 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('id', $id)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found'
            ], 404);
        }

        $product = $cartItem->product;

        if ($product->quantity < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient product quantity'
            ], 400);
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return response()->json([
            'success' => true,
            'message' => 'Cart item updated successfully'
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $cart = $user->cart;

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found'
            ], 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('id', $id)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found'
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart item removed successfully'
        ]);
    }
}