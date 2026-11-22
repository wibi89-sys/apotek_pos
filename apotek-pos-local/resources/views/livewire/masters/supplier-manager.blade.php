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
                <span class="text-2xl">🏢</span>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Data Distributor & PBF Obat</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Daftar Pedagang Besar Farmasi (PBF), izin distribusi obat resmi, dan kontak pemasok</p>
        </div>
        <div>
            <button
                wire:click="openCreateModal"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shadow-xs transition cursor-pointer"
            >
                <span>➕</span> <span>Tambah PBF / Supplier Baru</span>
            </button>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white rounded-2xl p-4 shadow-xs border border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="w-full sm:w-96 relative">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari kode PBF, nama distributor, alamat..."
                class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
            />
            <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
        </div>
        <div class="text-xs text-slate-500">
            Total terdaftar: <strong class="text-slate-800">{{ $suppliers->total() }}</strong> distributor
        </div>
    </div>

    <!-- Table Suppliers -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                        <th class="py-3.5 px-4 w-12 text-center">#</th>
                        <th class="py-3.5 px-4 w-32">Kode PBF</th>
                        <th class="py-3.5 px-4">Nama Perusahaan Distributor</th>
                        <th class="py-3.5 px-4">Kontak / Telepon</th>
                        <th class="py-3.5 px-4">Alamat Gudang / Kantor</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-right w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($suppliers as $idx => $sup)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3.5 px-4 text-center font-mono text-slate-400">
                                {{ $suppliers->firstItem() + $idx }}
                            </td>
                            <td class="py-3.5 px-4 font-mono font-extrabold text-indigo-700">
                                {{ $sup->code }}
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-900 text-sm">
                                {{ $sup->name }}
                            </td>
                            <td class="py-3.5 px-4 font-mono text-slate-600">
                                {{ $sup->phone ?? '-' }}
                            </td>
                            <td class="py-3.5 px-4 text-slate-600">
                                {{ $sup->address ?? '-' }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <button
                                    wire:click="toggleStatus('{{ $sup->id }}')"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold cursor-pointer transition {{ $sup->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}"
                                >
                                    {{ $sup->is_active ? '● Aktif' : '○ Nonaktif' }}
                                </button>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <button
                                    wire:click="editSupplier('{{ $sup->id }}')"
                                    class="text-indigo-600 hover:text-indigo-800 font-bold px-2.5 py-1 rounded-lg hover:bg-indigo-50 transition cursor-pointer"
                                >
                                    ✏️ Edit
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                Belum ada data PBF/distributor terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 bg-slate-50/50">
            {{ $suppliers->links() }}
        </div>
    </div>

    <!-- Modal Form Supplier -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 border border-slate-200 relative">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <h3 class="text-base font-black text-slate-900">
                        {{ $supplierId ? 'Edit Distributor PBF' : 'Tambah Distributor Baru' }}
                    </h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-600 font-bold text-lg p-1 cursor-pointer">
                        ✕
                    </button>
                </div>

                <form wire:submit.prevent="save" class="mt-4 space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Kode PBF *</label>
                        <input
                            type="text"
                            wire:model="code"
                            placeholder="Contoh: PBF-ENS"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono uppercase focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        />
                        @error('code') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Perusahaan / PBF *</label>
                        <input
                            type="text"
                            wire:model="name"
                            placeholder="Contoh: PT Enseval Putera Megatrading Tbk"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        />
                        @error('name') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">No. Telepon / Sales</label>
                        <input
                            type="text"
                            wire:model="phone"
                            placeholder="Contoh: 021-4609042 / 0811xxxx"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        />
                        @error('phone') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Alamat Kantor / Gudang PBF</label>
                        <textarea
                            wire:model="address"
                            rows="2"
                            placeholder="Alamat lengkap distributor..."
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        ></textarea>
                        @error('address') <span class="text-rose-500 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="rounded text-indigo-600 focus:ring-indigo-500" />
                            <span class="font-bold text-slate-700">Status PBF Aktif (Bisa Dipilih di Faktur Pembelian)</span>
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
                            Simpan Distributor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
