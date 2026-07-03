<?php
// app/Http/Controllers/Api/OrderController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'delivery_address' => 'required|string',
                'payment_method' => 'required|in:cash,card,aba,khqr',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // ✅ Check if user exists
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found'
                ], 404);
            }

            // ✅ Get cart from database, not localStorage
            $cart = Cart::with('product')->where('user_id', $user->id)->get();

            if ($cart->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cart is empty'
                ], 400);
            }

            // Calculate totals
            $subtotal = 0;
            $items = [];

            foreach ($cart as $item) {
                $product = $item->product;

                // ✅ Check if product exists
                if (!$product) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Product not found'
                    ], 404);
                }

                if (!$product->is_available || $product->stock < $item->quantity) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Product '{$product->name}' is not available or insufficient stock"
                    ], 400);
                }

                $itemTotal = $product->price * $item->quantity;
                $subtotal += $itemTotal;

                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_price' => $product->price,
                    'quantity' => $item->quantity,
                    'subtotal' => $itemTotal,
                ];
            }

            // Calculate fees
            $deliveryFee = 2.50;
            $tax = $subtotal * 0.05;
            $total = $subtotal + $deliveryFee + $tax;

            // ✅ Create order
            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'user_id' => $user->id,
                'total_amount' => $total,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'tax' => $tax,
                'discount' => 0,
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'delivery_address' => $request->delivery_address,
                'customer_name' => $user->full_name ?? $user->name ?? 'Customer',
                'customer_phone' => $user->phone ?? '',
                'notes' => $request->notes,
            ]);

            // ✅ Create order items
            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'product_price' => $item['product_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);

                // Reduce stock
                $product = Product::find($item['product_id']);
                if ($product) {
                    $product->decrement('stock', $item['quantity']);
                }
            }

            // ✅ Clear cart
            Cart::where('user_id', $user->id)->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Order placed successfully',
                'data' => $order->load('items')
            ], 201);
        } catch (\Exception $e) {
            // ✅ Log detailed error
            Log::error('Error creating order: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $orders = Order::with(['items.product'])
                ->where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching orders: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch orders'
            ], 500);
        }
    }

    public function show($id, Request $request)
    {
        try {
            $order = Order::with(['items.product'])
                ->where('user_id', $request->user()->id)
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        }
    }
}
