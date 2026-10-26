<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\PlanLimitService;
use App\Support\Permissions;
use App\Support\StoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    use ApiResponses;

    public function __construct(protected PlanLimitService $planLimits) {}

    public function index(): JsonResponse
    {
        $users = User::where('store_id', StoreContext::id())
            ->with('roles')
            ->orderBy('name')
            ->get();

        return $this->ok(UserResource::collection($users)->resolve());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'role' => ['required', Rule::in(Permissions::roles())],
        ]);

        $this->planLimits->assertCanAddUser((int) StoreContext::id());

        $user = User::create([
            'store_id' => StoreContext::id(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        $user->assignRole($data['role']);

        return $this->created(new UserResource($user->load('roles')));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($user->store_id === StoreContext::id(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'confirmed', Password::min(6)],
            'role' => ['required', Rule::in(Permissions::roles())],
            'is_active' => ['boolean'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? $user->is_active,
            ...(! empty($data['password']) ? ['password' => Hash::make($data['password'])] : []),
        ]);

        $user->syncRoles([$data['role']]);

        return $this->ok(new UserResource($user->load('roles')));
    }

    public function destroy(User $user): JsonResponse
    {
        abort_unless($user->store_id === StoreContext::id(), 403);
        abort_if($user->hasRole(Permissions::ROLE_OWNER), 403, 'Cannot delete the store owner.');

        $user->delete();

        return $this->ok(null, 'Deleted.');
    }

    public function roles(): JsonResponse
    {
        return $this->ok(Permissions::roles());
    }
}
