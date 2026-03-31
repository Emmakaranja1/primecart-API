<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'brand' => 'nullable|string',  
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'featured' => 'nullable|string|in:true,false',
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

        $query = Product::active();

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('brand')) {
            $brands = array_map('trim', explode(',', $request->brand));
            $query->whereIn('brand', $brands);
        }

        if ($request->has('min_price') || $request->has('max_price')) {
            $query->byPriceRange($request->min_price, $request->max_price);
        }

        if ($request->filled('featured') && $request->get('featured') === 'true') {
            $query->featured();
        }

        $limit = $request->get('limit', 12);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $limit;

        $products = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $total = $query->count();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                ]
            ]
        ]);
    }

    public function show($id)
    {
        $product = Product::active()->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product
            ]
        ]);
    }

    public function adminIndex(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'brand' => 'nullable|string',  
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'featured' => 'nullable|boolean',
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

        $query = Product::query();

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('brand')) {
            $brands = array_map('trim', explode(',', $request->brand));
            $query->whereIn('brand', $brands);
        }

        if ($request->has('min_price') || $request->has('max_price')) {
            $query->byPriceRange($request->min_price, $request->max_price);
        }

        if ($request->has('is_active')) {
            $isActive = $request->get('is_active');
            if ($isActive === 'false' || $isActive === '0') {
                $isActive = false;
            } elseif ($isActive === 'true' || $isActive === '1') {
                $isActive = true;
            } else {
                $isActive = filter_var($isActive, FILTER_VALIDATE_BOOLEAN);
            }
            $query->where('is_active', $isActive);
        }

        if ($request->has('featured')) {
            $isFeatured = $request->get('featured');
            if ($isFeatured === 'false' || $isFeatured === '0') {
                $isFeatured = false;
            } elseif ($isFeatured === 'true' || $isFeatured === '1') {
                $isFeatured = true;
            } else {
                $isFeatured = filter_var($isFeatured, FILTER_VALIDATE_BOOLEAN);
            }
            $query->where('featured', $isFeatured);
        }

        $limit = $request->get('limit', 10);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $limit;

        $products = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $total = $query->count();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'category' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::create([
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'quantity' => $request->quantity,
            'category' => $request->category,
            'brand' => $request->brand,
            'image' => $request->image,
            'is_active' => $request->boolean('is_active', true),
            'featured' => $request->boolean('featured', false),
        ]);

        ActivityLog::log(
            auth()->user()->id,
            'product_created',
            'product',
            $product->id,
            $request->ip()
        );


        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => [
                'product' => $product
            ]
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'quantity' => 'sometimes|required|integer|min:0',
            'category' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update([
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'quantity' => $request->quantity,
            'category' => $request->category,
            'brand' => $request->brand,
            'image' => $request->image,
            'is_active' => $request->boolean('is_active', $product->is_active),
            'featured' => $request->boolean('featured', $product->featured),
        ]);

            ActivityLog::log(
            auth()->user()->id,
            'product_updated',
            'product',
            $product->id,
            $request->ip()
        );


        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => [
                'product' => $product
            ]
        ]);
    }

    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Check if product has associated order items or cart items
        $hasOrderItems = $product->orderItems()->exists();
        $hasCartItems = $product->cartItems()->exists();

        if ($hasOrderItems) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete product: It has associated orders. Please delete the orders first.'
            ], 400);
        }

        if ($hasCartItems) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete product: It is in users\' shopping carts.'
            ], 400);
        }

        $productId = $product->id;
        $product->delete();
        
        ActivityLog::log(
            auth()->user()->id,
            'product_deleted',
            'product',
            $productId,
            $request->ip()
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}