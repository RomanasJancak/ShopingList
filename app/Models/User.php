<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'google_avatar',
        'default_shopping_list_id',
        'load_default_shopping_list_on_login',
        'show_product_pictures_in_shopping_list',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'load_default_shopping_list_on_login' => 'boolean',
            'show_product_pictures_in_shopping_list' => 'boolean',
        ];
    }

    public function ownedFamilies(): HasMany
    {
        return $this->hasMany(Family::class, 'owner_user_id');
    }

    public function familyRoles(): HasMany
    {
        return $this->hasMany(FamilyUserRole::class);
    }

    public function families(): BelongsToMany
    {
        return $this->belongsToMany(Family::class, 'family_user_roles');
    }

    public function ownedShoppingLists(): HasMany
    {
        return $this->hasMany(ShoppingList::class, 'owner_user_id');
    }

    public function shoppingListShares(): HasMany
    {
        return $this->hasMany(ShoppingListUser::class);
    }

    public function familyShoppingListShares(): HasMany
    {
        return $this->hasMany(ShoppingListFamilyUser::class);
    }
}
