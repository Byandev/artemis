<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Invoice extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_PAID = 'paid';

    /**
     * Receipts, bank transfer slips, and payment screenshots evidencing that
     * this invoice was settled.
     */
    public const PROOF_COLLECTION = 'proof_of_payment';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(static::PROOF_COLLECTION)
            // One receipt per invoice: uploading again replaces the old file
            // rather than leaving an admin to guess which one is current.
            ->singleFile()
            // No acceptsMimeTypes() here on purpose. What a receipt may be is
            // enforced by request validation, which returns a field error the
            // admin can act on; media-library's own check re-sniffs the mime
            // from the stored bytes and throws, turning a truncated upload or
            // an iPhone HEIC reported as image/heif into a 500.
            ->useDisk(config('filesystems.proof_of_payment_disk'));
    }

    /**
     * The proof of payment on file, if one has been attached.
     */
    public function proofOfPayment(): ?Media
    {
        return $this->getFirstMedia(static::PROOF_COLLECTION);
    }

    protected $fillable = [
        'number',
        'workspace_id',
        'subscription_plan_id',
        'bill_to_name',
        'bill_to_email',
        'bill_to_address',
        'issue_date',
        'due_date',
        'currency',
        'line_items',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'notes',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'line_items' => 'array',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Generate the next sequential invoice number for the given year,
     * formatted as INV-YYYY-000123.
     */
    public static function nextNumber(?int $year = null): string
    {
        $year ??= (int) Carbon::now()->format('Y');

        $lastSequence = static::query()
            ->where('number', 'like', "INV-{$year}-%")
            ->selectRaw('MAX(CAST(SUBSTRING_INDEX(number, "-", -1) AS UNSIGNED)) as seq')
            ->value('seq');

        $next = ((int) $lastSequence) + 1;

        return sprintf('INV-%d-%06d', $year, $next);
    }

    /**
     * Recalculate subtotal / tax / total from the line items and tax rate.
     * Returns the computed line items (with per-row amount filled in).
     */
    public function recalculate(): array
    {
        $items = [];
        $subtotal = 0.0;

        foreach ($this->line_items ?? [] as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $amount = round($quantity * $unitPrice, 2);
            $subtotal += $amount;

            $items[] = [
                'description' => (string) ($item['description'] ?? ''),
                'quantity' => $quantity,
                'unit_price' => round($unitPrice, 2),
                'amount' => $amount,
            ];
        }

        $taxAmount = round($subtotal * ((float) $this->tax_rate / 100), 2);

        $this->line_items = $items;
        $this->subtotal = round($subtotal, 2);
        $this->tax_amount = $taxAmount;
        $this->total = round($subtotal + $taxAmount, 2);

        return $items;
    }
}
