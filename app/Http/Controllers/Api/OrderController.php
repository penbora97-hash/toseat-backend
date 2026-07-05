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
    /**
     * Store a new order
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'delivery_address' => 'required|string',
                'payment_method' => 'required|in:cash,card,aba,khqr',
                'notes' => 'nullable|string',
                'full_name' => 'nullable|string',
                'email' => 'nullable|email',
                'phone' => 'nullable|string',
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
                    'message' => 'User not found'
                ], 404);
            }

            // Get cart from database
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
            $deliveryFee = $subtotal > 20 ? 0 : 2.50;
            $tax = $subtotal * 0.05;
            $total = $subtotal + $deliveryFee + $tax;

            // Get customer info from request or user
            $customerName = $request->full_name ?? $user->full_name ?? $user->name ?? 'Customer';
            $customerPhone = $request->phone ?? $user->phone ?? '';
            $customerEmail = $request->email ?? $user->email ?? '';

            // Create order
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
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
                'notes' => $request->notes,
                'cancelled_by' => null, // ✅ Add cancelled_by field
            ]);

            // Create order items
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

            // Clear cart
            Cart::where('user_id', $user->id)->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Order placed successfully',
                'data' => $order->load('items')
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating order: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user orders
     */
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

    /**
     * Get single order
     */
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

    /**
     * ✅ Update order (for cancelling by user)
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();

            // Find order
            $order = Order::where('user_id', $user->id)->findOrFail($id);

            // ✅ Validate request
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,confirmed,preparing,out_for_delivery,delivered,cancelled'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $newStatus = $request->status;

            // ✅ Check if order can be cancelled
            if ($newStatus === 'cancelled') {
                // Only pending orders can be cancelled
                if ($order->status !== 'pending') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Only pending orders can be cancelled. Current status: ' . $order->status
                    ], 422);
                }

                // ✅ Restore stock when cancelling
                foreach ($order->items as $item) {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $product->increment('stock', $item->quantity);
                    }
                }

                // ✅ Set cancelled_by to 'user'
                $order->cancelled_by = 'user';

                // ✅ Append cancellation note
                $cancellationNote = "Cancelled by customer at " . now();
                $order->notes = $order->notes
                    ? $order->notes . "\n" . $cancellationNote
                    : $cancellationNote;
            }

            // ✅ Prevent changing from delivered or cancelled
            if (in_array($order->status, ['delivered', 'cancelled'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This order cannot be modified. Current status: ' . $order->status
                ], 422);
            }

            // ✅ Update order status
            $order->status = $newStatus;
            $order->save();

            // ✅ Log the update
            Log::info('Order updated by user', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'old_status' => $order->getOriginal('status'),
                'new_status' => $newStatus,
                'cancelled_by' => $order->cancelled_by
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Order updated successfully',
                'data' => $order->load('items')
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating order: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Cancel order (alternative method using POST by user)
     */
    public function cancel(Request $request, $id)
    {
        try {
            $user = $request->user();

            // Find order
            $order = Order::where('user_id', $user->id)->findOrFail($id);

            // ✅ Check if order can be cancelled
            if ($order->status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only pending orders can be cancelled. Current status: ' . $order->status
                ], 422);
            }

            // ✅ Restore stock
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock', $item->quantity);
                }
            }

            // ✅ Update order status
            $order->status = 'cancelled';
            $order->cancelled_by = 'user'; // ✅ Set cancelled_by to 'user'

            // ✅ Append cancellation note
            $cancellationNote = "Cancelled by customer at " . now();
            $order->notes = $order->notes
                ? $order->notes . "\n" . $cancellationNote
                : $cancellationNote;

            $order->save();

            // ✅ Log the cancellation
            Log::info('Order cancelled by user', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'cancelled_by' => 'user'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Order cancelled successfully',
                'data' => $order->load('items')
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error cancelling order: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Admin: Get all orders (for admin dashboard)
     */
    public function adminIndex(Request $request)
    {
        try {
            $orders = Order::with(['user', 'items.product'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'status' => 'success',
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching admin orders: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch orders'
            ], 500);
        }
    }

    /**
     * ✅ Admin: Update order status
     */
    public function adminUpdateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,confirmed,preparing,out_for_delivery,delivered,cancelled'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $order = Order::findOrFail($id);
            $oldStatus = $order->status;
            $newStatus = $request->status;

            // ✅ If cancelling by admin
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                // Restore stock
                foreach ($order->items as $item) {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $product->increment('stock', $item->quantity);
                    }
                }

                // ✅ Set cancelled_by to 'admin'
                $order->cancelled_by = 'admin';

                // ✅ Append cancellation note
                $adminName = $request->user()->name ?? 'Admin';
                $cancellationNote = "Cancelled by admin ({$adminName}) at " . now();
                $order->notes = $order->notes
                    ? $order->notes . "\n" . $cancellationNote
                    : $cancellationNote;
            }

            // ✅ If confirming, check stock
            if ($newStatus === 'confirmed' && $oldStatus === 'pending') {
                foreach ($order->items as $item) {
                    $product = Product::find($item->product_id);
                    if ($product && $product->stock < $item->quantity) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "Insufficient stock for product: {$product->name}"
                        ], 422);
                    }
                }
            }

            $order->status = $newStatus;
            $order->save();

            Log::info('Admin updated order status', [
                'order_id' => $order->id,
                'admin_id' => $request->user()->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'cancelled_by' => $order->cancelled_by
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Order status updated successfully',
                'data' => $order->load('items')
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating order status: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update order status'
            ], 500);
        }
    }

    /**
     * ✅ Admin: Cancel order
     */
    public function adminCancel(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);

            // Check if already cancelled
            if ($order->status === 'cancelled') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This order is already cancelled'
                ], 422);
            }

            // Restore stock
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock', $item->quantity);
                }
            }

            // Update order
            $order->status = 'cancelled';
            $order->cancelled_by = 'admin';

            $adminName = $request->user()->name ?? 'Admin';
            $cancellationNote = "Cancelled by admin ({$adminName}) at " . now();
            $order->notes = $order->notes
                ? $order->notes . "\n" . $cancellationNote
                : $cancellationNote;

            $order->save();

            Log::info('Admin cancelled order', [
                'order_id' => $order->id,
                'admin_id' => $request->user()->id,
                'cancelled_by' => 'admin'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Order cancelled successfully',
                'data' => $order->load('items')
            ]);
        } catch (\Exception $e) {
            Log::error('Error admin cancelling order: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel order'
            ], 500);
        }
    }
}
