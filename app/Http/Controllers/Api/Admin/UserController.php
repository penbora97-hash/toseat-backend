<?php
// app/Http/Controllers/Api/Admin/UserController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Get all users (Admin only)
     */
    public function index(Request $request)
    {
        try {
          
            $query = User::where('role', 'user')->orderBy('created_at', 'desc');

            // Search by name or email
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%");
                });
            }

            $users = $query->paginate(20);

            return response()->json([
                'status' => 'success',
                'data' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch users: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Get single user
     */
    public function show($id)
    {
        try {
            $user = User::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $request->validate([
                'full_name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20',
                'role' => 'sometimes|in:user,admin',
                'is_active' => 'sometimes|boolean',
            ]);

            $user->update($request->only(['full_name', 'phone', 'role', 'is_active']));

            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully',
                'data' => $user
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);

            // Prevent deleting self
            if (auth()->id() === $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete your own account'
                ], 422);
            }

            $user->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'is_active' => 'required|boolean',
            ]);

            $user = User::findOrFail($id);
            $user->is_active = $request->is_active;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'User status updated successfully',
                'data' => $user
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating user status: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update user status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics
     */
    public function stats()
    {
        try {
            $total = User::count();
            $active = User::where('is_active', true)->count();
            $inactive = User::where('is_active', false)->count();
            $admins = User::where('role', 'admin')->count();
            $users = User::where('role', 'user')->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total' => $total,
                    'active' => $active,
                    'inactive' => $inactive,
                    'admins' => $admins,
                    'users' => $users,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user stats: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch stats'
            ], 500);
        }
    }
}
