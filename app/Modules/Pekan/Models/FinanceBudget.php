<?php

namespace App\Modules\Pekan\Models;

use App\Core\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceBudget extends Model
{
    use SoftDeletes;

    protected $connection = 'pekan';
    protected $table = 'finance_budgets';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'category_id',
        'amount_limit_minor',
        'spent_amount_minor',
        'currency',
        'period',
        'start_date',
        'end_date',
        'alert_threshold_pct',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount_limit_minor' => 'integer',
        'spent_amount_minor' => 'integer',
        'alert_threshold_pct' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected $appends = [
        'category_name',
        'progress_percent',
        'is_past',
        'period_label',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    public function getCategoryNameAttribute(): ?string
    {
        return $this->category?->name;
    }

    public function getDateRange(): array
    {
        $start = $this->start_date ? \Carbon\Carbon::parse($this->start_date) : now();
        $period = $this->period ?: 'monthly';

        if ($this->end_date) {
            $startDate = $start->copy()->startOfDay();
            $endDate = \Carbon\Carbon::parse($this->end_date)->endOfDay();
        } elseif ($period === 'weekly') {
            $startDate = $start->copy()->startOfWeek()->startOfDay();
            $endDate = $start->copy()->endOfWeek()->endOfDay();
        } elseif ($period === 'yearly') {
            $startDate = $start->copy()->startOfYear()->startOfDay();
            $endDate = $start->copy()->endOfYear()->endOfDay();
        } else { // monthly
            $startDate = $start->copy()->startOfMonth()->startOfDay();
            $endDate = $start->copy()->endOfMonth()->endOfDay();
        }

        return [$startDate, $endDate];
    }

    public function recalculateSpentAmount(): int
    {
        [$startDate, $endDate] = $this->getDateRange();

        $categoryIds = [];
        $catWords = [];

        if ($this->category_id) {
            $categoryIds[] = $this->category_id;

            // Masukkan ID subkategori jika ada
            $subCatIds = FinanceCategory::where('tenant_id', $this->tenant_id)
                ->where('parent_id', $this->category_id)
                ->pluck('id')
                ->all();
            $categoryIds = array_merge($categoryIds, $subCatIds);

            // Kata kunci dari nama pos anggaran atau kategori terkait
            $catName = $this->category?->name ?: $this->name;
            $words = array_filter(
                explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $catName))),
                fn($w) => strlen($w) >= 3 && !in_array($w, ['dan', 'yang', 'atau', 'pos', 'anggaran'])
            );
            $catWords = array_values($words);

            if (!empty($catWords)) {
                $similarCats = FinanceCategory::where('tenant_id', $this->tenant_id)
                    ->whereNotIn('id', $categoryIds)
                    ->where(function ($q) use ($catWords) {
                        foreach ($catWords as $word) {
                            $q->orWhere('name', 'like', "%{$word}%");
                        }
                    })
                    ->pluck('id')
                    ->all();
                $categoryIds = array_merge($categoryIds, $similarCats);
            }
        }

        $query = FinanceTransaction::where('tenant_id', $this->tenant_id)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if (!empty($categoryIds)) {
            $query->where(function ($q) use ($categoryIds, $catWords) {
                $q->whereIn('category_id', $categoryIds);
                if (!empty($catWords)) {
                    foreach ($catWords as $word) {
                        $q->orWhere(function ($sub) use ($word) {
                            $sub->whereNull('category_id')->where('description', 'like', "%{$word}%");
                        });
                    }
                }
            });
        }

        $spent = (int) $query->sum('amount_minor');

        if ($this->spent_amount_minor !== $spent) {
            $this->spent_amount_minor = $spent;
            $this->saveQuietly();
        }

        return $spent;
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->amount_limit_minor <= 0) return 0.0;
        return round(($this->spent_amount_minor / $this->amount_limit_minor) * 100, 1);
    }

    public function getIsPastAttribute(): bool
    {
        if ($this->status === 'archived') {
            return true;
        }

        if ($this->end_date && \Carbon\Carbon::parse($this->end_date)->endOfDay()->isPast()) {
            return true;
        }

        if ($this->start_date) {
            $start = \Carbon\Carbon::parse($this->start_date);
            $period = $this->period ?: 'monthly';
            if ($period === 'monthly') {
                return $start->copy()->endOfMonth()->endOfDay()->isPast();
            } elseif ($period === 'weekly') {
                return $start->copy()->endOfWeek()->endOfDay()->isPast();
            } elseif ($period === 'yearly') {
                return $start->copy()->endOfYear()->endOfDay()->isPast();
            }
        }

        return false;
    }

    public function getPeriodLabelAttribute(): string
    {
        if (!$this->start_date) return 'Bulanan';
        $start = \Carbon\Carbon::parse($this->start_date);
        $period = $this->period ?: 'monthly';
        if ($period === 'monthly') {
            return $start->translatedFormat('F Y');
        } elseif ($period === 'weekly') {
            return 'Pekan ' . $start->weekOfYear . ' (' . $start->format('d M Y') . ')';
        }
        return 'Tahun ' . $start->format('Y');
    }
}
