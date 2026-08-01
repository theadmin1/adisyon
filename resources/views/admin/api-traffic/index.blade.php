@extends('admin.layout')

@section('title', 'Canlı API Trafiği - Central Admin')

@section('content')
<div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
    <div>
        <div class="flex items-center gap-3">
            <h2 class="text-2xl font-bold text-white">Canlı API Trafiği</h2>
            <span class="inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-950/60 px-3 py-1 text-xs font-bold text-emerald-300">
                <span id="liveDot" class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span>
                <span id="liveText">CANLI</span>
            </span>
        </div>
        <p class="mt-1 text-sm text-gray-400">Flutter garson API istek ve cevapları, hassas alanlar maskelenerek gösterilir.</p>
    </div>
    <div class="flex items-center gap-3">
        <span class="text-xs text-gray-500">Kayıt saklama: {{ $retentionDays }} gün</span>
        <button id="pauseButton" type="button" class="rounded-lg border border-gray-700 bg-gray-800 px-4 py-2 text-xs font-bold text-gray-200 hover:bg-gray-700">Akışı Duraklat</button>
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-xl border border-gray-800 bg-[#181a24] p-4">
        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Son 24 Saat</div>
        <div id="statRequests" class="mt-2 text-3xl font-black text-white">{{ number_format($stats['requests_24h']) }}</div>
        <div class="mt-1 text-xs text-gray-500">toplam API isteği</div>
    </div>
    <div class="rounded-xl border border-gray-800 bg-[#181a24] p-4">
        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Hatalar</div>
        <div id="statErrors" class="mt-2 text-3xl font-black text-rose-400">{{ number_format($stats['errors_24h']) }}</div>
        <div class="mt-1 text-xs text-gray-500">4xx ve 5xx cevaplar</div>
    </div>
    <div class="rounded-xl border border-gray-800 bg-[#181a24] p-4">
        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Ortalama Süre</div>
        <div class="mt-2 text-3xl font-black text-sky-300"><span id="statAverage">{{ number_format($stats['average_ms_24h']) }}</span><span class="ml-1 text-sm">ms</span></div>
        <div class="mt-1 text-xs text-gray-500">son 24 saat</div>
    </div>
    <div class="rounded-xl border border-gray-800 bg-[#181a24] p-4">
        <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Anlık Yoğunluk</div>
        <div class="mt-2 text-3xl font-black text-emerald-300"><span id="statMinute">{{ number_format($stats['requests_last_minute']) }}</span><span class="ml-1 text-sm">/dk</span></div>
        <div id="lastUpdate" class="mt-1 text-xs text-gray-500">Sunucuya bağlı</div>
    </div>
</div>

<form method="GET" action="{{ route('admin.api-traffic.index') }}" class="grid gap-3 rounded-xl border border-gray-800 bg-[#181a24] p-4 md:grid-cols-2 xl:grid-cols-5">
    <div>
        <label for="branch_id" class="mb-1 block text-xs font-bold uppercase text-gray-500">Şube</label>
        <select id="branch_id" name="branch_id" class="w-full rounded-lg border border-gray-700 bg-[#0f1117] px-3 py-2 text-sm text-gray-200">
            <option value="">Tüm şubeler</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? null) == $branch->id)>{{ $branch->name }} ({{ $branch->code }})</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="method" class="mb-1 block text-xs font-bold uppercase text-gray-500">Metot</label>
        <select id="method" name="method" class="w-full rounded-lg border border-gray-700 bg-[#0f1117] px-3 py-2 text-sm text-gray-200">
            <option value="">Tümü</option>
            @foreach(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                <option value="{{ $method }}" @selected(($filters['method'] ?? null) === $method)>{{ $method }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="status" class="mb-1 block text-xs font-bold uppercase text-gray-500">Cevap</label>
        <select id="status" name="status" class="w-full rounded-lg border border-gray-700 bg-[#0f1117] px-3 py-2 text-sm text-gray-200">
            <option value="">Tüm durumlar</option>
            <option value="2xx" @selected(($filters['status'] ?? null) === '2xx')>2xx Başarılı</option>
            <option value="3xx" @selected(($filters['status'] ?? null) === '3xx')>3xx Yönlendirme</option>
            <option value="4xx" @selected(($filters['status'] ?? null) === '4xx')>4xx İstemci Hatası</option>
            <option value="5xx" @selected(($filters['status'] ?? null) === '5xx')>5xx Sunucu Hatası</option>
        </select>
    </div>
    <div>
        <label for="search" class="mb-1 block text-xs font-bold uppercase text-gray-500">Arama</label>
        <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="Endpoint, personel, request ID..." class="w-full rounded-lg border border-gray-700 bg-[#0f1117] px-3 py-2 text-sm text-gray-200 placeholder:text-gray-600">
    </div>
    <div class="flex items-end gap-2">
        <button class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-500">Filtrele</button>
        <a href="{{ route('admin.api-traffic.index') }}" class="rounded-lg border border-gray-700 px-4 py-2 text-sm text-gray-400 hover:bg-gray-800 hover:text-white">Sıfırla</a>
    </div>
</form>

<div class="overflow-hidden rounded-xl border border-gray-800 bg-[#181a24] shadow-2xl">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[1180px] text-left text-sm">
            <thead class="border-b border-gray-800 bg-[#141620] text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="p-4">Zaman</th>
                    <th class="p-4">Durum</th>
                    <th class="p-4">Endpoint</th>
                    <th class="p-4">Şube / Personel</th>
                    <th class="p-4">Süre</th>
                    <th class="p-4">Boyut</th>
                    <th class="p-4 text-right">Detay</th>
                </tr>
            </thead>
            <tbody id="trafficRows" class="divide-y divide-gray-800"></tbody>
        </table>
    </div>
</div>

<div id="detailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
    <div class="max-h-[92vh] w-full max-w-6xl overflow-hidden rounded-2xl border border-gray-700 bg-[#141620] shadow-2xl">
        <div class="flex items-start justify-between border-b border-gray-800 p-5">
            <div>
                <div class="flex items-center gap-3">
                    <span id="detailMethod"></span>
                    <span id="detailStatus"></span>
                    <span id="detailDuration" class="text-xs text-gray-500"></span>
                </div>
                <div id="detailPath" class="mt-2 break-all font-mono text-sm text-gray-200"></div>
                <div id="detailMeta" class="mt-1 text-xs text-gray-500"></div>
            </div>
            <button id="closeModal" type="button" class="rounded-lg border border-gray-700 px-3 py-2 text-gray-400 hover:bg-gray-800 hover:text-white">✕</button>
        </div>
        <div class="grid max-h-[72vh] gap-4 overflow-y-auto p-5 xl:grid-cols-2">
            <section class="space-y-3">
                <h3 class="text-sm font-bold text-sky-300">İstek</h3>
                <div>
                    <div class="mb-1 text-[10px] font-bold uppercase text-gray-500">Headers</div>
                    <pre id="detailHeaders" class="max-h-52 overflow-auto whitespace-pre-wrap rounded-lg border border-gray-800 bg-black/30 p-3 text-xs leading-relaxed text-gray-400"></pre>
                </div>
                <div>
                    <div class="mb-1 text-[10px] font-bold uppercase text-gray-500">Payload</div>
                    <pre id="detailRequest" class="max-h-96 overflow-auto whitespace-pre-wrap rounded-lg border border-sky-900/40 bg-black/30 p-3 text-xs leading-relaxed text-sky-100"></pre>
                </div>
            </section>
            <section class="space-y-3">
                <h3 class="text-sm font-bold text-emerald-300">Cevap</h3>
                <pre id="detailResponse" class="max-h-[34rem] overflow-auto whitespace-pre-wrap rounded-lg border border-emerald-900/40 bg-black/30 p-3 text-xs leading-relaxed text-emerald-100"></pre>
            </section>
        </div>
    </div>
</div>

<script>
(() => {
    const streamUrl = @json(route('admin.api-traffic.stream'));
    const filters = @json($filters);
    const pollInterval = @json($pollInterval);
    let traffic = @json($initialLogs);
    let paused = false;
    let polling = false;

    const rows = document.getElementById('trafficRows');
    const modal = document.getElementById('detailModal');
    const pauseButton = document.getElementById('pauseButton');
    const methodClasses = {
        GET: 'border-sky-500/40 bg-sky-950/60 text-sky-300',
        POST: 'border-emerald-500/40 bg-emerald-950/60 text-emerald-300',
        PUT: 'border-amber-500/40 bg-amber-950/60 text-amber-300',
        PATCH: 'border-fuchsia-500/40 bg-fuchsia-950/60 text-fuchsia-300',
        DELETE: 'border-rose-500/40 bg-rose-950/60 text-rose-300'
    };

    const methodClass = method => methodClasses[method] || 'border-gray-600 bg-gray-900 text-gray-300';
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[character]);
    const statusClass = status => status >= 500
        ? 'text-rose-300 bg-rose-950/60 border-rose-500/30'
        : status >= 400
            ? 'text-amber-300 bg-amber-950/60 border-amber-500/30'
            : status >= 300
                ? 'text-violet-300 bg-violet-950/60 border-violet-500/30'
                : 'text-emerald-300 bg-emerald-950/60 border-emerald-500/30';
    const byteLabel = bytes => Number(bytes || 0) < 1024 ? `${Number(bytes || 0)} B` : `${(Number(bytes) / 1024).toFixed(1)} KB`;
    const pretty = value => value == null ? '—' : JSON.stringify(value, null, 2);

    const render = () => {
        if (!traffic.length) {
            rows.innerHTML = '<tr><td colspan="7" class="p-12 text-center text-gray-500">Henüz filtrelere uygun API trafiği yok.</td></tr>';
            return;
        }

        rows.innerHTML = traffic.map(log => {
            const branch = log.branch?.name || log.restaurant_id || 'Kimlik doğrulanmadı';
            const actor = log.staff_name || log.user_name || 'Anonim / giriş aşaması';
            const durationClass = log.duration_ms >= 1000 ? 'text-rose-300' : log.duration_ms >= 400 ? 'text-amber-300' : 'text-emerald-300';
            return `<tr class="transition hover:bg-gray-800/40">
                <td class="whitespace-nowrap p-4 font-mono text-xs text-gray-400">${escapeHtml(log.occurred_at_label || '-')}<div class="mt-1 text-[10px] text-gray-600">#${log.id}</div></td>
                <td class="p-4"><div class="flex items-center gap-2"><span class="rounded border px-2 py-1 font-mono text-[11px] font-black ${methodClass(log.method)}">${escapeHtml(log.method)}</span><span class="rounded border px-2 py-1 font-mono text-xs font-bold ${statusClass(log.status_code)}">${log.status_code}</span></div></td>
                <td class="max-w-xl p-4"><div class="break-all font-mono text-xs font-semibold text-white">${escapeHtml(log.path)}</div><div class="mt-1 font-mono text-[10px] text-indigo-400">${escapeHtml(log.route_name || '-')}</div></td>
                <td class="p-4"><div class="font-semibold text-gray-200">${escapeHtml(branch)}</div><div class="mt-1 text-xs text-gray-500">${escapeHtml(actor)}</div><div class="mt-1 font-mono text-[10px] text-gray-600">${escapeHtml(log.ip_address || '-')}</div></td>
                <td class="p-4"><span class="font-mono text-sm font-bold ${durationClass}">${Number(log.duration_ms).toLocaleString('tr-TR')} ms</span></td>
                <td class="p-4 text-xs text-gray-400"><div>↑ ${byteLabel(log.request_size)}</div><div class="mt-1">↓ ${byteLabel(log.response_size)}</div></td>
                <td class="p-4 text-right"><button type="button" data-log-id="${log.id}" class="detail-button rounded-lg border border-indigo-500/30 bg-indigo-950/40 px-3 py-2 text-xs font-bold text-indigo-300 hover:bg-indigo-900/60">İstek / Cevap</button></td>
            </tr>`;
        }).join('');

        document.querySelectorAll('.detail-button').forEach(button => button.addEventListener('click', () => openDetail(Number(button.dataset.logId))));
    };

    const openDetail = id => {
        const log = traffic.find(item => Number(item.id) === id);
        if (!log) return;
        const method = document.getElementById('detailMethod');
        method.textContent = log.method;
        method.className = `rounded border px-2 py-1 font-mono text-xs font-black ${methodClass(log.method)}`;
        const status = document.getElementById('detailStatus');
        status.textContent = `HTTP ${log.status_code}`;
        status.className = `rounded border px-2 py-1 font-mono text-xs font-bold ${statusClass(log.status_code)}`;
        document.getElementById('detailDuration').textContent = `${log.duration_ms} ms · ↑ ${byteLabel(log.request_size)} · ↓ ${byteLabel(log.response_size)}`;
        document.getElementById('detailPath').textContent = log.path;
        document.getElementById('detailMeta').textContent = `${log.occurred_at_label || '-'} · Request ID: ${log.request_id} · ${log.ip_address || '-'}`;
        document.getElementById('detailHeaders').textContent = pretty(log.request_headers);
        document.getElementById('detailRequest').textContent = pretty(log.request_payload);
        document.getElementById('detailResponse').textContent = pretty(log.response_payload);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };

    const setLiveState = (state, label) => {
        document.getElementById('liveText').textContent = label;
        document.getElementById('liveDot').className = `h-2 w-2 rounded-full ${state === 'online' ? 'animate-pulse bg-emerald-400' : state === 'paused' ? 'bg-amber-400' : 'bg-rose-400'}`;
    };

    const updateStats = stats => {
        document.getElementById('statRequests').textContent = Number(stats.requests_24h || 0).toLocaleString('tr-TR');
        document.getElementById('statErrors').textContent = Number(stats.errors_24h || 0).toLocaleString('tr-TR');
        document.getElementById('statAverage').textContent = Number(stats.average_ms_24h || 0).toLocaleString('tr-TR');
        document.getElementById('statMinute').textContent = Number(stats.requests_last_minute || 0).toLocaleString('tr-TR');
    };

    const poll = async () => {
        if (paused || polling) return;
        polling = true;
        try {
            const params = new URLSearchParams(Object.entries(filters).filter(([, value]) => value !== null && value !== ''));
            const maxId = traffic.reduce((max, log) => Math.max(max, Number(log.id)), 0);
            if (maxId > 0) params.set('after_id', String(maxId));
            const response = await fetch(`${streamUrl}?${params.toString()}`, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, cache: 'no-store'});
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            const known = new Set(traffic.map(log => Number(log.id)));
            data.logs.forEach(log => { if (!known.has(Number(log.id))) traffic.unshift(log); });
            traffic = traffic.slice(0, 100);
            updateStats(data.stats);
            document.getElementById('lastUpdate').textContent = `Son güncelleme: ${new Date(data.server_time).toLocaleTimeString('tr-TR')}`;
            setLiveState('online', 'CANLI');
            render();
        } catch (error) {
            setLiveState('error', 'BAĞLANTI HATASI');
            document.getElementById('lastUpdate').textContent = error.message;
        } finally {
            polling = false;
        }
    };

    pauseButton.addEventListener('click', () => {
        paused = !paused;
        pauseButton.textContent = paused ? 'Akışı Devam Ettir' : 'Akışı Duraklat';
        setLiveState(paused ? 'paused' : 'online', paused ? 'DURAKLATILDI' : 'CANLI');
        if (!paused) poll();
    });

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };
    document.getElementById('closeModal').addEventListener('click', closeModal);
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeModal(); });

    render();
    setInterval(poll, pollInterval);
})();
</script>
@endsection
