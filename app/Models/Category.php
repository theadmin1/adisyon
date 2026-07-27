<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'sync_uuid',
        'is_synced',
        'name',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected static function booted(): void
    {
        static::creating(function ($category) {
            if (empty($category->sync_uuid)) {
                $category->sync_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });

        static::deleting(function ($category) {
            if (empty($category->sync_uuid)) {
                $category->sync_uuid = (string) \Illuminate\Support\Str::uuid();
                \Illuminate\Support\Facades\DB::table('categories')->where('id', $category->id)->update(['sync_uuid' => $category->sync_uuid]);
            }
            
            if (\Illuminate\Support\Facades\Schema::hasTable('deleted_records')) {
                try {
                    \Illuminate\Support\Facades\DB::table('deleted_records')->updateOrInsert(
                        ['sync_uuid' => $category->sync_uuid, 'type' => 'category'],
                        [
                            'record_id' => $category->id,
                            'name' => $category->name,
                            'is_synced' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                } catch (\Throwable $e) {}
            }
        });
    }
}
