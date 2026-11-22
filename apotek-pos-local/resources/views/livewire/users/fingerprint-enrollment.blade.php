<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="text-2xl">👆</span>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                    Registrasi Biometrik Sidik Jari (U.are.U 4500)
                </h1>
                <span class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-[10px] font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider shadow-2xs">
                    PRO FEATURE
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1 max-w-2xl leading-relaxed">
                Pendaftaran sidik jari kasir & supervisor untuk otorisasi instan pembatalan transaksi (VOID), diskon khusus, dan buka laci kasir otomatis tanpa mengetik PIN secara manual.
            </p>
        </div>

        <!-- Hardware Status Badge Card -->
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-xs flex items-center gap-3.5 shadow-2xs self-start md:self-auto min-w-[280px]">
            <span class="w-3.5 h-3.5 rounded-full shrink-0 {{ ($hwStatus['status'] ?? '') === 'ready' ? 'bg-emerald-500 animate-pulse ring-4 ring-emerald-100' : 'bg-amber-500 ring-4 ring-amber-100' }}"></span>
            <div>
                <p class="font-bold text-slate-800">Status Sensor Hardware:</p>
                <p class="text-slate-600 font-medium text-[11px] mt-0.5">
                    {{ $hwStatus['device'] ?? ($hwStatus['message'] ?? 'Menunggu sensor...') }}
                </p>
                @if(isset($hwStatus['sensor_technology']))
                    <p class="text-[10px] text-slate-400 font-mono mt-0.5">{{ $hwStatus['sensor_technology'] }}</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    @if ($successMessage)
        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-2xl shadow-2xs flex items-center gap-2">
            <span class="text-emerald-600 font-bold">✓</span>
            <p class="text-xs text-emerald-800 font-bold">{{ $successMessage }}</p>
        </div>
    @endif

    @if ($errorMessage)
        <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-2xl shadow-2xs flex items-center gap-2">
            <span class="text-rose-600 font-bold">✕</span>
            <p class="text-xs text-rose-800 font-bold">{{ $errorMessage }}</p>
        </div>
    @endif

    <!-- Users Table Card -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-extrabold text-slate-900">Daftar Pengguna & Status Biometrik</h2>
                <p class="text-xs text-slate-500 mt-0.5">Pilih staf apotek dan tempelkan jari pada sensor DigitalPersona U.are.U saat tombol ditekan.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-slate-100 text-xs text-left">
                <thead class="bg-slate-50/80 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="py-3.5 px-6">Nama Staf / Pengguna</th>
                        <th class="py-3.5 px-6">Username Login</th>
                        <th class="py-3.5 px-6">Peran / Otoritas</th>
                        <th class="py-3.5 px-6">Status Rekaman Jari</th>
                        <th class="py-3.5 px-6 text-right">Aksi Pendaftaran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $user)
                        <tr class="hover:bg-indigo-50/20 transition">
                            <td class="py-4 px-6 font-bold text-slate-900 text-sm">
                                {{ $user->name }}
                            </td>
                            <td class="py-4 px-6 text-slate-500 font-mono text-xs">
                                {{ $user->username }}
                            </td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold
                                    {{ $user->role->canAuthorizeSupervisorActions() ? 'bg-indigo-100 text-indigo-800 border border-indigo-200' : 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                                    {{ $user->role->label() }}
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                @if ($user->fingerprint_template)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> ✓ Terdaftar (U.are.U)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-500 border border-slate-200">
                                        Belum Terdaftar
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-right space-x-2 whitespace-nowrap">
                                <button
                                    wire:click="enroll('{{ $user->id }}')"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer"
                                >
                                    <span wire:loading.remove wire:target="enroll('{{ $user->id }}')">
                                        👆 {{ $user->fingerprint_template ? 'Pindai Ulang' : 'Daftarkan Sidik Jari' }}
                                    </span>
                                    <span wire:loading wire:target="enroll('{{ $user->id }}')" class="flex items-center gap-1">
                                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        Membaca Sensor...
                                    </span>
                                </button>
                                @if ($user->fingerprint_template)
                                    <button
                                        wire:click="clearFingerprint('{{ $user->id }}')"
                                        class="inline-flex items-center px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-xl border border-rose-200 transition cursor-pointer"
                                    >
                                        Hapus
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
