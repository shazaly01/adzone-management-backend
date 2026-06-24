<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);

        $users = User::with(['roles', 'store', 'treasury', 'bank'])
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('type', $request->type);
            })
            ->latest()
            ->paginate($perPage);

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $userData = [
                'full_name'   => $validated['full_name'],
                'username'    => $validated['username'],
                'email'       => $validated['email'] ?? null,
                'password'    => Hash::make($validated['password']),
                'type'        => $validated['type'],
                'store_id'    => $validated['store_id'] ?? null,
                'treasury_id' => $validated['treasury_id'] ?? null,
                'bank_id'     => $validated['bank_id'] ?? null,
            ];

            $user = User::create($userData);

            $user->assignRole($validated['roles']);
            DB::commit();

            return new UserResource($user->load(['roles', 'store', 'treasury', 'bank']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create user.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(User $user)
    {
        return new UserResource($user->load(['roles', 'store', 'treasury', 'bank']));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $userData = [
                'full_name'   => $validated['full_name'],
                'username'    => $validated['username'],
                'email'       => $validated['email'] ?? $user->email,
                'type'        => $validated['type'],
                'store_id'    => $validated['store_id'] ?? $user->store_id,
                'treasury_id' => $validated['treasury_id'] ?? $user->treasury_id,
                'bank_id'     => $validated['bank_id'] ?? $user->bank_id,
            ];

            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);

            $user->syncRoles($validated['roles']);
            DB::commit();

            return new UserResource($user->fresh()->load(['roles', 'store', 'treasury', 'bank']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update user.', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(User $user)
    {
        if ($user->hasRole('Super Admin')) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot delete a Super Admin user.');
        }

        $user->delete();
        return response()->noContent();
    }
}
