<?php
// app/Http/Controllers/Api/Admin/ProductController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Get all products with category
     */
    public function index(Request $request)
    {
        try {
            $query = Product::with('category');

            // Search by name
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('name_kh', 'LIKE', "%{$search}%");
                });
            }

            // Filter by category
            if ($request->has('category_id') && !empty($request->category_id)) {
                $query->where('category_id', $request->category_id);
            }

            // Filter by availability
            if ($request->has('is_available')) {
                $query->where('is_available', $request->is_available);
            }

            $products = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'status' => 'success',
                'data' => $products
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching products: ' . $e->getMessage());

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
            $product = Product::with('category')->findOrFail($id);

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
     * Create new product
     */

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'name_kh' => 'nullable|string|max:255',
                'category_id' => 'required|exists:categories,id',
                'price' => 'required|numeric|min:0',
                'stock' => 'nullable|integer|min:0',
                'is_available' => 'boolean',
                'image_url' => 'nullable|string|max:500', // ✅ បង្កើន max length
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // ✅ រក្សាទុក URL ដូចដើម មិនបន្ថែមអ្វី
            $imageUrl = $request->image_url;

            $product = Product::create([
                'name' => $request->name,
                'name_kh' => $request->name_kh,
                'category_id' => $request->category_id,
                'price' => $request->price,
                'stock' => $request->stock ?? 0,
                'is_available' => $request->is_available ?? true,
                'image_url' => $imageUrl, // ✅ រក្សាទុក URL ដើម
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Product created successfully',
                'data' => $product
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating product: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create product: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Update product
     */
    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'name_kh' => 'nullable|string|max:255',
                'category_id' => 'sometimes|exists:categories,id',
                'price' => 'sometimes|numeric|min:0',
                'stock' => 'nullable|integer|min:0',
                'is_available' => 'boolean',
                'image_url' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if category exists if provided
            if ($request->has('category_id')) {
                $category = Category::find($request->category_id);
                if (!$category) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Category not found'
                    ], 404);
                }
            }

            // ✅ Fix image_url - remove storage prefix if exists
            $data = $request->all();
            if (isset($data['image_url']) && $data['image_url']) {
                $data['image_url'] = preg_replace('#^https?://[^/]+/storage/#', '', $data['image_url']);
                $data['image_url'] = preg_replace('#^/storage/#', '', $data['image_url']);
                $data['image_url'] = preg_replace('#^storage/#', '', $data['image_url']);
            }

            $product->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Product updated successfully',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating product: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete product
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            // Delete image if exists and it's a local file
            if ($product->image_url && !filter_var($product->image_url, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($product->image_url);
            }

            $product->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Product deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting product: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update product stock
     */
    public function updateStock(Request $request, $id)
    {
        try {
            $request->validate([
                'stock' => 'required|integer|min:0',
            ]);

            $product = Product::findOrFail($id);
            $product->stock = $request->stock;
            $product->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Stock updated successfully',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating stock: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stock: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update product availability
     */
    public function updateAvailability(Request $request, $id)
    {
        try {
            $request->validate([
                'is_available' => 'required|boolean',
            ]);

            $product = Product::findOrFail($id);
            $product->is_available = $request->is_available;
            $product->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Product availability updated',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating availability: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update availability: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get products by category
     */
    public function getByCategory($categoryId, Request $request)
    {
        try {
            $category = Category::findOrFail($categoryId);

            $query = Product::where('category_id', $categoryId);

            if ($request->has('is_available')) {
                $query->where('is_available', $request->is_available);
            }

            $products = $query->orderBy('name')->get();

            return response()->json([
                'status' => 'success',
                'category' => $category,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching products by category: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get product statistics
     */
    public function stats()
    {
        try {
            $total = Product::count();
            $available = Product::where('is_available', true)->count();
            $unavailable = Product::where('is_available', false)->count();
            $lowStock = Product::where('stock', '<', 10)->where('stock', '>', 0)->count();
            $outOfStock = Product::where('stock', 0)->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total' => $total,
                    'available' => $available,
                    'unavailable' => $unavailable,
                    'low_stock' => $lowStock,
                    'out_of_stock' => $outOfStock,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching product stats: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch stats'
            ], 500);
        }
    }

    /**
     * Upload product image
     */
    public function uploadImage(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $path = $request->file('image')->store('products', 'public');
            $imageUrl = asset('storage/' . $path);

            return response()->json([
                'status' => 'success',
                'message' => 'Image uploaded successfully',
                'data' => [
                    'image_url' => $imageUrl,
                    'path' => $path
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading image: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload image: ' . $e->getMessage()
            ], 500);
        }
    }
}
