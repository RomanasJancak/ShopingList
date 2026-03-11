<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingListUser extends Model
{
    use HasFactory;

    protected $table = 'shopping_list_user';

    protected $fillable = [
        'shopping_list_id',
        'user_id',
        'permission',
    ];

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}