<?php
// app/Http/Controllers/Api/CartController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    /**
     * Get user cart
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $cart = Cart::with('product')
                ->where('user_id', $user->id)
                ->get();

            $total = $cart->sum(function ($item) {
                return $item->product->price * $item->quantity;
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'items' => $cart,
                    'total' => $total,
                    'count' => $cart->sum('quantity'),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching cart: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch cart: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add item to cart
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $product = Product::find($request->product_id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found'
                ], 404);
            }

            // Check stock
            if ($product->stock < $request->quantity) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient stock. Available: ' . $product->stock
                ], 400);
            }

            // Check if product already in cart
            $cart = Cart::where('user_id', $user->id)
                ->where('product_id', $request->product_id)
                ->first();

            if ($cart) {
                // Update quantity
                $newQuantity = $cart->quantity + $request->quantity;

                if ($product->stock < $newQuantity) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insufficient stock. Available: ' . $product->stock
                    ], 400);
                }

                $cart->quantity = $newQuantity;
                $cart->save();
            } else {
                $cart = Cart::create([
                    'user_id' => $user->id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                ]);
            }

            // Load updated cart
            $cart->load('product');

            return response()->json([
                'status' => 'success',
                'message' => 'Item added to cart successfully',
                'data' => $cart
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error adding to cart: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add item to cart: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function update(Request $request, $productId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $cart = Cart::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->first();

            if (!$cart) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Item not found in cart'
                ], 404);
            }

            $product = Product::find($productId);

            if ($product && $product->stock < $request->quantity) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient stock. Available: ' . $product->stock
                ], 400);
            }

            $cart->quantity = $request->quantity;
            $cart->save();
            $cart->load('product');

            return response()->json([
                'status' => 'success',
                'message' => 'Cart updated successfully',
                'data' => $cart
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating cart: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update cart: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove item from cart
     */
    public function destroy(Request $request, $productId)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $cart = Cart::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->first();

            if (!$cart) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Item not found in cart'
                ], 404);
            }

            $cart->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Item removed from cart successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error removing from cart: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove item from cart: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear cart
     */
    public function clear(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            Cart::where('user_id', $user->id)->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Cart cleared successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error clearing cart: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear cart: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cart count
     */
    public function count(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $count = Cart::where('user_id', $user->id)
                ->sum('quantity');

            return response()->json([
                'status' => 'success',
                'data' => ['count' => $count]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting cart count: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get cart count'
            ], 500);
        }
    }
}
