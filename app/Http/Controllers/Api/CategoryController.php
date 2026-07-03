<?php
// app/Http/Controllers/Api/CategoryController.php (Public)

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Get all active categories for menu
     */
    public function index(Request $request)
    {
        try {
            // ✅ Order by 'order' column
            $categories = Category::active()
                ->withCount('products')
                ->orderBy('order', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch categories'
            ], 500);
        }
    }

    /**
     * Get category with products
     */
    public function show($id)
    {
        try {
            $category = Category::with(['products' => function ($query) {
                $query->available()->limit(10);
            }])->withCount('products')->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found'
            ], 404);
        }
    }

    /**
     * Get products by category
     */
    public function products($id, Request $request)
    {
        try {
            $category = Category::findOrFail($id);

            $query = $category->products()->with('category')->available();

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

            $perPage = $request->per_page ?? 12;
            $products = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'category' => $category,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch products'
            ], 500);
        }
    }
}
