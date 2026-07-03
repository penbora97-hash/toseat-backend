<?php
// app/Http/Controllers/Api/Admin/OrderController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Get all orders
     */
    public function index(Request $request)
    {
        try {
            $query = Order::with('user')->orderBy('created_at', 'desc');

            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            // ✅ Limit for dashboard
            if ($request->has('limit')) {
                $orders = $query->limit($request->limit)->get();
            } else {
                $orders = $query->paginate(20);
            }

            return response()->json([
                'status' => 'success',
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching orders: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch orders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single order
     */
    public function show($id)
    {
        try {
            $order = Order::with(['user', 'items.product'])->findOrFail($id);

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
     * Update order status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,confirmed,cancelled'
            ]);

            $order = Order::findOrFail($id);
            $order->status = $request->status;
            $order->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Order status updated successfully',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating order status: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update order status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function stats()
    {
        try {
            // ✅ Check if tables exist and have data
            $totalOrders = Order::count();
            $pendingOrders = Order::where('status', 'pending')->count();
            $confirmedOrders = Order::where('status', 'confirmed')->count();
            $cancelledOrders = Order::where('status', 'cancelled')->count();

            // ✅ Use coalesce to handle null
            $totalRevenue = Order::where('status', 'confirmed')->sum('total_amount') ?? 0;

            // ✅ Get other stats
            $totalUsers = User::count();
            $totalProducts = Product::count();
            $totalCategories = Category::count();

            $stats = [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'confirmed_orders' => $confirmedOrders,
                'cancelled_orders' => $cancelledOrders,
                'total_revenue' => (float) $totalRevenue,
                'total_users' => $totalUsers,
                'total_products' => $totalProducts,
                'total_categories' => $totalCategories,
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching stats: ' . $e->getMessage());
            Log::error('Line: ' . $e->getLine());
            Log::error('File: ' . $e->getFile());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
