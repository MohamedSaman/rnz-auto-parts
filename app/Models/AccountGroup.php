<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',      // asset, liability, income, expense, equity
        'nature',    // debit, credit
        'parent_id',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ── Relationships ──

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AccountGroup::class, 'parent_id');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'group_id');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ── Helpers ──

    /**
     * Check if this group's nature is debit
     */
    public function isDebitNature(): bool
    {
        return $this->nature === 'debit';
    }

    /**
     * Get all account IDs under this group and its sub-groups (recursive)
     */
    public function getAllAccountIds(): array
    {
        $ids = $this->accounts()->pluck('id')->toArray();

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllAccountIds());
        }

        return $ids;
    }

    /**
     * Get all group IDs including this group and all descendants
     */
    public function getAllGroupIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllGroupIds());
        }

        return $ids;
    }
}
