@extends('layouts.app')

@section('title', 'Kasa Vardiyası & Kasa Sayımı')

@section('content')
<div class="flex min-h-screen flex-col bg-[#07090e] font-sans text-slate-100">
    <header class="flex h-16 shrink-0 items-center justify-between border-b border-slate-800/80 bg-[#0f131f]/95 px-4 backdrop-blur-md sm:px-8">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700/50 bg-slate-800/80 text-slate-300 transition hover:bg-slate-700 hover:text-white">
                <i class="fi fi-rr-arrow-left text-lg"></i>
            </a>
            <div>
                <h1 class="flex items-center gap-2 text-base font-extrabold tracking-tight text-white sm:text-lg">
                    <i class="fi fi-rr-cash-register text-teal-400"></i>
                    Kasa Vardiyası & Sayım
                </h1>
                <p class="hidden text-[11px] text-slate-400 sm:block">Açılış bakiyesi, nakit hareketleri ve vardiya kapanış farkı</p>
            </div>
        </div>
        @if($currentShift)
            <div class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 text-xs font-black text-emerald-300">
                <span class="mr-1 inline-block h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span>
                VARDİYA AÇIK
            </div>
        @else
            <div class="rounded-full border border-slate-700 bg-slate-800/70 px-3 py-1.5 text-xs font-black text-slate-400">KASA KAPALI</div>
        @endif
    </header>

    <main class="flex-1 space-y-6 p-4 sm:p-8">
        @if(false && $errors->any())
            <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm font-semibold text-rose-300">
                <div class="flex items-start gap-2">
                    <i class="fi fi-rr-exclamation mt-0.5"></i>
                    <div>
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if(!$currentShift)
            <section class="mx-auto max-w-2xl overflow-hidden rounded-3xl border border-slate-800 bg-[#111524] shadow-2xl">
                <div class="border-b border-slate-800 bg-gradient-to-r from-teal-500/10 to-transparent p-6">
                    <h2 class="text-xl font-black text-white">Yeni Kasa Vardiyası Aç</h2>
                    <p class="mt-1 text-sm text-slate-400">Kasadaki başlangıç nakdini girerek vardiyayı başlatın.</p>
                </div>
                <form method="POST" action="{{ route('cash-shifts.store') }}" class="space-y-5 p-6">
                    @csrf
                    <div>
                        <label for="opening_cash" class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-400">Açılış Bakiyesi</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-black text-teal-400">₺</span>
                            <input id="opening_cash" name="opening_cash" type="number" min="0" max="999999999.99" step="0.01" required
                                value="{{ old('opening_cash', '0.00') }}"
                                class="w-full rounded-2xl border border-slate-700 bg-[#0c101b] py-4 pl-10 pr-4 text-2xl font-black text-white outline-none transition focus:border-teal-500">
                        </div>
                    </div>
                    <div>
                        <label for="opening_note" class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-400">Açılış Notu <span class="font-normal text-slate-600">(isteğe bağlı)</span></label>
                        <textarea id="opening_note" name="opening_note" rows="3" maxlength="1000"
                            placeholder="Devir teslim veya açılış hakkında not..."
                            class="w-full resize-none rounded-2xl border border-slate-700 bg-[#0c101b] px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-teal-500">{{ old('opening_note') }}</textarea>
                    </div>
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-teal-600 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-teal-600/20 transition hover:bg-teal-500">
                        <i class="fi fi-rr-play"></i>Kasa Vardiyasını Aç
                    </button>
                </form>
            </section>
        @else
            <section class="rounded-3xl border border-slate-800 bg-[#111524] p-5 shadow-xl">
                <div class="flex flex-col gap-3 border-b border-slate-800 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-mono text-xs font-bold text-teal-400">{{ $currentShift->shift_number }}</div>
                        <h2 class="mt-1 text-lg font-black text-white">Aktif Kasa Vardiyası</h2>
                    </div>
                    <div class="text-left text-xs text-slate-400 sm:text-right">
                        <div><span class="text-slate-500">Açan:</span> <strong class="text-slate-200">{{ $currentShift->opened_by_name }}</strong></div>
                        <div class="mt-1 font-mono">{{ $currentShift->opened_at?->format('d.m.Y H:i:s') }}</div>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <div class="rounded-2xl border border-slate-800 bg-[#0c101b] p-4">
                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-500">Açılış</div>
                        <div class="mt-2 text-xl font-black text-white">₺{{ number_format((float) $currentShift->opening_cash, 2, ',', '.') }}</div>
                    </div>
                    <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4">
                        <div class="text-[10px] font-black uppercase tracking-wider text-emerald-500">Nakit Satış</div>
                        <div class="mt-2 text-xl font-black text-emerald-400">₺{{ number_format($summary['cash_sales'], 2, ',', '.') }}</div>
                    </div>
                    <div class="rounded-2xl border border-sky-500/20 bg-sky-500/5 p-4">
                        <div class="text-[10px] font-black uppercase tracking-wider text-sky-500">Nakit Giriş</div>
                        <div class="mt-2 text-xl font-black text-sky-400">+₺{{ number_format($summary['cash_in_total'], 2, ',', '.') }}</div>
                    </div>
                    <div class="rounded-2xl border border-rose-500/20 bg-rose-500/5 p-4">
                        <div class="text-[10px] font-black uppercase tracking-wider text-rose-500">Nakit Çıkış</div>
                        <div class="mt-2 text-xl font-black text-rose-400">-₺{{ number_format($summary['cash_out_total'], 2, ',', '.') }}</div>
                    </div>
                    <div class="col-span-2 rounded-2xl border border-teal-500/30 bg-teal-500/10 p-4 lg:col-span-1">
                        <div class="text-[10px] font-black uppercase tracking-wider text-teal-400">Beklenen Kasa</div>
                        <div id="expectedCash" data-value="{{ $summary['expected_cash'] }}" class="mt-2 text-2xl font-black text-teal-300">₺{{ number_format($summary['expected_cash'], 2, ',', '.') }}</div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    @foreach($paymentMethods as $methodId => $method)
                        @continue($methodId === 'nakit')
                        <div class="rounded-xl border border-slate-800 bg-slate-900/50 px-4 py-3 text-xs">
                            <span class="text-slate-500">{{ $method['label'] }}:</span>
                            <strong class="float-right text-slate-200">₺{{ number_format($summary['payment_totals'][$methodId] ?? 0, 2, ',', '.') }}</strong>
                        </div>
                    @endforeach
                    <div class="rounded-xl border border-slate-800 bg-slate-900/50 px-4 py-3 text-xs">
                        <span class="text-slate-500">Toplam Tahsilat:</span>
                        <strong class="float-right text-white">₺{{ number_format(array_sum($summary['payment_totals']), 2, ',', '.') }}</strong>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <section class="rounded-3xl border border-slate-800 bg-[#111524] p-5 shadow-xl">
                    <h3 class="flex items-center gap-2 text-base font-black text-white">
                        <i class="fi fi-rr-exchange text-sky-400"></i>Nakit Giriş / Çıkış
                    </h3>
                    <p class="mt-1 text-xs text-slate-500">Satış dışındaki kasa hareketleri için açıklama zorunludur.</p>

                    <form method="POST" action="{{ route('cash-shifts.movements.store', $currentShift) }}" class="mt-5 space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="cash_in" class="peer sr-only" @checked(old('type', 'cash_in') === 'cash_in')>
                                <span class="flex items-center justify-center gap-2 rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-3 text-sm font-bold text-slate-400 transition peer-checked:border-sky-500 peer-checked:bg-sky-500/10 peer-checked:text-sky-300">
                                    <i class="fi fi-rr-arrow-down"></i>Para Girişi
                                </span>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="cash_out" class="peer sr-only" @checked(old('type') === 'cash_out')>
                                <span class="flex items-center justify-center gap-2 rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-3 text-sm font-bold text-slate-400 transition peer-checked:border-rose-500 peer-checked:bg-rose-500/10 peer-checked:text-rose-300">
                                    <i class="fi fi-rr-arrow-up"></i>Para Çıkışı
                                </span>
                            </label>
                        </div>
                        <input name="amount" type="number" min="0.01" max="999999999.99" step="0.01" required value="{{ old('amount') }}" placeholder="Tutar (₺)"
                            class="w-full rounded-xl border border-slate-700 bg-[#0c101b] px-4 py-3 text-sm font-bold text-white outline-none focus:border-sky-500">
                        <input name="reason" type="text" maxlength="500" required value="{{ old('reason') }}" placeholder="Açıklama: tedarikçi ödemesi, bozuk para girişi..."
                            class="w-full rounded-xl border border-slate-700 bg-[#0c101b] px-4 py-3 text-sm text-white outline-none placeholder:text-slate-600 focus:border-sky-500">
                        <button type="submit" class="w-full rounded-xl bg-sky-600 px-4 py-3 text-sm font-black text-white transition hover:bg-sky-500">
                            Hareketi Kaydet
                        </button>
                    </form>
                </section>

                <section class="rounded-3xl border border-slate-800 bg-[#111524] p-5 shadow-xl">
                    <h3 class="flex items-center gap-2 text-base font-black text-white">
                        <i class="fi fi-rr-calculator text-amber-400"></i>Kasa Sayımı & Vardiya Kapatma
                    </h3>
                    <p class="mt-1 text-xs text-slate-500">Her kupürün kasadaki adetini girin; toplam sunucuda tekrar hesaplanır.</p>

                    <form method="POST" action="{{ route('cash-shifts.close', $currentShift) }}" class="mt-5 space-y-4" id="closeShiftForm">
                        @csrf
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach($denominations as $valueInKurus => $label)
                                <label class="rounded-xl border border-slate-800 bg-[#0c101b] p-3">
                                    <span class="mb-2 block text-xs font-black text-slate-300">{{ $label }}</span>
                                    <input name="denominations[{{ $valueInKurus }}]" type="number" min="0" max="100000" step="1"
                                        value="{{ old("denominations.{$valueInKurus}", 0) }}"
                                        data-denomination="{{ $valueInKurus }}"
                                        class="denomination-input w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-right font-mono text-sm font-bold text-white outline-none focus:border-amber-500">
                                </label>
                            @endforeach
                            <label class="rounded-xl border border-slate-800 bg-[#0c101b] p-3">
                                <span class="mb-2 block text-xs font-black text-slate-300">Diğer Tutar</span>
                                <input id="otherAmount" name="other_amount" type="number" min="0" max="999999999.99" step="0.01"
                                    value="{{ old('other_amount', 0) }}"
                                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-right font-mono text-sm font-bold text-white outline-none focus:border-amber-500">
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-3">
                                <div class="text-[10px] font-black uppercase text-amber-500">Sayılan Toplam</div>
                                <div id="countedCash" class="mt-1 text-xl font-black text-amber-300">₺0,00</div>
                            </div>
                            <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-3">
                                <div class="text-[10px] font-black uppercase text-slate-500">Tahmini Fark</div>
                                <div id="cashDifference" class="mt-1 text-xl font-black text-slate-300">₺0,00</div>
                            </div>
                        </div>

                        <textarea name="closing_note" rows="2" maxlength="1000"
                            placeholder="Kapanış notu (sayım farkı varsa zorunlu)..."
                            class="w-full resize-none rounded-xl border border-slate-700 bg-[#0c101b] px-4 py-3 text-sm text-white outline-none placeholder:text-slate-600 focus:border-amber-500">{{ old('closing_note') }}</textarea>

                        <button type="submit" onclick="return confirm('Kasa vardiyası kapatılacak. Bu işlem geri alınamaz. Devam edilsin mi?')"
                            class="w-full rounded-xl bg-amber-600 px-4 py-3 text-sm font-black text-white transition hover:bg-amber-500">
                            Sayımı Kaydet ve Vardiyayı Kapat
                        </button>
                    </form>
                </section>
            </div>

            <section class="overflow-hidden rounded-3xl border border-slate-800 bg-[#111524] shadow-xl">
                <div class="border-b border-slate-800 p-5">
                    <h3 class="text-base font-black text-white">Bu Vardiyanın Nakit Hareketleri</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-[#0c101b] text-[10px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="p-4">Saat</th>
                                <th class="p-4">İşlem</th>
                                <th class="p-4">Tutar</th>
                                <th class="p-4">Açıklama</th>
                                <th class="p-4">Personel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse($currentShift->movements as $movement)
                                <tr class="text-slate-300">
                                    <td class="p-4 font-mono text-xs text-slate-500">{{ $movement->occurred_at?->format('d.m.Y H:i:s') }}</td>
                                    <td class="p-4">
                                        <span class="rounded-full border px-2.5 py-1 text-xs font-black {{ $movement->type === 'cash_in' ? 'border-sky-500/30 bg-sky-500/10 text-sky-300' : 'border-rose-500/30 bg-rose-500/10 text-rose-300' }}">
                                            {{ $movement->type === 'cash_in' ? 'PARA GİRİŞİ' : 'PARA ÇIKIŞI' }}
                                        </span>
                                    </td>
                                    <td class="p-4 font-mono font-black {{ $movement->type === 'cash_in' ? 'text-sky-300' : 'text-rose-300' }}">
                                        {{ $movement->type === 'cash_in' ? '+' : '-' }}₺{{ number_format((float) $movement->amount, 2, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-slate-300">{{ $movement->reason }}</td>
                                    <td class="p-4 font-semibold text-white">{{ $movement->created_by_name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-8 text-center text-sm text-slate-500">Henüz manuel nakit hareketi yok.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-3xl border border-slate-800 bg-[#111524] shadow-xl">
            <div class="border-b border-slate-800 p-5">
                <h3 class="text-base font-black text-white">Geçmiş Kasa Vardiyaları</h3>
                <p class="mt-1 text-xs text-slate-500">Kapanmış vardiyaların beklenen, sayılan ve fark tutarları.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-left text-sm">
                    <thead class="bg-[#0c101b] text-[10px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="p-4">Vardiya</th>
                            <th class="p-4">Açılış / Kapanış</th>
                            <th class="p-4">Personel</th>
                            <th class="p-4">Açılış</th>
                            <th class="p-4">Nakit Satış</th>
                            <th class="p-4">Beklenen</th>
                            <th class="p-4">Sayılan</th>
                            <th class="p-4">Fark</th>
                            <th class="p-4">Not</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($history as $shift)
                            <tr class="align-top text-slate-300">
                                <td class="p-4 font-mono text-xs font-bold text-teal-400">{{ $shift->shift_number }}</td>
                                <td class="whitespace-nowrap p-4 font-mono text-xs text-slate-500">
                                    <div>{{ $shift->opened_at?->format('d.m.Y H:i') }}</div>
                                    <div class="mt-1">{{ $shift->closed_at?->format('d.m.Y H:i') }}</div>
                                </td>
                                <td class="p-4 text-xs">
                                    <div><span class="text-slate-500">Açan:</span> {{ $shift->opened_by_name }}</div>
                                    <div class="mt-1"><span class="text-slate-500">Kapatan:</span> {{ $shift->closed_by_name }}</div>
                                </td>
                                <td class="p-4 font-mono">₺{{ number_format((float) $shift->opening_cash, 2, ',', '.') }}</td>
                                <td class="p-4 font-mono text-emerald-400">₺{{ number_format((float) $shift->cash_sales, 2, ',', '.') }}</td>
                                <td class="p-4 font-mono font-bold text-white">₺{{ number_format((float) $shift->expected_cash, 2, ',', '.') }}</td>
                                <td class="p-4 font-mono font-bold text-amber-300">₺{{ number_format((float) $shift->counted_cash, 2, ',', '.') }}</td>
                                <td class="p-4">
                                    @php($difference = (float) $shift->difference)
                                    <span class="rounded-full border px-2.5 py-1 font-mono text-xs font-black {{ abs($difference) < 0.01 ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : ($difference > 0 ? 'border-sky-500/30 bg-sky-500/10 text-sky-300' : 'border-rose-500/30 bg-rose-500/10 text-rose-300') }}">
                                        {{ $difference > 0 ? '+' : '' }}₺{{ number_format($difference, 2, ',', '.') }}
                                    </span>
                                </td>
                                <td class="max-w-xs p-4 text-xs text-slate-400">{{ $shift->closing_note ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="p-8 text-center text-sm text-slate-500">Henüz kapanmış kasa vardiyası yok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($history->hasPages())
                <div class="border-t border-slate-800 p-4">{{ $history->links() }}</div>
            @endif
        </section>
    </main>
</div>
@endsection

@section('scripts')
@if($currentShift)
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const denominationInputs = document.querySelectorAll('.denomination-input');
        const otherAmount = document.getElementById('otherAmount');
        const countedCash = document.getElementById('countedCash');
        const cashDifference = document.getElementById('cashDifference');
        const expected = Number(document.getElementById('expectedCash')?.dataset.value || 0);
        const currency = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' });

        const calculate = () => {
            let total = Math.max(0, Number(otherAmount?.value || 0));

            denominationInputs.forEach(input => {
                const denomination = Number(input.dataset.denomination || 0) / 100;
                const quantity = Math.max(0, Number(input.value || 0));
                total += denomination * quantity;
            });

            const difference = Math.round((total - expected) * 100) / 100;
            countedCash.textContent = currency.format(total);
            cashDifference.textContent = `${difference > 0 ? '+' : ''}${currency.format(difference)}`;
            cashDifference.className = `mt-1 text-xl font-black ${Math.abs(difference) < 0.01 ? 'text-emerald-300' : (difference > 0 ? 'text-sky-300' : 'text-rose-300')}`;
        };

        denominationInputs.forEach(input => input.addEventListener('input', calculate));
        otherAmount?.addEventListener('input', calculate);
        calculate();
    });
</script>
@endif
@endsection
