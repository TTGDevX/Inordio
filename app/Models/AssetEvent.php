<?php

namespace App\Models;

use App\Enums\AssetEventType;
use Database\Factories\AssetEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable([
    'serialized_asset_id',
    'parent_asset_id',
    'type',
    'location_id',
    'user_id',
    'note',
])]
class AssetEvent extends Model
{
    /** @use HasFactory<AssetEventFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => AssetEventType::class,
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(SerializedAsset::class, 'serialized_asset_id');
    }

    public function parentAsset(): BelongsTo
    {
        return $this->belongsTo(SerializedAsset::class, 'parent_asset_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
