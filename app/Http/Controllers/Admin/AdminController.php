<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * Get all users with optional filtering and pagination.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:active,inactive',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = User::query();

        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        
        $limit = $request->get('limit', 10);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $limit;

        $users = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'username', 'email', 'role', 'phone_number', 'address', 'status', 'created_at', 'updated_at']);

        $total = $query->count();

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                ]
            ]
        ]);
    }

    /**
     * Activate a user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function activateUser($id)
    {
        
        \Log::info('Activate user called with ID: ' . $id);
        
        $user = User::find($id);
        
        \Log::info('User found: ' . ($user ? 'Yes' : 'No'));

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'User is already active and cannot be activated again'
            ], 400);
        }

        $user->update(['status' => 'active']);

        // Log the activity
        ActivityLog::log(
            auth()->user()->id,
            'activate_user',
            'user',
            $user->id,
            request()->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'User activated successfully',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'status' => $user->status,
            ]
        ]);
    }

    /**
     * Deactivate a user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivateUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate admin users'
            ], 403);
        }

        if ($user->status === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'User is already inactive and cannot be deactivated again'
            ], 400);
        }

        $user->update(['status' => 'inactive']);

        // Log the activity
        ActivityLog::log(
            auth()->user()->id,
            'deactivate_user',
            'user',
            $user->id,
            request()->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'User deactivated successfully',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'status' => $user->status,
            ]
        ]);
    }

    /**
     * Get activity logs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActivityLogs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:users,id',
            'action' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = ActivityLog::with('user:id,username,email');

        
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        
        if ($request->has('action')) {
            $query->where('action', 'like', '%' . $request->action . '%');
        }

        
        $limit = $request->get('limit', 20);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $limit;

        $logs = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $total = $query->count();

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit),
                ]
            ]
        ]);
    }
}