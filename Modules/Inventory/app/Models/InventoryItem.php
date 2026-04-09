<?php

namespace Modules\Inventory\Models;

use App\Models\Product;
use App\Models\Workspace; // Assuming your Product model is in the main app
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $table = 'inventory_items';

    protected $fillable = [
        'workspace_id',
        'product_id',
        'sku',
        'sales_keywords',
        'transaction_keywords',
    ];

    /**
     * Relationship to the Workspace
     */
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
