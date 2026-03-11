<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyUserRole;
use App\Models\ShoppingList;
use App\Models\ShoppingListFamily;
use App\Models\ShoppingListFamilyUser;
use App\Models\ShoppingListUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShoppingListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $familyIds = $this->getUserFamilyIds($userId);

        $lists = ShoppingList::query()
            ->where('owner_user_id', $userId)
            ->orWhereHas('userShares', fn ($query) => $query->where('user_id', $userId))
            ->orWhereHas('familyShares', fn ($query) => $query->whereIn('family_id', $familyIds))
            ->orWhereHas('familyMemberShares', fn ($query) => $query->where('user_id', $userId)->whereIn('family_id', $familyIds))
            ->with('owner:id,name')
            ->latest()
            ->get(['id', 'name', 'description', 'owner_user_id']);

        return response()->json(
            $lists->map(function (ShoppingList $shoppingList) use ($userId, $familyIds) {
                return [
                    'id' => $shoppingList->id,
                    'name' => $shoppingList->name,
                    'description' => $shoppingList->description,
                    'owner_user_id' => $shoppingList->owner_user_id,
                    'owner' => $shoppingList->owner,
                    'effective_permission' => $this->resolveEffectivePermission($shoppingList, $userId, $familyIds),
                ];
            })->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $shoppingList = ShoppingList::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'owner_user_id' => $request->user()->id,
        ]);

        ShoppingListUser::create([
            'shopping_list_id' => $shoppingList->id,
            'user_id' => $request->user()->id,
            'permission' => 'owner',
        ]);

        return response()->json([
            'id' => $shoppingList->id,
            'name' => $shoppingList->name,
            'description' => $shoppingList->description,
            'owner_user_id' => $shoppingList->owner_user_id,
            'effective_permission' => 'owner',
        ], 201);
    }

    public function show(ShoppingList $shoppingList, Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $familyIds = $this->getUserFamilyIds($userId);

        $this->abortUnlessListVisible($shoppingList, $userId, $familyIds);

        $shoppingList->load([
            'owner:id,name,email',
            'userShares.user:id,name,email',
            'familyShares.family:id,name,owner_user_id',
            'familyMemberShares.family:id,name,owner_user_id',
            'familyMemberShares.user:id,name,email',
        ]);

        return response()->json([
            'id' => $shoppingList->id,
            'name' => $shoppingList->name,
            'description' => $shoppingList->description,
            'owner_user_id' => $shoppingList->owner_user_id,
            'owner' => $shoppingList->owner,
            'effective_permission' => $this->resolveEffectivePermission($shoppingList, $userId, $familyIds),
            'user_shares' => $shoppingList->userShares,
            'family_shares' => $shoppingList->familyShares,
            'family_member_shares' => $shoppingList->familyMemberShares,
        ]);
    }

    public function update(ShoppingList $shoppingList, Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $familyIds = $this->getUserFamilyIds($userId);

        $this->abortUnlessListEditable($shoppingList, $userId, $familyIds);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $shoppingList->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'id' => $shoppingList->id,
            'name' => $shoppingList->name,
            'description' => $shoppingList->description,
            'owner_user_id' => $shoppingList->owner_user_id,
        ]);
    }

    public function destroy(ShoppingList $shoppingList, Request $request): JsonResponse
    {
        $this->abortUnlessListOwner($shoppingList, $request->user()->id);

        $shoppingList->delete();

        return response()->json([
            'message' => 'Shopping list deleted successfully.',
        ]);
    }

    public function shareUser(ShoppingList $shoppingList, Request $request): JsonResponse
    {
        $this->abortUnlessListOwner($shoppingList, $request->user()->id);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'permission' => ['required', 'string', Rule::in(['view', 'edit'])],
        ]);

        abort_if($validated['user_id'] === $shoppingList->owner_user_id, 422, 'Owner already has full access.');

        $share = ShoppingListUser::query()->updateOrCreate(
            [
                'shopping_list_id' => $shoppingList->id,
                'user_id' => $validated['user_id'],
            ],
            [
                'permission' => $validated['permission'],
            ]
        );

        $share->load('user:id,name,email');

        return response()->json($share, 201);
    }

    public function updateUserShare(ShoppingList $shoppingList, int $userId, Request $request): JsonResponse
    {
        $this->abortUnlessListOwner($shoppingList, $request->user()->id);

        $validated = $request->validate([
            'permission' => ['required', 'string', Rule::in(['view', 'edit'])],
        ]);

        abort_if($userId === $shoppingList->owner_user_id, 422, 'Owner permission cannot be changed.');

        $share = ShoppingListUser::query()
            ->where('shopping_list_id', $shoppingList->id)
            ->where('user_id', $userId)
            ->first();

        abort_if(! $share, 404, 'Direct user share not found.');

        $share->update([
            'permission' => $validated['permission'],
        ]);

        $share->load('user:id,name,email');

        return response()->json($share);
    }

    public function removeUserShare(ShoppingList $shoppingList, int $userId, Request $request): JsonResponse
    {
        $this->abortUnlessListOwner($shoppingList, $request->user()->id);

        abort_if($userId === $shoppingList->owner_user_id, 422, 'Owner access cannot be removed.');

        $deleted = ShoppingListUser::query()
            ->where('shopping_list_id', $shoppingList->id)
            ->where('user_id', $userId)
            ->delete();

        abort_if($deleted === 0, 404, 'Direct user share not found.');

        return response()->json([
            'message' => 'User share removed successfully.',
        ]);
    }

    public function shareFamily(ShoppingList $shoppingList, Request $request): JsonResponse
    {
        $this->abortUnlessListOwner($shoppingList, $request->user()->id);

        $validated = $request->validate([
            'family_id' => ['required', 'integer', 'exists:families,id'],
            'permission' => ['required', 'string', Rule::in(['view', 'edit'])],
        ]);

        $this->abortUnlessUserBelongsToFamily($validated['family_id'], $request->user()->id);

        $share = ShoppingListFamily::query()->updateOrCreate(
            [
                'shopping_list_id' => $shoppingList->id,
                'family_id' => $validated['family_id'],
            ],
            [
                'permission' => $validated['permission'],
            ]
        );

        $share->load('family:id,name,owner_user_id');

        return response()->json($share, 201);
    }

    public function updateFamilyShare(ShoppingList $shoppingList, Family $family, Request $request): JsonResponse
    {
        $this->abortUnlessListOwner($shoppingList, $request->user()->id);
        $this->abortUnlessUserBelongsToFamily($family->id, $request->user()->id);

        $validated = $request->validate([
            'permission' => ['required', 'string', Rule::in(['view', 'edit'])],
        ]);

        $share = ShoppingListFamily::query()
            ->where('shopping_list_id', $shoppingList->id)
            ->where('family_id', $family->id)
            ->first();

        abort_if(! $share, 404, 'Family share not found.');

        $share->update([
            'permission' => $validated['permission'],
        ]);

        $share->load('family:id,name,owner_user_id');

        return response()->json($share);
    }

    public function removeFamilyShare(ShoppingList $shoppingList, Family $family, Request $request): JsonResponse
    {
        $this->abortUnlessListOwner($shoppingList, $request->user()->id);
        $this->abortUnlessUserBelongsToFamily($family->id, $request->user()->id);

        $deleted = ShoppingListFamily::query()
            ->where('shopping_list_id', $shoppingList->id)
            ->where('family_id', $family->id)
            ->delete();

        abort_if($deleted === 0, 404, 'Family share not found.');

        ShoppingListFamilyUser::query()
            ->where('shopping_list_id', $shoppingList->id)
            ->where('family_id', $family->id)
            ->delete();

        return response()->json([
            'message' => 'Family share removed successfully.',
        ]);
    }

    public function shareFamilyMember(ShoppingList $shoppingList, Family $family, Request $request): JsonResponse
    {
        $this->abortUnlessListOwner($shoppingList, $request->user()->id);
        $this->abortUnlessUserBelongsToFamily($family->id, $request->user()->id);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'permission' => ['required', 'string', Rule::in(['view', 'edit'])],
        ]);

        $this->abortUnlessUserBelongsToFamily($family->id, $validated['user_id']);

        $share = ShoppingListFamilyUser::query()->updateOrCreate(
            [
                'shopping_list_id' => $shoppingList->id,
                'family_id' => $family->id,
                'user_id' => $validated['user_id'],
            ],
            [
                'permission' => $validated['permission'],
            ]
        );

        $share->load([
            'family:id,name,owner_user_id',
            'user:id,name,email',
        ]);

        return response()->json($share, 201);
    }

    public function updateFamilyMemberShare(ShoppingList $shoppingList, Family $family, int $userId, Request $request): JsonResponse
    {
        $this->abortUnlessListOwner($shoppingList, $request->user()->id);
        $this->abortUnlessUserBelongsToFamily($family->id, $request->user()->id);

        $validated = $request->validate([
            'permission' => ['required', 'string', Rule::in(['view', 'edit'])],
        ]);

        $share = ShoppingListFamilyUser::query()
            ->where('shopping_list_id', $shoppingList->id)
            ->where('family_id', $family->id)
            ->where('user_id', $userId)
            ->first();

        abort_if(! $share, 404, 'Family member share not found.');

        $share->update([
            'permission' => $validated['permission'],
        ]);

        $share->load([
            'family:id,name,owner_user_id',
            'user:id,name,email',
        ]);

        return response()->json($share);
    }

    public function removeFamilyMemberShare(ShoppingList $shoppingList, Family $family, int $userId, Request $request): JsonResponse
    {
        $this->abortUnlessListOwner($shoppingList, $request->user()->id);
        $this->abortUnlessUserBelongsToFamily($family->id, $request->user()->id);

        $deleted = ShoppingListFamilyUser::query()
            ->where('shopping_list_id', $shoppingList->id)
            ->where('family_id', $family->id)
            ->where('user_id', $userId)
            ->delete();

        abort_if($deleted === 0, 404, 'Family member share not found.');

        return response()->json([
            'message' => 'Family member share removed successfully.',
        ]);
    }

    private function abortUnlessListVisible(ShoppingList $shoppingList, int $userId, $familyIds): void
    {
        abort_unless($this->resolveEffectivePermission($shoppingList, $userId, $familyIds) !== null, 403);
    }

    private function abortUnlessListEditable(ShoppingList $shoppingList, int $userId, $familyIds): void
    {
        $permission = $this->resolveEffectivePermission($shoppingList, $userId, $familyIds);

        abort_unless($this->permissionRank($permission) >= $this->permissionRank('edit'), 403);
    }

    private function abortUnlessListOwner(ShoppingList $shoppingList, int $userId): void
    {
        abort_unless($shoppingList->owner_user_id === $userId, 403);
    }

    private function abortUnlessUserBelongsToFamily(int $familyId, int $userId): void
    {
        $isMember = Family::query()
            ->where('id', $familyId)
            ->where(function ($query) use ($userId) {
                $query->where('owner_user_id', $userId)
                    ->orWhereHas('userRoles', fn ($familyUserRoles) => $familyUserRoles->where('user_id', $userId));
            })
            ->exists();

        abort_unless($isMember, 403, 'User must belong to the selected family.');
    }

    private function getUserFamilyIds(int $userId)
    {
        return Family::query()
            ->where('owner_user_id', $userId)
            ->orWhereHas('userRoles', fn ($query) => $query->where('user_id', $userId))
            ->pluck('id')
            ->unique()
            ->values();
    }

    private function resolveEffectivePermission(ShoppingList $shoppingList, int $userId, $familyIds): ?string
    {
        $permission = null;

        if ($shoppingList->owner_user_id === $userId) {
            $permission = 'owner';
        }

        $directPermission = ShoppingListUser::query()
            ->where('shopping_list_id', $shoppingList->id)
            ->where('user_id', $userId)
            ->value('permission');

        $permission = $this->higherPermission($permission, $directPermission);

        if ($familyIds->isNotEmpty()) {
            $familyPermission = ShoppingListFamily::query()
                ->where('shopping_list_id', $shoppingList->id)
                ->whereIn('family_id', $familyIds)
                ->get(['permission'])
                ->pluck('permission')
                ->reduce(fn (?string $carry, string $item) => $this->higherPermission($carry, $item), $permission);

            $permission = $familyPermission;

            $memberPermission = ShoppingListFamilyUser::query()
                ->where('shopping_list_id', $shoppingList->id)
                ->where('user_id', $userId)
                ->whereIn('family_id', $familyIds)
                ->get(['permission'])
                ->pluck('permission')
                ->reduce(fn (?string $carry, string $item) => $this->higherPermission($carry, $item), $permission);

            $permission = $memberPermission;
        }

        return $permission;
    }

    private function higherPermission(?string $current, ?string $candidate): ?string
    {
        if ($candidate === null) {
            return $current;
        }

        return $this->permissionRank($candidate) > $this->permissionRank($current)
            ? $candidate
            : $current;
    }

    private function permissionRank(?string $permission): int
    {
        return match ($permission) {
            'owner' => 3,
            'edit' => 2,
            'view' => 1,
            default => 0,
        };
    }
}