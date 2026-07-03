<?php
// app/Http/Controllers/Api/ProductController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * Get all available products
     */
    public function index(Request $request)
    {
        try {
            // ✅ Test query
            $query = Product::with('category');

            // Filter by category
            if ($request->has('category_id') && !empty($request->category_id)) {
                $query->where('category_id', $request->category_id);
            }

            // Search by name
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('name_kh', 'LIKE', "%{$search}%");
                });
            }

            // Sort
            if ($request->has('sort')) {
                switch ($request->sort) {
                    case 'price_asc':
                        $query->orderBy('price', 'asc');
                        break;
                    case 'price_desc':
                        $query->orderBy('price', 'desc');
                        break;
                    case 'name':
                        $query->orderBy('name', 'asc');
                        break;
                    default:
                        $query->latest();
                }
            }

            $products = $query->paginate(12);

            return response()->json([
                'status' => 'success',
                'data' => $products
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching products: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single product
     */
    public function show($id)
    {
        try {
            // ✅ យក is_available ចេញ (បង្ហាញទាំងអស់)
            $product = Product::with('category')
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }
    }

    /**
     * Get featured products
     */
    public function featured(Request $request)
    {
        try {
            $limit = $request->limit ?? 8;

            $products = Product::where('is_available', true)
                ->where('is_featured', true)
                ->with('category')
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch featured products'
            ], 500);
        }
    }

    /**
     * Get popular products
     */
    public function popular(Request $request)
    {
        try {
            $limit = $request->limit ?? 8;

            $products = Product::where('is_available', true)
                ->where('is_popular', true)
                ->with('category')
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch popular products'
            ], 500);
        }
    }
}
