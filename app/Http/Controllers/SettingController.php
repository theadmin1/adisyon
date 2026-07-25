<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $settings = Setting::getAllAsArray();

        // Varsayılan Ayarlar (Veritabanında henüz kayıt yoksa varsayılanlar yüklenir)
        $defaults = [
            // Genel Restoran
            'restaurant_name' => 'AltF4 Adisyon & Restoran',
            'restaurant_phone' => '0(850) 123 45 67',
            'restaurant_email' => 'info@altf4software.com',
            'restaurant_address' => 'Merkez Mah. Atatürk Cad. No:10, İstanbul',
            'currency_symbol' => '₺',
            'default_vat_rate' => '10',
            
            // POS & Adisyon
            'auto_close_table' => '1',
            'require_staff_pin' => '1',
            'max_discount_percent' => '20',
            'allow_item_void' => '1',
            
            // Fiş & Yazıcı
            'receipt_title' => 'AltF4 RESTORAN & KAFE',
            'receipt_footer' => 'Bizi Tercih Ettiğiniz İçin Teşekkür Ederiz. Yine Bekleriz!',
            'auto_print_kitchen' => '1',
            'receipt_copies' => '1',
            // Termal yazıcı ₺ (U+20BA) karakterini basamaz; fişte bunun yerine bu metin kullanılır.
            'receipt_currency_text' => 'TL',
            
            // Ödeme
            'enable_cash' => '1',
            'enable_card' => '1',
            'enable_sodexo' => '1',
            'enable_multinet' => '1',
            'enable_ticket' => '1',
            
            // Mutfak & Bildirim Sesleri
            'kitchen_refresh_sec' => '10',
            'kitchen_warning_min' => '15',
            'kitchen_sound_alert' => '1',
            'delivery_sound_alert' => '1',
            'table_sound_alert' => '1',
            'notification_sound_theme' => 'chime',
            'notification_sound_volume' => '80',
            'notification_sound_repeat' => '1',

            // Masa Ayarları
            'enable_table_transfer' => '1',
            'enable_table_merge' => '1',
            'default_table_capacity' => '4',
            'table_idle_warning_min' => '45',
            'table_grid_columns' => '4',
        ];

        // Veritabanındaki değerlerle birleştir
        $merged = array_merge($defaults, $settings);

        // Termal Yazıcı Tanımları & Son Yazdırma Kuyruğu
        $printers = Printer::orderBy('type')->orderBy('name')->get();

        $printJobs = PrintJob::with('printer')
            ->latest('id')
            ->limit(15)
            ->get();

        // Salonlar ve Masalar
        $halls = \App\Models\Hall::withCount('tables')->orderBy('sort_order')->get();
        $tables = \App\Models\DiningTable::with('hall')->orderBy('hall_id')->orderBy('name')->get();

        // Online Paket Servis Entegrasyonları (Trendyol Go, Yemeksepeti, GetirYemek, Migros Yemek)
        $defaultChannels = [
            'trendyol' => ['name' => 'Trendyol Go', 'color' => 'orange'],
            'yemeksepeti' => ['name' => 'Yemeksepeti', 'color' => 'pink'],
            'getir' => ['name' => 'GetirYemek', 'color' => 'purple'],
            'migros' => ['name' => 'Migros Yemek', 'color' => 'amber'],
        ];

        try {
            $integrations = \App\Models\DeliveryIntegration::all()->keyBy('channel');
            foreach ($defaultChannels as $key => $meta) {
                if (!$integrations->has($key)) {
                    $integrations[$key] = \App\Models\DeliveryIntegration::create([
                        'channel' => $key,
                        'store_name' => $meta['name'] . ' Restoran',
                        'store_id' => strtoupper($key) . '-8842',
                        'api_key' => '',
                        'is_active' => true,
                        'auto_accept' => false,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $integrations = collect();
        }

        return view('settings.index', compact('merged', 'printers', 'printJobs', 'halls', 'tables', 'integrations', 'defaultChannels'));
    }

    public function update(Request $request): RedirectResponse
    {
        $group = $request->input('group', 'general');

        if ($group === 'integrations') {
            $integData = $request->input('integrations', []);
            foreach (['trendyol', 'yemeksepeti', 'getir', 'migros'] as $ch) {
                $item = $integData[$ch] ?? [];
                \App\Models\DeliveryIntegration::updateOrCreate(
                    ['channel' => $ch],
                    [
                        'store_name' => $item['store_name'] ?? ($ch . ' Restoran'),
                        'store_id' => $item['store_id'] ?? null,
                        'api_key' => $item['api_key'] ?? null,
                        'is_active' => isset($item['is_active']) && $item['is_active'] == '1',
                        'auto_accept' => isset($item['auto_accept']) && $item['auto_accept'] == '1',
                    ]
                );
            }

            return redirect()->route('settings.index', ['tab' => 'integrations'])
                ->with('success', 'Online paket servis entegrasyon ayarları kaydedildi!');
        }

        $inputs = $request->except(['_token', 'group']);

        foreach ($inputs as $key => $value) {
            Setting::set($key, $value, $group);
        }

        return redirect()->route('settings.index', ['tab' => $group])
            ->with('success', 'Ayarlar başarıyla kaydedildi!');
    }
}
