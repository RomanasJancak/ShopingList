<?php

namespace App\Support;

use App\Models\Family;
use App\Models\ShoppingList;
use App\Models\ShoppingListFamily;
use App\Models\ShoppingListFamilyUser;
use App\Models\ShoppingListUser;
use App\Models\User;
use Illuminate\Support\Collection;

class UserShoppingListPreferenceResolver
{
    public function getAccessibleShoppingLists(User $user): Collection
    {
        $familyIds = $this->getUserFamilyIds($user->id);

        return ShoppingList::query()
            ->where('owner_user_id', $user->id)
            ->orWhereHas('userShares', fn ($query) => $query->where('user_id', $user->id))
            ->orWhereHas('familyShares', fn ($query) => $query->whereIn('family_id', $familyIds))
            ->orWhereHas('familyMemberShares', fn ($query) => $query->where('user_id', $user->id)->whereIn('family_id', $familyIds))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function resolveDefaultShoppingList(User $user): ?ShoppingList
    {
        $lists = $this->getAccessibleShoppingLists($user);

        if ($lists->isEmpty()) {
            if ($user->default_shopping_list_id !== null) {
                $user->forceFill(['default_shopping_list_id' => null])->save();
            }

            return null;
        }

        if ($lists->count() === 1) {
            $singleList = $lists->first();

            if ((int) $user->default_shopping_list_id !== (int) $singleList->id) {
                $user->forceFill(['default_shopping_list_id' => $singleList->id])->save();
            }

            return $singleList;
        }

        if ($user->default_shopping_list_id !== null) {
            $selected = $lists->firstWhere('id', (int) $user->default_shopping_list_id);
            if ($selected) {
                return $selected;
            }

            $user->forceFill(['default_shopping_list_id' => null])->save();
        }

        return null;
    }

    public function resolvePostLoginRoute(User $user): string
    {
        $defaultList = $this->resolveDefaultShoppingList($user);

        if ($user->load_default_shopping_list_on_login && $defaultList) {
            return route('shopping-list.view', ['id' => $defaultList->id]);
        }

        return route('shopping-lists.index');
    }

    private function getUserFamilyIds(int $userId): Collection
    {
        return Family::query()
            ->where('owner_user_id', $userId)
            ->orWhereHas('userRoles', fn ($query) => $query->where('user_id', $userId))
            ->pluck('id')
            ->unique()
            ->values();
    }
}
