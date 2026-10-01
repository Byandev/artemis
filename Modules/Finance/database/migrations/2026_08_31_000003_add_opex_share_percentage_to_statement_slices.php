<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The share of the month's OPEX pool a row was given, kept beside the amount
     * it produced.
     *
     * Stored rather than re-derived so the allocation stays checkable: the
     * delivered-order counts a percentage was struck from can change with a
     * later sync, and a saved statement should still be able to say what split
     * it actually used.
     *
     * A PERCENTAGE (0–100), not a fraction — unlike the `*_rate` columns on
     * `finance_income_statements`, which hold fractions. The column name carries
     * the unit; keep it that way.
     *
     * Six decimals because the denominator is a month's parcels: at ~35,000
     * delivered orders a single parcel is ~0.003%, and rounding the share to
     * two would blur rows that genuinely differ.
     *
     * Note the amount is NOT simply `percentage × pool`: the pool is split with
     * largest-remainder rounding so the shares add back to it exactly, which
     * moves a centavo or two off the plain product.
     */
    private const TABLES = [
        'finance_income_user_statements',
        'finance_income_product_statements',
        'finance_income_user_product_statements',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->decimal('opex_share_percentage', 9, 6)->default(0)->after('opex');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('opex_share_percentage');
            });
        }
    }
};
