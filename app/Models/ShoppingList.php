<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingList extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'owner_user_id',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function userShares(): HasMany
    {
        return $this->hasMany(ShoppingListUser::class);
    }

    public function familyShares(): HasMany
    {
        return $this->hasMany(ShoppingListFamily::class);
    }

    public function familyMemberShares(): HasMany
    {
        return $this->hasMany(ShoppingListFamilyUser::class);
    }
}