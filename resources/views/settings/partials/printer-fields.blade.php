@php
    /** @var \App\Models\Printer|null $printer */
    $uid = $printer?->id ?? 'new';

    // old() yalnızca "yeni yazıcı" formuna uygulanır. Aksi halde bir doğrulama
    // hatasından sonra sayfadaki TÜM düzenleme formları da yeni kayıt girdisiyle dolardı.
    $val = fn (string $key, $default = null) => $printer
        ? ($printer->{$key} ?? $default)
        : old($key, $default);

    $connection = $val('connection_type', 'windows_driver');
    $paperWidth = (int) $val('paper_width', 80);
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">

    <div class="lg:col-span-1">
        <label class="block font-bold text-slate-300 mb-1.5">Yazıcı Adı</label>
        <input type="text" name="name" required maxlength="100"
               value="{{ $val('name', '') }}"
               placeholder="Mutfak Yazıcısı"
               class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-sky-500 focus:outline-none transition">
    </div>

    <div>
        <label class="block font-bold text-slate-300 mb-1.5">Kullanım Yeri</label>
        <select name="type" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-sky-500 focus:outline-none transition">
            @foreach(['kitchen' => 'Mutfak (Sipariş Fişi)', 'cashier' => 'Kasa (Hesap Fişi)', 'bar' => 'Bar'] as $value => $label)
                <option value="{{ $value }}" @selected($val('type', 'cashier') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block font-bold text-slate-300 mb-1.5">Bağlantı Tipi</label>
        <select name="connection_type" data-connection-select="{{ $uid }}"
                onchange="onConnectionChange('{{ $uid }}')"
                class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-sky-500 focus:outline-none transition">
            @foreach([
                'windows_driver' => 'Windows Sürücüsü (Önerilen)',
                'network_tcp' => 'Ağ / TCP (IP:Port)',
                'serial_com' => 'Seri Port (COM)',
                'usb' => 'USB',
            ] as $value => $label)
                <option value="{{ $value }}" @selected($connection === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="sm:col-span-2">
        <label class="block font-bold text-slate-300 mb-1.5">
            Hedef <span data-target-hint="{{ $uid }}" class="font-normal text-slate-500"></span>
        </label>
        <input type="text" name="printer_target" maxlength="255"
               data-target-input="{{ $uid }}"
               value="{{ $val('printer_target', '') }}"
               class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white font-mono focus:border-sky-500 focus:outline-none transition">
        <p class="text-[10px] text-slate-500 mt-1.5" data-target-help="{{ $uid }}"></p>
    </div>

    <div>
        <label class="block font-bold text-slate-300 mb-1.5">Kağıt Genişliği</label>
        <select name="paper_width" data-paper-select="{{ $uid }}" onchange="onPaperChange('{{ $uid }}')"
                class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-sky-500 focus:outline-none transition">
            <option value="80" @selected($paperWidth === 80)>80 mm (48 karakter)</option>
            <option value="58" @selected($paperWidth === 58)>58 mm (32 karakter)</option>
        </select>
    </div>

    <div>
        <label class="block font-bold text-slate-300 mb-1.5">Satır Genişliği (karakter)</label>
        <input type="number" name="char_width" min="24" max="96"
               data-char-input="{{ $uid }}"
               value="{{ $val('char_width', '') }}"
               placeholder="Otomatik"
               class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-sky-500 focus:outline-none transition">
        <p class="text-[10px] text-slate-500 mt-1.5">Boş bırakılırsa kağıt genişliğinden hesaplanır. Fiş dar/taşkın basıyorsa buradan ayarlayın.</p>
    </div>

    <div class="flex items-end gap-3">
        <label class="flex-1 flex items-center justify-between p-3 rounded-xl bg-slate-900 border border-slate-800 cursor-pointer">
            <span class="font-bold text-slate-300">Aktif</span>
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked((bool) $val('is_active', true)) class="w-4 h-4 accent-sky-500">
        </label>

        <label class="flex-1 flex items-center justify-between p-3 rounded-xl bg-slate-900 border border-slate-800 cursor-pointer">
            <span class="font-bold text-slate-300">Varsayılan</span>
            <input type="hidden" name="is_default" value="0">
            <input type="checkbox" name="is_default" value="1" @checked((bool) $val('is_default', false)) class="w-4 h-4 accent-indigo-500">
        </label>
    </div>
</div>

@error('printer_target')
    <p class="text-[11px] text-rose-400 font-semibold mt-2">{{ $message }}</p>
@enderror
