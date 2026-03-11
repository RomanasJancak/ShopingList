<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingListFamily extends Model
{
    use HasFactory;

    protected $table = 'shopping_list_family';

    protected $fillable = [
        'shopping_list_id',
        'family_id',
        'permission',
    ];

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}