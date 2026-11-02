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

    public function getDateRange(?\Carbon\Carbon $referenceDate = null): array
    {
        $ref = $referenceDate ? $referenceDate->copy() : now();
        $start = $this->start_date ? \Carbon\Carbon::parse($this->start_date) : $ref;
        $period = $this->period ?: 'monthly';

        // Jika memiliki tanggal batas akhir spesifik (misal anggaran berjangka tertentu)
        if ($this->end_date) {
            $startDate = $start->copy()->startOfDay();
            $endDate = \Carbon\Carbon::parse($this->end_date)->endOfDay();
            return [$startDate, $endDate];
        }

        // Anggaran berulang (recurring): evaluasi siklus periode berjalan saat ini
        if ($period === 'weekly') {
            $cycleStart = $ref->copy()->startOfWeek()->startOfDay();
            $cycleEnd = $ref->copy()->endOfWeek()->endOfDay();
        } elseif ($period === 'yearly') {
            $cycleStart = $ref->copy()->startOfYear()->startOfDay();
            $cycleEnd = $ref->copy()->endOfYear()->endOfDay();
        } else { // monthly
            $cycleStart = $ref->copy()->startOfMonth()->startOfDay();
            $cycleEnd = $ref->copy()->endOfMonth()->endOfDay();
        }

        // Jika tanggal mulai berlaku berada di dalam siklus berjalan saat ini
        if ($start->greaterThan($cycleStart) && $start->lessThanOrEqualTo($cycleEnd)) {
            $startDate = $start->copy()->startOfDay();
        } elseif ($start->greaterThan($cycleEnd)) {
            // Anggaran dijadwalkan mulai berlaku di masa depan
            $startDate = $start->copy()->startOfDay();
            $endDate = ($period === 'weekly')
                ? $start->copy()->endOfWeek()->endOfDay()
                : (($period === 'yearly') ? $start->copy()->endOfYear()->endOfDay() : $start->copy()->endOfMonth()->endOfDay());
            return [$startDate, $endDate];
        } else {
            $startDate = $cycleStart;
        }

        $endDate = $cycleEnd;

        return [$startDate, $endDate];
    }

    public function getTransactionsQuery()
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
        } else {
            $words = array_filter(
                explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $this->name))),
                fn($w) => strlen($w) >= 3 && !in_array($w, ['dan', 'yang', 'atau', 'pos', 'anggaran'])
            );
            $catWords = array_values($words);
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
        } elseif (!empty($catWords)) {
            $query->where(function ($q) use ($catWords) {
                foreach ($catWords as $word) {
                    $q->orWhere('description', 'like', "%{$word}%");
                }
            });
        }

        return $query;
    }

    public function recalculateSpentAmount(bool $force = false): int
    {
        // Kunci nominal anggaran jika periodenya sudah berakhir (masuk histori) agar tidak terus bertambah
        if (!$force && ($this->is_past || $this->status === 'archived')) {
            return (int) $this->spent_amount_minor;
        }

        $spent = (int) $this->getTransactionsQuery()->sum('amount_minor');

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
        // Anggaran yang diarsipkan secara manual
        if ($this->status === 'archived' || $this->status === 'inactive') {
            return true;
        }

        // Hanya dianggap telah lewat/berakhir jika memiliki tanggal batas akhir spesifik dan tanggal tersebut telah lewat
        if ($this->end_date && \Carbon\Carbon::parse($this->end_date)->endOfDay()->isPast()) {
            return true;
        }

        // Anggaran berulang tanpa tanggal akhir (ongoing recurring budget) adalah anggaran berjalan
        return false;
    }

    public function getPeriodLabelAttribute(): string
    {
        $period = $this->period ?: 'monthly';
        $now = now();

        if ($this->end_date) {
            $start = $this->start_date ? \Carbon\Carbon::parse($this->start_date) : null;
            $end = \Carbon\Carbon::parse($this->end_date);
            return ($start ? $start->translatedFormat('d M Y') . ' - ' : '') . $end->translatedFormat('d M Y');
        }

        if ($this->status === 'archived') {
            $ref = $this->updated_at ? \Carbon\Carbon::parse($this->updated_at) : ($this->start_date ? \Carbon\Carbon::parse($this->start_date) : $now);
            return 'Diarsipkan (' . $ref->translatedFormat('F Y') . ')';
        }

        if ($this->start_date && \Carbon\Carbon::parse($this->start_date)->startOfDay()->isFuture()) {
            return 'Mulai ' . \Carbon\Carbon::parse($this->start_date)->translatedFormat('d F Y');
        }

        if ($period === 'monthly') {
            return 'Bulanan (' . $now->translatedFormat('F Y') . ')';
        } elseif ($period === 'weekly') {
            return 'Mingguan (Pekan ' . $now->weekOfYear . ')';
        } elseif ($period === 'yearly') {
            return 'Tahunan (' . $now->format('Y') . ')';
        }

        return 'Bulanan';
    }
}
