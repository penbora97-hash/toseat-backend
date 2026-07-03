<?php
// app/Http/Controllers/Api/OrderItemsController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderItemsController extends Controller
{
    /**
     * Get all order items for a specific order
     */
    public function index(Request $request)
    {
        try {
            $query = OrderItem::with(['order', 'product']);

            // Filter by order_id
            if ($request->has('order_id') && !empty($request->order_id)) {
                $query->where('order_id', $request->order_id);
            }

            // Filter by user (only show items from user's orders)
            if ($request->user()) {
                $query->whereHas('order', function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                });
            }

            $items = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'status' => 'success',
                'data' => $items
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching order items: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch order items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single order item
     */
    public function show($id, Request $request)
    {
        try {
            $item = OrderItem::with(['order', 'product'])
                ->whereHas('order', function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                })
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $item
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order item not found'
            ], 404);
        }
    }

    /**
     * Create new order item (for manual addition)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|exists:orders,id',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ]);

            $order = Order::find($request->order_id);
            
            // Check if order is pending
            if ($order->status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot add items to non-pending orders'
                ], 422);
            }

            $product = \App\Models\Product::find($request->product_id);
            
            // Check stock
            if ($product->stock < $request->quantity) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient stock'
                ], 400);
            }

            $subtotal = $product->price * $request->quantity;

            $item = OrderItem::create([
                'order_id' => $request->order_id,
                'product_id' => $request->product_id,
                'product_name' => $product->name,
                'product_price' => $product->price,
                'quantity' => $request->quantity,
                'subtotal' => $subtotal,
            ]);

            // Update order total
            $order->subtotal += $subtotal;
            $order->total_amount = $order->subtotal + $order->delivery_fee + $order->tax;
            $order->save();

            // Reduce stock
            $product->decrement('stock', $request->quantity);

            return response()->json([
                'status' => 'success',
                'message' => 'Order item added successfully',
                'data' => $item
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating order item: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create order item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order item
     */
    public function update(Request $request, $id)
    {
        try {
            $item = OrderItem::with('order')->findOrFail($id);
            
            // Check if order is pending
            if ($item->order->status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot update items in non-pending orders'
                ], 422);
            }

            $request->validate([
                'quantity' => 'required|integer|min:1',
            ]);

            $oldQuantity = $item->quantity;
            $newQuantity = $request->quantity;
            $quantityDiff = $newQuantity - $oldQuantity;

            $product = \App\Models\Product::find($item->product_id);
            
            // Check stock for additional quantity
            if ($quantityDiff > 0 && $product->stock < $quantityDiff) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient stock'
                ], 400);
            }

            // Update stock
            if ($quantityDiff > 0) {
                $product->decrement('stock', $quantityDiff);
            } else {
                $product->increment('stock', abs($quantityDiff));
            }

            // Update item
            $oldSubtotal = $item->subtotal;
            $item->quantity = $newQuantity;
            $item->subtotal = $product->price * $newQuantity;
            $item->save();

            // Update order total
            $order = $item->order;
            $order->subtotal += ($item->subtotal - $oldSubtotal);
            $order->total_amount = $order->subtotal + $order->delivery_fee + $order->tax;
            $order->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Order item updated successfully',
                'data' => $item
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating order item: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update order item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete order item
     */
    public function destroy($id)
    {
        try {
            $item = OrderItem::with('order')->findOrFail($id);
            
            // Check if order is pending
            if ($item->order->status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete items from non-pending orders'
                ], 422);
            }

            // Restore stock
            $product = \App\Models\Product::find($item->product_id);
            if ($product) {
                $product->increment('stock', $item->quantity);
            }

            // Update order total
            $order = $item->order;
            $order->subtotal -= $item->subtotal;
            $order->total_amount = $order->subtotal + $order->delivery_fee + $order->tax;
            $order->save();

            $item->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Order item deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting order item: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete order item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get items by order number
     */
    public function getByOrderNumber($orderNumber)
    {
        try {
            $order = Order::where('order_number', $orderNumber)->firstOrFail();
            
            $items = OrderItem::with('product')
                ->where('order_id', $order->id)
                ->get();

            return response()->json([
                'status' => 'success',
                'order' => $order,
                'data' => $items
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        }
    }

    /**
     * Get order items statistics
     */
    public function stats()
    {
        try {
            $totalItems = OrderItem::count();
            $totalQuantity = OrderItem::sum('quantity');
            $totalRevenue = OrderItem::sum('subtotal');

            // Top selling products
            $topProducts = OrderItem::select('product_id', 'product_name')
                ->selectRaw('SUM(quantity) as total_quantity')
                ->selectRaw('SUM(subtotal) as total_revenue')
                ->groupBy('product_id', 'product_name')
                ->orderBy('total_quantity', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_items' => $totalItems,
                    'total_quantity' => $totalQuantity,
                    'total_revenue' => $totalRevenue,
                    'top_products' => $topProducts,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching order item stats: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch stats'
            ], 500);
        }
    }
}