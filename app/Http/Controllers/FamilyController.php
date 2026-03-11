<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyRole;
use App\Models\FamilyUserRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FamilyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $families = Family::query()
            ->where('owner_user_id', $userId)
            ->orWhereHas('userRoles', fn ($query) => $query->where('user_id', $userId))
            ->with('owner:id,name')
            ->latest()
            ->get(['id', 'name', 'owner_user_id']);

        return response()->json($families);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $hasFamilyMembership = Family::query()
            ->where('owner_user_id', $user->id)
            ->orWhereHas('userRoles', fn ($query) => $query->where('user_id', $user->id))
            ->exists();

        if (! $user->hasRole('super-admin') && ! $hasFamilyMembership) {
            abort(403, 'You are not allowed to create a family.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $family = Family::create([
            'name' => $validated['name'],
            'owner_user_id' => $user->id,
        ]);

        $ownerRole = FamilyRole::create([
            'family_id' => $family->id,
            'name' => 'owner',
            'level' => 100,
            'permissions' => ['family.manage', 'roles.manage', 'members.manage'],
        ]);

        FamilyUserRole::create([
            'family_id' => $family->id,
            'user_id' => $user->id,
            'family_role_id' => $ownerRole->id,
        ]);

        return response()->json($family, 201);
    }

    public function update(Family $family, Request $request): JsonResponse
    {
        $this->abortUnlessCanManageFamily($family, $request->user()->id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $family->update([
            'name' => $validated['name'],
        ]);

        return response()->json($family->only(['id', 'name', 'owner_user_id']));
    }

    public function destroy(Family $family, Request $request): JsonResponse
    {
        $isOwner = $family->owner_user_id === $request->user()->id;
        $isSuperAdmin = $request->user()->hasRole('super-admin');

        abort_unless($isOwner || $isSuperAdmin, 403, 'Only the family owner can delete a family.');

        $family->delete();

        return response()->json([
            'message' => 'Family deleted successfully.',
        ]);
    }

    public function show(Family $family, Request $request): JsonResponse
    {
        $this->abortIfNotFamilyMember($family, $request->user()->id);

        $family->load([
            'roles' => fn ($query) => $query->orderByDesc('level'),
            'userRoles.user:id,name,email',
            'userRoles.role:id,name,level,family_id',
        ]);

        return response()->json($family);
    }

    public function roles(Family $family, Request $request): JsonResponse
    {
        $this->abortIfNotFamilyMember($family, $request->user()->id);

        return response()->json(
            $family->roles()->orderByDesc('level')->get(['id', 'family_id', 'name', 'level', 'permissions'])
        );
    }

    public function updateRole(Family $family, FamilyRole $role, Request $request): JsonResponse
    {
        $this->abortIfRoleNotInFamily($family, $role);
        $this->abortUnlessCanManageRoles($family, $request->user()->id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('family_roles', 'name')
                    ->where('family_id', $family->id)
                    ->ignore($role->id),
            ],
            'level' => ['required', 'integer', 'min:1', 'max:99'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:255'],
        ]);

        $actorHighestLevel = $this->getUserHighestFamilyRoleLevel($family, $request->user()->id);

        if ($family->owner_user_id !== $request->user()->id && $validated['level'] > $actorHighestLevel) {
            abort(403, 'You cannot set a role level higher than your own level.');
        }

        $role->update([
            'name' => $validated['name'],
            'level' => $validated['level'],
            'permissions' => $validated['permissions'] ?? [],
        ]);

        return response()->json($role);
    }

    public function destroyRole(Family $family, FamilyRole $role, Request $request): JsonResponse
    {
        $this->abortIfRoleNotInFamily($family, $role);
        $this->abortUnlessCanManageRoles($family, $request->user()->id);

        $isRoleInUse = FamilyUserRole::query()
            ->where('family_id', $family->id)
            ->where('family_role_id', $role->id)
            ->exists();

        abort_if($isRoleInUse, 422, 'Role is assigned to members and cannot be deleted.');

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }

    public function storeRole(Family $family, Request $request): JsonResponse
    {
        $this->abortUnlessCanManageRoles($family, $request->user()->id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('family_roles', 'name')->where('family_id', $family->id)],
            'level' => ['required', 'integer', 'min:1', 'max:99'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:255'],
        ]);

        $role = $family->roles()->create([
            'name' => $validated['name'],
            'level' => $validated['level'],
            'permissions' => $validated['permissions'] ?? [],
        ]);

        return response()->json($role, 201);
    }

    public function assignRole(Family $family, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'family_role_id' => ['required', 'integer', Rule::exists('family_roles', 'id')->where('family_id', $family->id)],
        ]);

        $this->abortUnlessCanManageRoles($family, $request->user()->id);

        $role = FamilyRole::query()->findOrFail($validated['family_role_id']);
        $actorHighestLevel = $this->getUserHighestFamilyRoleLevel($family, $request->user()->id);

        if ($family->owner_user_id !== $request->user()->id && $role->level > $actorHighestLevel) {
            abort(403, 'You cannot assign a role higher than your own level.');
        }

        User::query()->findOrFail($validated['user_id']);

        FamilyUserRole::updateOrCreate(
            [
                'family_id' => $family->id,
                'user_id' => $validated['user_id'],
            ],
            [
                'family_role_id' => $role->id,
            ]
        );

        return response()->json([
            'message' => 'Role assigned successfully.',
        ]);
    }

    public function members(Family $family, Request $request): JsonResponse
    {
        $this->abortIfNotFamilyMember($family, $request->user()->id);

        $members = FamilyUserRole::query()
            ->where('family_id', $family->id)
            ->with([
                'user:id,name,email',
                'role:id,name,level,family_id',
            ])
            ->get(['id', 'family_id', 'user_id', 'family_role_id']);

        return response()->json($members);
    }

    public function addMember(Family $family, Request $request): JsonResponse
    {
        $this->abortUnlessCanManageMembers($family, $request->user()->id);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'family_role_id' => ['required', 'integer', Rule::exists('family_roles', 'id')->where('family_id', $family->id)],
        ]);

        $isAlreadyMember = FamilyUserRole::query()
            ->where('family_id', $family->id)
            ->where('user_id', $validated['user_id'])
            ->exists();

        abort_if($isAlreadyMember, 422, 'User is already a member of this family.');

        $role = FamilyRole::query()->findOrFail($validated['family_role_id']);
        $actorHighestLevel = $this->getUserHighestFamilyRoleLevel($family, $request->user()->id);

        if ($family->owner_user_id !== $request->user()->id && $role->level > $actorHighestLevel) {
            abort(403, 'You cannot assign a role higher than your own level.');
        }

        $member = FamilyUserRole::create([
            'family_id' => $family->id,
            'user_id' => $validated['user_id'],
            'family_role_id' => $role->id,
        ]);

        $member->load([
            'user:id,name,email',
            'role:id,name,level,family_id',
        ]);

        return response()->json($member, 201);
    }

    public function updateMemberRole(Family $family, User $user, Request $request): JsonResponse
    {
        $this->abortUnlessCanManageMembers($family, $request->user()->id);

        abort_if($family->owner_user_id === $user->id, 422, 'Family owner role cannot be changed.');

        $validated = $request->validate([
            'family_role_id' => ['required', 'integer', Rule::exists('family_roles', 'id')->where('family_id', $family->id)],
        ]);

        $memberAssignment = FamilyUserRole::query()
            ->where('family_id', $family->id)
            ->where('user_id', $user->id)
            ->first();

        abort_if(! $memberAssignment, 404, 'Member not found in this family.');

        $role = FamilyRole::query()->findOrFail($validated['family_role_id']);
        $actorHighestLevel = $this->getUserHighestFamilyRoleLevel($family, $request->user()->id);

        if ($family->owner_user_id !== $request->user()->id && $role->level > $actorHighestLevel) {
            abort(403, 'You cannot assign a role higher than your own level.');
        }

        $memberAssignment->update([
            'family_role_id' => $role->id,
        ]);

        $memberAssignment->load([
            'user:id,name,email',
            'role:id,name,level,family_id',
        ]);

        return response()->json($memberAssignment);
    }

    public function removeMember(Family $family, User $user, Request $request): JsonResponse
    {
        $this->abortUnlessCanManageMembers($family, $request->user()->id);

        abort_if($family->owner_user_id === $user->id, 422, 'Family owner cannot be removed.');

        $deleted = FamilyUserRole::query()
            ->where('family_id', $family->id)
            ->where('user_id', $user->id)
            ->delete();

        abort_if($deleted === 0, 404, 'Member not found in this family.');

        return response()->json([
            'message' => 'Member removed successfully.',
        ]);
    }

    public function myPermissions(Family $family, Request $request): JsonResponse
    {
        $assignment = FamilyUserRole::query()
            ->where('family_id', $family->id)
            ->where('user_id', $request->user()->id)
            ->with('role')
            ->first();

        if (! $assignment) {
            abort(403, 'You are not a member of this family.');
        }

        $level = $assignment->role->level;

        $inheritedPermissions = $family->roles()
            ->where('level', '<=', $level)
            ->pluck('permissions')
            ->flatten(1)
            ->filter()
            ->values()
            ->unique()
            ->values();

        return response()->json([
            'family_id' => $family->id,
            'role' => [
                'id' => $assignment->role->id,
                'name' => $assignment->role->name,
                'level' => $assignment->role->level,
            ],
            'effective_permissions' => $inheritedPermissions,
        ]);
    }

    private function abortIfNotFamilyMember(Family $family, int $userId): void
    {
        $isMember = $family->owner_user_id === $userId || FamilyUserRole::query()
            ->where('family_id', $family->id)
            ->where('user_id', $userId)
            ->exists();

        abort_unless($isMember, 403);
    }

    private function abortUnlessCanManageRoles(Family $family, int $userId): void
    {
        if ($family->owner_user_id === $userId) {
            return;
        }

        $assignment = FamilyUserRole::query()
            ->where('family_id', $family->id)
            ->where('user_id', $userId)
            ->with('role')
            ->first();

        if (! $assignment) {
            abort(403, 'You are not a member of this family.');
        }

        $permissions = collect($assignment->role->permissions ?? []);

        abort_unless($permissions->contains('roles.manage') || $assignment->role->level >= 90, 403);
    }

    private function abortUnlessCanManageMembers(Family $family, int $userId): void
    {
        if ($family->owner_user_id === $userId) {
            return;
        }

        $assignment = FamilyUserRole::query()
            ->where('family_id', $family->id)
            ->where('user_id', $userId)
            ->with('role')
            ->first();

        if (! $assignment) {
            abort(403, 'You are not a member of this family.');
        }

        $permissions = collect($assignment->role->permissions ?? []);

        abort_unless(
            $permissions->contains('members.manage') || $permissions->contains('roles.manage') || $assignment->role->level >= 90,
            403
        );
    }

    private function abortUnlessCanManageFamily(Family $family, int $userId): void
    {
        if ($family->owner_user_id === $userId) {
            return;
        }

        $assignment = FamilyUserRole::query()
            ->where('family_id', $family->id)
            ->where('user_id', $userId)
            ->with('role')
            ->first();

        if (! $assignment) {
            abort(403, 'You are not a member of this family.');
        }

        $permissions = collect($assignment->role->permissions ?? []);

        abort_unless(
            $permissions->contains('family.manage') || $permissions->contains('roles.manage') || $assignment->role->level >= 90,
            403
        );
    }

    private function abortIfRoleNotInFamily(Family $family, FamilyRole $role): void
    {
        abort_if($role->family_id !== $family->id, 404);
    }

    private function getUserHighestFamilyRoleLevel(Family $family, int $userId): int
    {
        if ($family->owner_user_id === $userId) {
            return 100;
        }

        return (int) FamilyUserRole::query()
            ->where('family_user_roles.family_id', $family->id)
            ->where('family_user_roles.user_id', $userId)
            ->join('family_roles', 'family_roles.id', '=', 'family_user_roles.family_role_id')
            ->max('family_roles.level');
    }
}