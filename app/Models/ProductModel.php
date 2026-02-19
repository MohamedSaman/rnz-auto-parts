<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductModel extends Model
{
    use HasFactory;

    protected $table = 'product_models';

    protected $fillable = [
        'model_name',
        'status',
    ];

    /**
     * Products that belong to this model.
     */
    public function products(): HasMany
    {
        return $this->hasMany(ProductDetail::class, 'model_id');
    }
}
