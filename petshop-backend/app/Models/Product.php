<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    protected $guarded = [];

    protected $appends = ['stores_stock', 'total_stock', 'quantity'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'store_product_stock')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function getStoresStockAttribute()
    {
        $allStores = Store::where('is_active', true)->get();
        if ($allStores->isEmpty()) {
            return [];
        }

        $stockMap = [];
        if ($this->relationLoaded('stores') && $this->stores->isNotEmpty()) {
            foreach ($this->stores as $st) {
                $stockMap[$st->id] = (float) ($st->pivot->quantity ?? 0);
            }
        } else {
            $stocks = DB::table('store_product_stock')
                ->where('product_id', $this->id)
                ->get();
            foreach ($stocks as $st) {
                $stockMap[$st->store_id] = (float) $st->quantity;
            }
        }

        return $allStores->map(function ($store) use ($stockMap) {
            $qty = isset($stockMap[$store->id]) 
                ? $stockMap[$store->id] 
                : ($store->id === 1 ? (float) ($this->stock_quantity ?? 0) : 0.0);

            return [
                'store_id'   => $store->id,
                'store_name' => $store->name,
                'quantity'   => (float) $qty,
            ];
        })->values();
    }

    public function getTotalStockAttribute()
    {
        return (float) ($this->stock_quantity ?? 0);
    }

    public function getQuantityAttribute()
    {
        return (float) ($this->stock_quantity ?? 0);
    }
}

