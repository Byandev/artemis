<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product; // Assuming your Product model is in the main app
use App\Models\Workspace;

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