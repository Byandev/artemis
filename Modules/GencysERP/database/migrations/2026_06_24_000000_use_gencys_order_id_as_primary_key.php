<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The tracking number is no longer the upsert key. Drop its unique index
        // if the original create migration left one behind.
        $trackingUnique = 'gencys_daily_sales_orders_workspace_id_tracking_number_unique';
        if ($this->indexExists($trackingUnique)) {
            Schema::table('gencys_daily_sales_orders', function (Blueprint $table) use ($trackingUnique) {
                $table->dropUnique($trackingUnique);
            });
        }

        // The items FK references `id`, so MySQL won't let us alter it in place.
        Schema::table('gencys_daily_sales_order_items', function (Blueprint $table) {
            $table->dropForeign('gdso_items_order_id_fk');
        });

        // Stop auto-incrementing `id` so it can hold Gencys' own order id (the
        // `id` Gencys sends, e.g. 921462). That id is the stable natural key we
        // upsert on — a tracking number can be blank or change, the order id can't.
        DB::statement('ALTER TABLE gencys_daily_sales_orders MODIFY id BIGINT UNSIGNED NOT NULL');

        Schema::table('gencys_daily_sales_order_items', function (Blueprint $table) {
            $table->foreign('order_id', 'gdso_items_order_id_fk')
                ->references('id')->on('gencys_daily_sales_orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gencys_daily_sales_order_items', function (Blueprint $table) {
            $table->dropForeign('gdso_items_order_id_fk');
        });

        DB::statement('ALTER TABLE gencys_daily_sales_orders MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        Schema::table('gencys_daily_sales_order_items', function (Blueprint $table) {
            $table->foreign('order_id', 'gdso_items_order_id_fk')
                ->references('id')->on('gencys_daily_sales_orders')
                ->cascadeOnDelete();
        });

        Schema::table('gencys_daily_sales_orders', function (Blueprint $table) {
            $table->unique(['workspace_id', 'tracking_number']);
        });
    }

    private function indexExists(string $name): bool
    {
        return ! empty(DB::select(
            'SHOW INDEX FROM gencys_daily_sales_orders WHERE Key_name = ?',
            [$name],
        ));
    }
};
