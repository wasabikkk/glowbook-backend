<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct()
    {
        // Optional: you can also enforce admin here if you want:
        // $this->middleware(function ($request, $next) {
        //     $user = $request->user();
        //     if (!$user || !in_array($user->role, ['admin']) && !$user->is_superadmin) {
        //         return response()->json(['message' => 'Forbidden'], 403);
        //     }
        //     return $next($request);
        // });
    }

    /**
     * GET /api/admin/users
     * Optional query params: ?search=...&role=admin|aesthetician|client
     */
    public function index(Request $request)
    {
        $auth = auth()->user();

        if (!$auth || !$auth->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $search = trim($request->query('search', ''));
        $role   = trim($request->query('role', ''));

        $q = User::query();

        if ($search !== '') {
            $q->where(function ($x) use ($search) {
                $x->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($role !== '') {
            $q->where('role', $role);
        }

        $users = $q->orderBy('id', 'asc')->get();

        return response()->json([
            'items' => $users,
        ]);
    }

    /**
     * POST /api/admin/users
     * Body: { name, email, username, password, role, is_superadmin? }
     */
    public function store(Request $request)
    {
        $auth = auth()->user();
        if (!$auth || !in_array($auth->role, ['admin']) && !$auth->is_superadmin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:6',
            'role'          => 'required|in:admin,aesthetician,client',
            'is_super_admin' => 'sometimes|boolean',
        ]);

        // Only a superadmin can create another superadmin
        $isSuperAdmin = !empty($data['is_super_admin']) && $auth->isSuperAdmin();

        // Automatically verify email for all users created by admin (admin, aesthetician, client)
        $user = User::create([
            'name'          => $data['name'],
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'email'         => strtolower($data['email']),
            'password'      => Hash::make($data['password']),
            'role'          => $data['role'],
            'is_super_admin' => $isSuperAdmin,
            'email_verified_at' => now(), // All users added by admin are automatically verified
        ]);

        return response()->json([
            'message' => 'User created.',
            'user'    => $user,
        ], 201);
    }

    /**
     * GET /api/admin/users/{id}
     */
    public function show(Request $request, $id)
    {
        $auth = auth()->user();
        if (!$auth || !in_array($auth->role, ['admin']) && !$auth->is_superadmin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['user' => $user]);
    }

    /**
     * PUT /api/admin/users/{id}
     * Admin can update user's basic info + role.
     */
    public function update(Request $request, $id)
    {
        $auth = auth()->user();
        if (!$auth || !in_array($auth->role, ['admin']) && !$auth->is_superadmin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Cannot modify a superadmin unless current is superadmin
        if ($user->isSuperAdmin() && !$auth->isSuperAdmin()) {
            return response()->json(['message' => 'You cannot modify a superadmin.'], 403);
        }

        $data = $request->validate([
            'name'       => 'sometimes|required|string|max:255',
            'first_name' => 'sometimes|required|string|max:255',
            'last_name'  => 'sometimes|required|string|max:255',
            'email'      => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password'   => 'sometimes|nullable|string|min:6',
            'role'       => 'sometimes|required|in:admin,aesthetician,client',
        ]);

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['first_name'])) {
            $user->first_name = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $user->last_name = $data['last_name'];
        }
        // Email cannot be changed (read-only)
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        if (isset($data['role'])) {
            // prevent demoting the last superadmin
            if ($user->isSuperAdmin() && !$auth->isSuperAdmin()) {
                return response()->json(['message' => 'You cannot change role of a superadmin.'], 403);
            }
            $user->role = $data['role'];
            
            // Automatically verify email when admin changes user role (for all roles)
            if (!$user->email_verified_at) {
                $user->email_verified_at = now();
            }
        }

        $user->save();

        return response()->json([
            'message' => 'User updated.',
            'user'    => $user,
        ]);
    }

    /**
     * DELETE /api/admin/users/{id}
     * Superadmin cannot be deleted, and user cannot delete themselves (optional).
     */
    public function destroy(Request $request, $id)
    {
        $auth = auth()->user();
        if (!$auth || !in_array($auth->role, ['admin']) && !$auth->is_superadmin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'You cannot delete a superadmin.'], 403);
        }

        if ($user->id === $auth->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}
