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

        // 1. Özet Göstergeleri (KPI Cards)
        $checksQuery = Check::whereBetween('opened_at', [$startDate, $endDate]);

        $closedChecks = (clone $checksQuery)->where('status', 'closed')->get();
        $totalChecksCount = $closedChecks->count();
        $totalRevenue = $closedChecks->sum('total');
        $avgCheckAmount = $totalChecksCount > 0 ? ($totalRevenue / $totalChecksCount) : 0;
        $totalDiscounts = $closedChecks->sum('discount_total');

        // Ödeme Yöntemi Dağılımı (Kasa Özeti / Z-Raporu)
        $payments = Payment::whereHas('check', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('opened_at', [$startDate, $endDate]);
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
        $hourlySalesRaw = Payment::whereHas('check', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('opened_at', [$startDate, $endDate]);
        })
        ->select(
            DB::raw("EXTRACT(HOUR FROM created_at) as hour"),
            DB::raw("SUM(amount) as total_amount"),
            DB::raw("COUNT(DISTINCT check_id) as check_count")
        )
        ->groupBy(DB::raw("EXTRACT(HOUR FROM created_at)"))
        ->get()
        ->keyBy('hour');

        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $record = $hourlySalesRaw->get($h);
            $hourlyData[] = [
                'hour' => sprintf('%02d:00', $h),
                'amount' => $record ? (float) $record->total_amount : 0,
                'count' => $record ? (int) $record->check_count : 0,
            ];
        }

        // 4. Ürün Bazlı Satış Performansı
        $productStats = CheckItem::select(
            'product_id',
            'product_name',
            DB::raw("SUM(CASE WHEN is_cancelled = false AND (kitchen_status IS NULL OR kitchen_status != 'cancelled') THEN quantity ELSE 0 END) as sold_qty"),
            DB::raw("SUM(CASE WHEN is_cancelled = false AND (kitchen_status IS NULL OR kitchen_status != 'cancelled') THEN total_price ELSE 0 END) as total_revenue"),
            DB::raw("SUM(CASE WHEN is_cancelled = true OR kitchen_status = 'cancelled' THEN quantity ELSE 0 END) as cancelled_qty")
        )
        ->whereBetween('created_at', [$startDate, $endDate])
        ->groupBy('product_id', 'product_name')
        ->orderByDesc('total_revenue')
        ->get();

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
        $waiterStats = Check::whereBetween('opened_at', [$startDate, $endDate])
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
        $checksHistory = Check::whereBetween('opened_at', [$startDate, $endDate])
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
