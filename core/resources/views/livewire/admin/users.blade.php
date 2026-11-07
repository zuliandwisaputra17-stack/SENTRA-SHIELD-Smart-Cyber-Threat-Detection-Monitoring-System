<div>
    <x-ui.page-header title="Pengguna" icon="user">
        Personel yang boleh masuk, perannya, dan satuannya.
    </x-ui.page-header>

    <div class="mx-auto max-w-[1600px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">
        <section class="panel p-4 sm:p-6">
            <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">01 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Personel</span></p>
            <div class="mt-3 overflow-x-auto">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th class="py-2 pe-4">Nama</th>
                            <th class="py-2 pe-4">Email</th>
                            <th class="py-2 pe-4">Peran</th>
                            <th class="py-2 pe-4">Satuan</th>
                            <th class="py-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($users as $user)
                            <tr wire:key="user-{{ $user->id }}">
                                <td class="py-2 pe-4 text-ink">{{ $user->name }}</td>
                                <td class="py-2 pe-4 text-ink-2">{{ $user->email }}</td>
                                <td class="py-2 pe-4 text-ink-2">{{ $user->role->label() }}</td>
                                <td class="py-2 pe-4 text-ink-2">{{ $user->unit?->name ?? '—' }}</td>
                                <td class="py-2"><button wire:click="edit({{ $user->id }})" class="text-xs link">Ubah</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel p-4 sm:p-6">
            <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">02 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Registrasi</span></p>
            <h3 class="mt-1 text-sm font-semibold text-ink-2">{{ $editingId ? 'Ubah pengguna' : 'Tambah pengguna' }}</h3>
            <form wire:submit="save" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <x-input-label for="name" value="Nama" />
                    <x-text-input wire:model="name" id="name" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="role" value="Peran" />
                    <select wire:model.live="role" id="role" class="mt-1 block w-full rounded-control border-line-control bg-canvas text-ink-2 text-sm">
                        @foreach (\App\Enums\Role::cases() as $r)
                            <option value="{{ $r->value }}">{{ $r->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('role')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="unitId" value="Satuan" />
                    <select wire:model="unitId" id="unitId" class="mt-1 block w-full rounded-control border-line-control bg-canvas text-ink-2 text-sm">
                        <option value="">—</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('unitId')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="password" :value="$editingId ? 'Kata sandi baru (kosongkan bila tidak diganti)' : 'Kata sandi (min. 10 karakter)'" />
                    <x-text-input wire:model="password" id="password" type="password" autocomplete="new-password" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                </div>
                <div class="flex items-end gap-2">
                    <x-primary-button>{{ $editingId ? 'Simpan perubahan' : 'Tambah pengguna' }}</x-primary-button>
                    @if ($editingId)
                        <button type="button" wire:click="resetForm" class="text-xs text-ink-3 underline">Batal</button>
                    @endif
                </div>
            </form>
        </section>
    </div>
</div>
