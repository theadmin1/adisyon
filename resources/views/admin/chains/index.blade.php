@extends('admin.layout')

@section('title', 'Zincir Yönetimi - Central Admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-white">🏢 Zincir Yönetimi</h2>
            <p class="text-sm text-gray-400">Zincirleri, bağlı şubeleri ve yönetim paneli kullanıcılarını yönetin.</p>
        </div>
        <div class="flex gap-3">
            <button onclick="openModal('createChainModal')" class="rounded-lg border border-indigo-500/40 bg-indigo-950 px-4 py-2.5 text-sm font-bold text-indigo-300 hover:bg-indigo-900">Yeni Zincir</button>
            <button onclick="openModal('createUserModal')" @disabled($organizations->isEmpty()) class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-500 disabled:opacity-40">Yeni Panel Kullanıcısı</button>
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-rose-500/40 bg-rose-950/60 p-4 text-sm text-rose-300">
            <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-2">
        @forelse($organizations as $organization)
            <section class="overflow-hidden rounded-2xl border border-gray-800 bg-[#181a24]">
                <div class="flex items-start justify-between border-b border-gray-800 p-5">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-24 shrink-0 items-center justify-center rounded-xl border border-gray-800 bg-[#11131a] p-2">
                            <img src="{{ $organization->logo_url ?? asset('assets/images/logo.png') }}" alt="{{ $organization->name }} logosu" class="max-h-full max-w-full object-contain">
                        </div>
                        <div class="flex h-14 w-24 shrink-0 items-center justify-center rounded-xl border border-gray-300 bg-white p-2">
                            <img src="{{ $organization->light_logo_url ?? asset('assets/images/logo-light.png') }}" alt="{{ $organization->name }} açık mod logosu" class="max-h-full max-w-full object-contain">
                        </div>
                        <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-black text-white">{{ $organization->name }}</h3>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $organization->is_active ? 'bg-emerald-950 text-emerald-400' : 'bg-rose-950 text-rose-400' }}">{{ $organization->is_active ? 'AKTİF' : 'PASİF' }}</span>
                        </div>
                        <p class="mt-1 font-mono text-xs text-indigo-400">{{ $organization->code }}</p>
                        </div>
                    </div>
                    <button onclick="openModal('editChain{{ $organization->id }}')" class="rounded-lg border border-gray-700 px-3 py-1.5 text-xs text-gray-300 hover:bg-gray-800">Düzenle</button>
                </div>

                <div class="border-b border-gray-800 p-5">
                    <p class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-500">Bağlı Şubeler ({{ $organization->branches_count }})</p>
                    <div class="flex flex-wrap gap-2">
                        @forelse($organization->branches as $branch)
                            <span class="rounded-lg border border-cyan-500/20 bg-cyan-950/40 px-2.5 py-1 text-xs text-cyan-300">{{ $branch->name }}</span>
                        @empty
                            <span class="text-xs text-amber-400">Henüz şube bağlanmamış.</span>
                        @endforelse
                    </div>
                </div>

                <div class="p-5">
                    <p class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-500">Panel Kullanıcıları ({{ $organization->users_count }})</p>
                    <div class="space-y-3">
                        @forelse($organization->users as $chainUser)
                            <div class="flex flex-col gap-3 rounded-xl border border-gray-800 bg-[#11131a] p-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-bold text-white">{{ $chainUser->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $chainUser->email }} · {{ str_replace('_', ' ', strtoupper($chainUser->chain_role)) }}</p>
                                    <p class="mt-1 text-[11px] text-cyan-400">{{ $chainUser->chainBranches->isEmpty() ? 'Tüm zincir şubeleri' : $chainUser->chainBranches->pluck('name')->join(', ') }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="openModal('editUser{{ $chainUser->id }}')" class="rounded-lg border border-indigo-500/30 px-3 py-1.5 text-xs text-indigo-300">Yetki / Şube</button>
                                    <form method="POST" action="{{ route('admin.chain-users.destroy', $chainUser) }}" onsubmit="return confirm('Bu kullanıcı silinsin mi?')">@csrf @method('DELETE')<button class="rounded-lg border border-rose-500/30 px-3 py-1.5 text-xs text-rose-300">Sil</button></form>
                                </div>
                            </div>

                            <div id="editUser{{ $chainUser->id }}" class="modal hidden fixed inset-0 z-50 items-center justify-center bg-black/75 p-4">
                                <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-gray-700 bg-[#181a24] p-6">
                                    <div class="mb-5 flex justify-between"><h3 class="font-black">Kullanıcıyı Düzenle</h3><button onclick="closeModal('editUser{{ $chainUser->id }}')">✕</button></div>
                                    @include('admin.chains.partials.user-form', ['action' => route('admin.chain-users.update', $chainUser), 'method' => 'PUT', 'editingUser' => $chainUser, 'selectedOrganization' => $organization])
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-gray-500">Panel kullanıcısı bulunmuyor.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <div id="editChain{{ $organization->id }}" class="modal hidden fixed inset-0 z-50 items-center justify-center bg-black/75 p-4">
                <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-gray-700 bg-[#181a24] p-6">
                    <div class="mb-5 flex justify-between"><h3 class="font-black">Zinciri Düzenle</h3><button onclick="closeModal('editChain{{ $organization->id }}')">✕</button></div>
                    <form method="POST" action="{{ route('admin.chains.update', $organization) }}" enctype="multipart/form-data" class="space-y-4">@csrf @method('PUT')
                        @include('admin.chains.partials.organization-fields', ['editingOrganization' => $organization])
                        <button class="w-full rounded-xl bg-indigo-600 py-3 text-sm font-bold">Kaydet</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-gray-700 p-12 text-center text-gray-500">İlk zinciri oluşturarak başlayın.</div>
        @endforelse
    </div>
</div>

<div id="createChainModal" class="modal hidden fixed inset-0 z-50 items-center justify-center bg-black/75 p-4">
    <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-gray-700 bg-[#181a24] p-6">
        <div class="mb-5 flex justify-between"><h3 class="font-black">Yeni Zincir</h3><button onclick="closeModal('createChainModal')">✕</button></div>
        <form method="POST" action="{{ route('admin.chains.store') }}" enctype="multipart/form-data" class="space-y-4">@csrf
            @include('admin.chains.partials.organization-fields', ['editingOrganization' => null])
            <button class="w-full rounded-xl bg-indigo-600 py-3 text-sm font-bold">Zinciri Oluştur</button>
        </form>
    </div>
</div>

<div id="createUserModal" class="modal hidden fixed inset-0 z-50 items-center justify-center bg-black/75 p-4">
    <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-gray-700 bg-[#181a24] p-6">
        <div class="mb-5 flex justify-between"><h3 class="font-black">Yeni Zincir Paneli Kullanıcısı</h3><button onclick="closeModal('createUserModal')">✕</button></div>
        @include('admin.chains.partials.user-form', ['action' => route('admin.chain-users.store'), 'method' => 'POST', 'editingUser' => null, 'selectedOrganization' => null])
    </div>
</div>

<script>
function openModal(id) { const el = document.getElementById(id); el.classList.remove('hidden'); el.classList.add('flex'); }
function closeModal(id) { const el = document.getElementById(id); el.classList.add('hidden'); el.classList.remove('flex'); }
</script>
@endsection
