<div class="max-w-4xl mx-auto py-8 px-4">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="bg-slate-900 px-6 py-5 text-white flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold">Status & Entitlement Lisensi</h1>
                <p class="text-xs text-slate-400 mt-1">Sertifikat Kriptografi Ed25519 (Operasional 100% Offline)</p>
            </div>
            @if($payload)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                    ● Terverifikasi Aktif
                </span>
            @else
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/20 text-rose-400 border border-rose-500/30">
                    ● Tidak Aktif
                </span>
            @endif
        </div>

        @if(session('success'))
            <div class="m-6 mb-0 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="m-6 mb-0 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if($payload)
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Info Apotek & Lisensi -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider border-b pb-2">Informasi Apotek</h3>
                    <div>
                        <div class="text-xs text-slate-400">Nama Apotek</div>
                        <div class="text-base font-semibold text-slate-800">{{ $payload['pharmacy_name'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">Pemilik / Customer</div>
                        <div class="text-sm font-medium text-slate-700">{{ $payload['customer_name'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">Kode Cabang</div>
                        <div class="text-sm font-mono text-slate-700">{{ $payload['branch_code'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">Kunci Lisensi</div>
                        <div class="text-sm font-mono font-bold text-blue-600">{{ $payload['license_key'] }}</div>
                    </div>
                </div>

                <!-- Paket & Kuota -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider border-b pb-2">Paket & Kuota</h3>
                    <div>
                        <div class="text-xs text-slate-400">Paket (Tier)</div>
                        <div class="text-base font-bold text-indigo-600">{{ $plan?->label() ?? $payload['plan'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">Masa Berlaku</div>
                        <div class="text-sm font-medium text-slate-700">
                            @if(empty($payload['expires_at']))
                                <span class="text-emerald-600 font-semibold">Lifetime (Seumur Hidup)</span>
                            @else
                                {{ \Carbon\Carbon::createFromTimestamp($payload['expires_at'])->format('d M Y') }}
                                @if($entitlement->isGracePeriod())
                                    <span class="text-amber-600 font-semibold">(Masa Tenggang)</span>
                                @endif
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">Batas Kuota Terminal POS</div>
                        <div class="text-sm font-medium text-slate-700">
                            Maks. {{ $payload['limits']['max_terminals'] ?? 'Unlimited' }} Terminal
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">HWID Mesin Ini</div>
                        <div class="text-xs font-mono text-slate-500 break-all bg-slate-50 p-2 rounded">{{ $payload['hwid'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Matriks Fitur Hak Akses -->
            <div class="px-6 pb-6">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider border-b pb-2 mb-4">Matriks Entitlement Fitur</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($features as $feature)
                        @php $allowed = $entitlement->hasFeature($feature); @endphp
                        <div class="flex items-center justify-between p-3 rounded-lg border {{ $allowed ? 'bg-emerald-50/40 border-emerald-200' : 'bg-slate-50 border-slate-200 opacity-60' }}">
                            <div class="text-xs font-medium {{ $allowed ? 'text-slate-800' : 'text-slate-500' }}">
                                {{ $feature->label() }}
                            </div>
                            <span class="text-xs font-bold px-2 py-0.5 rounded {{ $allowed ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                {{ $allowed ? 'AKTIF' : 'TERKUNCI' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-slate-50 px-6 py-4 border-t flex justify-between items-center">
                <a href="{{ route('license.activate') }}" class="text-xs text-slate-600 hover:text-blue-600 underline">
                    Ganti Kunci Lisensi
                </a>
                <button wire:click="refreshLicense" wire:loading.attr="disabled" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-4 py-2 rounded-lg transition disabled:opacity-50">
                    <span wire:loading.remove>Refresh Lisensi Online</span>
                    <span wire:loading>Memperbarui...</span>
                </button>
            </div>
        @else
            <div class="p-8 text-center">
                <p class="text-slate-600 mb-4">Aplikasi belum memiliki lisensi aktif di mesin ini.</p>
                <a href="{{ route('license.activate') }}" class="inline-block bg-blue-600 text-white text-sm font-semibold px-5 py-2.5 rounded-lg">
                    Aktivasi Lisensi Sekarang
                </a>
            </div>
        @endif
    </div>
</div>
