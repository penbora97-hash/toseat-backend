<?php
// app/Http/Controllers/Api/Admin/CategoryController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Category::orderBy('order', 'asc');

            if ($request->has('search') && !empty($request->search)) {
                $query->where('name', 'LIKE', "%{$request->search}%");
            }

            $categories = $query->paginate(20);

            return response()->json([
                'status' => 'success',
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            Log::error('Admin Category Error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch categories'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:categories',
                'is_active' => 'boolean',
                'order' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category = Category::create($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);

        } catch (\Exception $e) {
            Log::error('Create Category Error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create category'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $category = Category::findOrFail($id);
            $category->update($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Category updated successfully',
                'data' => $category
            ]);

        } catch (\Exception $e) {
            Log::error('Update Category Error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update category'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $category = Category::findOrFail($id);
            
            // Check if category has products
            if ($category->products()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete category with products'
                ], 422);
            }

            $category->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Category deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Delete Category Error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete category'
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'is_active' => 'required|boolean',
            ]);

            $category = Category::findOrFail($id);
            $category->is_active = $request->is_active;
            $category->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Category status updated',
                'data' => $category
            ]);

        } catch (\Exception $e) {
            Log::error('Update Status Error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update status'
            ], 500);
        }
    }
}