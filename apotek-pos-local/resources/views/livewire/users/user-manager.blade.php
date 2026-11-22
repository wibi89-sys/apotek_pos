<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Session Alert -->
    @if (session('success'))
        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-2xl shadow-2xs flex items-center gap-3">
            <span class="text-emerald-600 font-bold text-lg">✓</span>
            <p class="text-xs text-emerald-800 font-bold">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-slate-200 gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-2xl">👥</span>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Kelola Pegawai & Hak Akses</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Daftar staf apotek, kasir, apoteker penanggung jawab, dan otorisasi PIN / Sidik Jari U.are.U</p>
        </div>
        <div>
            <button
                wire:click="openCreateModal"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shadow-xs transition cursor-pointer"
            >
                <span>➕</span> <span>Tambah Pegawai Baru</span>
            </button>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white rounded-2xl p-4 shadow-xs border border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="w-full sm:w-96 relative">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama pegawai, username, email..."
                class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
            />
            <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
        </div>
        <div class="text-xs text-slate-500">
            Total terdaftar: <strong class="text-slate-800">{{ $users->total() }}</strong> pegawai
        </div>
    </div>

    <!-- Table Users -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                        <th class="py-3.5 px-4 w-12 text-center">#</th>
                        <th class="py-3.5 px-4">Nama Pegawai & Username</th>
                        <th class="py-3.5 px-4">Peran / Jabatan</th>
                        <th class="py-3.5 px-4 text-center">Keamanan & PIN</th>
                        <th class="py-3.5 px-4 text-center">Biometrik U.are.U</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-right w-36">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $idx => $u)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3.5 px-4 text-center font-mono text-slate-400">
                                {{ $users->firstItem() + $idx }}
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 text-sm">{{ $u->name }}</div>
                                <div class="text-slate-400 font-mono text-[11px] mt-0.5">@<span>{{ $u->username }}</span> • {{ $u->email }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($u->role === \App\Enums\UserRole::OWNER)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-purple-50 text-purple-700 border border-purple-200">
                                        👑 Pemilik (PSA)
                                    </span>
                                @elseif($u->role === \App\Enums\UserRole::APOTEKER)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-blue-50 text-blue-700 border border-blue-200">
                                        🩺 Apoteker (APA)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        🛒 Kasir (TTK)
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($u->pin_hash)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                        🔒 PIN Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 bg-slate-50 px-2 py-0.5 rounded-md border border-slate-200">
                                        Belum ada PIN
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($u->fingerprint_template)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200">
                                        <span>👆</span> Terdaftar
                                    </span>
                                @else
                                    <a
                                        href="{{ route('settings.fingerprint') }}"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 px-2 py-1 rounded-lg border border-amber-200 transition"
                                    >
                                        <span>+</span> Rekam Jari
                                    </a>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <button
                                    wire:click="toggleStatus('{{ $u->id }}')"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold cursor-pointer transition {{ $u->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}"
                                >
                                    {{ $u->is_active ? '● Aktif' : '○ Nonaktif' }}
                                </button>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <button
                                    wire:click="editUser('{{ $u->id }}')"
                                    class="text-indigo-600 hover:text-indigo-800 font-bold px-2.5 py-1 rounded-lg hover:bg-indigo-50 transition cursor-pointer"
                                >
                                    ✏️ Edit
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                Belum ada data pegawai yang sesuai pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 bg-slate-50/50">
            {{ $users->links() }}
        </div>
    </div>

    <!-- Modal Form Pegawai -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 border border-slate-200 relative">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <h3 class="text-base font-black text-slate-900">
                        {{ $userId ? 'Edit Data Pegawai' : 'Tambah Pegawai Baru' }}
                    </h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-600 font-bold text-lg p-1 cursor-pointer">
                        ✕
                    </button>
                </div>

                <form wire:submit.prevent="save" class="mt-4 space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Lengkap & Gelar *</label>
                        <input
                            type="text"
                            wire:model="name"
                            placeholder="Contoh: Siti Rahmawati, A.Md.Farm"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        />
                        @error('name') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Username Login *</label>
                            <input
                                type="text"
                                wire:model="username"
                                placeholder="misal: kasir2"
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            />
                            @error('username') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Peran / Hak Akses *</label>
                            <select
                                wire:model="role"
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            >
                                <option value="kasir">Kasir (TTK)</option>
                                <option value="apoteker">Apoteker (APA/Supervisor)</option>
                                <option value="owner">Pemilik (PSA)</option>
                            </select>
                            @error('role') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Email (Opsional)</label>
                        <input
                            type="email"
                            wire:model="email"
                            placeholder="nama@apotek.local"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        />
                        @error('email') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">
                                Password {{ $userId ? '(Kosongkan jika tdk diganti)' : '*' }}
                            </label>
                            <input
                                type="password"
                                wire:model="password"
                                placeholder="Min. 6 karakter"
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            />
                            @error('password') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1" title="PIN 6-digit untuk otorisasi void nota di kasir">
                                PIN Supervisor (6 Digit)
                            </label>
                            <input
                                type="password"
                                wire:model="pin"
                                maxlength="6"
                                placeholder="6 digit angka"
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono text-center tracking-widest focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            />
                            @error('pin') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="pt-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="rounded text-indigo-600 focus:ring-indigo-500" />
                            <span class="font-bold text-slate-700">Akun Pegawai Aktif (Bisa Login & Transaksi)</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                        <button
                            type="button"
                            wire:click="$set('showModal', false)"
                            class="px-4 py-2 font-bold text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 font-black text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs transition cursor-pointer"
                        >
                            Simpan Pegawai
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
