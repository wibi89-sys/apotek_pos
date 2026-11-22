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
                <span class="text-2xl">🩺</span>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Data Dokter Penulis Resep</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Daftar dokter mitra, nomor Surat Izin Praktik (SIP), dan fasilitas kesehatan untuk transaksi resep kasir</p>
        </div>
        <div>
            <button
                wire:click="openCreateModal"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shadow-xs transition cursor-pointer"
            >
                <span>➕</span> <span>Tambah Dokter Baru</span>
            </button>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white rounded-2xl p-4 shadow-xs border border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="w-full sm:w-96 relative">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama dokter, no. SIP, faskes/RS..."
                class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
            />
            <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
        </div>
        <div class="text-xs text-slate-500">
            Total terdaftar: <strong class="text-slate-800">{{ $doctors->total() }}</strong> dokter
        </div>
    </div>

    <!-- Table Doctors -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                        <th class="py-3.5 px-4 w-12 text-center">#</th>
                        <th class="py-3.5 px-4">Nama Lengkap & Gelar</th>
                        <th class="py-3.5 px-4">No. SIP</th>
                        <th class="py-3.5 px-4">Spesialisasi</th>
                        <th class="py-3.5 px-4">Klinik / RS / Faskes</th>
                        <th class="py-3.5 px-4">No. Kontak</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-right w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($doctors as $idx => $doc)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3.5 px-4 text-center font-mono text-slate-400">
                                {{ $doctors->firstItem() + $idx }}
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 text-sm">{{ $doc->name }}</div>
                                @if($doc->address)
                                    <div class="text-slate-400 text-[11px] mt-0.5">{{ $doc->address }}</div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-700">
                                {{ $doc->sip_number ?? '-' }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-[11px] font-semibold border border-slate-200">
                                    {{ $doc->specialization ?? 'Dokter Umum' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 font-medium">
                                {{ $doc->clinic_name ?? '-' }}
                            </td>
                            <td class="py-3.5 px-4 font-mono text-slate-600">
                                {{ $doc->phone ?? '-' }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <button
                                    wire:click="toggleStatus('{{ $doc->id }}')"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold cursor-pointer transition {{ $doc->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}"
                                >
                                    {{ $doc->is_active ? '● Aktif' : '○ Nonaktif' }}
                                </button>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <button
                                    wire:click="editDoctor('{{ $doc->id }}')"
                                    class="text-indigo-600 hover:text-indigo-800 font-bold px-2.5 py-1 rounded-lg hover:bg-indigo-50 transition cursor-pointer"
                                >
                                    ✏️ Edit
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-400">
                                Belum ada data dokter terdaftar. Klik "+ Tambah Dokter Baru" untuk memasukkan data.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 bg-slate-50/50">
            {{ $doctors->links() }}
        </div>
    </div>

    <!-- Modal Form Dokter -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 border border-slate-200 relative">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <h3 class="text-base font-black text-slate-900">
                        {{ $doctorId ? 'Edit Data Dokter' : 'Tambah Dokter Baru' }}
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
                            placeholder="Contoh: dr. Hendra Pratama, Sp.A"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        />
                        @error('name') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">No. SIP (Surat Izin Praktik)</label>
                            <input
                                type="text"
                                wire:model="sip_number"
                                placeholder="446/123/SIP.D/2024"
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            />
                            @error('sip_number') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Spesialisasi</label>
                            <input
                                type="text"
                                wire:model="specialization"
                                placeholder="Umum / Sp.A / Sp.PD"
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            />
                            @error('specialization') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">RS / Faskes / Klinik</label>
                            <input
                                type="text"
                                wire:model="clinic_name"
                                placeholder="RSUD / Klinik Sehat"
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            />
                            @error('clinic_name') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">No. Telepon / HP</label>
                            <input
                                type="text"
                                wire:model="phone"
                                placeholder="0812xxxxxxxx"
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            />
                            @error('phone') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Alamat Praktik (Opsional)</label>
                        <input
                            type="text"
                            wire:model="address"
                            placeholder="Jl. Kesehatan No. 10"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        />
                        @error('address') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="rounded text-indigo-600 focus:ring-indigo-500" />
                            <span class="font-bold text-slate-700">Status Dokter Aktif (Bisa Dipilih di Kasir Resep)</span>
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
                            Simpan Dokter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
