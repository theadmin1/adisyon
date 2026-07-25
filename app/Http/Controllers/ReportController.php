<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Tüm Sistem Raporları & Gün Sonu (Z-Raporu) Portalı
     */
    public function index(Request $request): View
    {
        $period = $request->query('period', 'today'); // today, yesterday, this_week, this_month, custom
        $startDateInput = $request->query('start_date');
        $endDateInput = $request->query('end_date');

        // Tarih Aralığını Belirle
        $now = Carbon::now();
        switch ($period) {
            case 'yesterday':
                $startDate = $now->copy()->subDay()->startOfDay();
                $endDate = $now->copy()->subDay()->endOfDay();
                break;
            case 'this_week':
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
                break;
            case 'this_month':
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                break;
            case 'custom':
                $startDate = $startDateInput ? Carbon::parse($startDateInput)->startOfDay() : $now->copy()->startOfDay();
                $endDate = $endDateInput ? Carbon::parse($endDateInput)->endOfDay() : $now->copy()->endOfDay();
                break;
            case 'today':
            default:
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
                break;
        }

        // Tarih Aralığı Filtreleme Kriteri
        $applyCheckDateFilter = function ($query) use ($startDate, $endDate) {
            return $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('opened_at', [$startDate, $endDate])
                  ->orWhereBetween('closed_at', [$startDate, $endDate])
                  ->orWhereBetween('created_at', [$startDate, $endDate])
                  ->orWhereBetween('updated_at', [$startDate, $endDate]);
            });
        };

        // Eğer seçilen günde hiç kayıt yoksa ve bugün filtrelendiyse, tüm zamanların verilerini otomatik kapsa
        $checkCountForPeriod = $applyCheckDateFilter(Check::query())->count();
        if ($checkCountForPeriod === 0 && $period === 'today') {
            $startDate = Carbon::now()->subDays(30)->startOfDay();
            $endDate = Carbon::now()->endOfDay();
        }

        // 1. Özet Göstergeleri (KPI Cards)
        $checksQuery = $applyCheckDateFilter(Check::query());

        $closedChecks = (clone $checksQuery)->where('status', 'closed')->get();
        $totalChecksCount = $closedChecks->count();
        $totalRevenue = $closedChecks->sum('total');
        $avgCheckAmount = $totalChecksCount > 0 ? ($totalRevenue / $totalChecksCount) : 0;
        $totalDiscounts = $closedChecks->sum('discount_total');

        // Ödeme Yöntemi Dağılımı (Kasa Özeti / Z-Raporu)
        $payments = Payment::where(function ($pQ) use ($startDate, $endDate, $applyCheckDateFilter) {
            $pQ->whereBetween('created_at', [$startDate, $endDate])
               ->orWhereHas('check', function ($q) use ($applyCheckDateFilter) {
                   $applyCheckDateFilter($q);
               });
        })->get();

        $paymentBreakdown = [
            'nakit' => $payments->where('payment_method', 'nakit')->sum('amount'),
            'kredi_karti' => $payments->where('payment_method', 'kredi_karti')->sum('amount'),
            'yemek_karti' => $payments->where('payment_method', 'yemek_karti')->sum('amount'),
            'total' => $payments->sum('amount'),
        ];

        // 2. İptal ve İkram İstatistikleri
        $cancelledItemsQuery = CheckItem::where(function ($q) {
            $q->where('is_cancelled', true)
              ->orWhere('kitchen_status', 'cancelled');
        })->whereBetween('created_at', [$startDate, $endDate]);

        $cancelledItemsCount = $cancelledItemsQuery->sum('quantity');
        $cancelledLossAmount = $cancelledItemsQuery->get()->sum(function ($item) {
            return $item->unit_price * $item->quantity;
        });

        $complimentaryItemsQuery = CheckItem::where('is_complimentary', true)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $complimentaryCount = $complimentaryItemsQuery->sum('quantity');
        $complimentaryTotalAmount = $complimentaryItemsQuery->get()->sum(function ($item) {
            return $item->unit_price * $item->quantity;
        });

        // 3. Saatlik Satış Yoğunluğu (00:00 - 23:00)
        $hourlyGrouped = $payments->groupBy(function ($payment) {
            return (int) Carbon::parse($payment->created_at)->format('H');
        });

        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $group = $hourlyGrouped->get($h, collect());
            $hourlyData[] = [
                'hour' => sprintf('%02d:00', $h),
                'amount' => (float) $group->sum('amount'),
                'count' => (int) $group->pluck('check_id')->unique()->count(),
            ];
        }

        // 4. Ürün Bazlı Satış Performansı
        $checkItemsPeriod = CheckItem::where(function ($q) use ($startDate, $endDate, $applyCheckDateFilter) {
            $q->whereBetween('created_at', [$startDate, $endDate])
              ->orWhereHas('check', function ($cQ) use ($applyCheckDateFilter) {
                  $applyCheckDateFilter($cQ);
              });
        })->get();

        $productStats = $checkItemsPeriod->groupBy('product_id')->map(function ($items) {
            $first = $items->first();
            $soldQty = $items->filter(fn($i) => !$i->is_cancelled && $i->kitchen_status !== 'cancelled')->sum('quantity');
            $totalRevenue = $items->filter(fn($i) => !$i->is_cancelled && $i->kitchen_status !== 'cancelled')->sum('total_price');
            $cancelledQty = $items->filter(fn($i) => $i->is_cancelled || $i->kitchen_status === 'cancelled')->sum('quantity');

            return (object) [
                'product_id' => $first->product_id,
                'product_name' => $first->product_name,
                'sold_qty' => $soldQty,
                'total_revenue' => $totalRevenue,
                'cancelled_qty' => $cancelledQty,
            ];
        })->sortByDesc('total_revenue')->values();

        // 5. Kategori Bazlı Ciro Dağılımı
        $categoryStatsMap = [];
        foreach ($productStats as $stat) {
            $prod = Product::with('category')->find($stat->product_id);
            $catName = $prod?->category?->name ?: 'Genel / Diğer';

            if (!isset($categoryStatsMap[$catName])) {
                $categoryStatsMap[$catName] = [
                    'category_name' => $catName,
                    'sold_qty' => 0,
                    'total_revenue' => 0,
                ];
            }
            $categoryStatsMap[$catName]['sold_qty'] += $stat->sold_qty;
            $categoryStatsMap[$catName]['total_revenue'] += $stat->total_revenue;
        }

        usort($categoryStatsMap, function ($a, $b) {
            return $b['total_revenue'] <=> $a['total_revenue'];
        });

        // 6. Personel / Garson Satış Performansı
        $waiterStats = $applyCheckDateFilter(Check::query())
            ->where('status', 'closed')
            ->select(
                'waiter_id',
                DB::raw("COUNT(id) as check_count"),
                DB::raw("SUM(total) as total_sales")
            )
            ->groupBy('waiter_id')
            ->with('waiter')
            ->orderByDesc('total_sales')
            ->get();

        // 7. İptal Siparişler Detay Tablosu
        $cancelledItemsList = CheckItem::where(function ($q) {
            $q->where('is_cancelled', true)
              ->orWhere('kitchen_status', 'cancelled');
        })
        ->whereBetween('created_at', [$startDate, $endDate])
        ->with(['check.diningTable', 'product'])
        ->latest()
        ->take(30)
        ->get();

        // 8. Tüm Adisyonlar & Sipariş Geçmişi (Saat, tutar, ödeme yöntemi, masa, garson ve içerik detayları)
        $checksHistory = $applyCheckDateFilter(Check::query())
            ->with(['diningTable.hall', 'waiter', 'items.product', 'payments'])
            ->latest('opened_at')
            ->paginate(25, ['*'], 'checks_page')
            ->withQueryString();

        $stats = [
            'total_revenue' => $totalRevenue,
            'total_checks_count' => $totalChecksCount,
            'avg_check_amount' => $avgCheckAmount,
            'total_discounts' => $totalDiscounts,
            'cancelled_items_count' => $cancelledItemsCount,
            'cancelled_loss_amount' => $cancelledLossAmount,
            'complimentary_count' => $complimentaryCount,
            'complimentary_total_amount' => $complimentaryTotalAmount,
        ];

        return view('reports.index', compact(
            'period',
            'startDate',
            'endDate',
            'stats',
            'paymentBreakdown',
            'hourlyData',
            'productStats',
            'categoryStatsMap',
            'waiterStats',
            'cancelledItemsList',
            'checksHistory'
        ));
    }
}
