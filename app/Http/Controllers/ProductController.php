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
            'brand' => 'nullable|string|max:255',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
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

        $query = Product::active();

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        if ($request->has('brand')) {
            $query->byBrand($request->brand);
        }

        if ($request->has('min_price') || $request->has('max_price')) {
            $query->byPriceRange($request->min_price, $request->max_price);
        }

        if ($request->boolean('featured')) {
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
            'brand' => 'nullable|string|max:255',
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

        if ($request->has('brand')) {
            $query->byBrand($request->brand);
        }

        if ($request->has('min_price') || $request->has('max_price')) {
            $query->byPriceRange($request->min_price, $request->max_price);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('featured')) {
            $query->where('featured', $request->boolean('featured'));
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

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}