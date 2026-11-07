<div>
    <x-ui.page-header title="Pengaturan" icon="settings" />

    <div class="py-6 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <form wire:submit="save" class="space-y-6">
            <section class="panel p-4 sm:p-6" aria-labelledby="set-blokir">
                <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">01 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Respons</span></p>
                <h3 id="set-blokir" class="mt-1 text-sm font-semibold text-ink-2">Respons</h3>
                <div class="mt-4 max-w-xs">
                    <x-input-label for="blockDuration" value="Durasi blokir default" />
                    <select wire:model="blockDuration" id="blockDuration" class="mt-1 block w-full rounded-control border-line-control bg-canvas text-sm text-ink-2">
                        @foreach (\App\Response\ActionService::DURATIONS as $seconds => $label)
                            <option value="{{ $seconds }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-ink-3">Pilihan awal saat mengusulkan blokir IP; pengusul tetap bisa memilih durasi lain.</p>
                    <x-input-error :messages="$errors->get('blockDuration')" class="mt-1" />
                </div>
            </section>

            <section class="panel p-4 sm:p-6" aria-labelledby="set-llm">
                <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">02 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Penyusun laporan</span></p>
                <h3 id="set-llm" class="mt-1 text-sm font-semibold text-ink-2">LLM lokal untuk LAPSIT (Ollama)</h3>
                <p class="mt-1 text-xs text-ink-3">Data insiden hanya dikirim ke alamat ini. Bila Ollama tidak tersedia atau melewati batas waktu, LAPSIT dibuat dari template.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="ollamaUrl" value="Alamat Ollama" />
                        <x-text-input wire:model="ollamaUrl" id="ollamaUrl" class="mt-1 block w-full font-mono text-sm" placeholder="http://ollama:11434" />
                        <x-input-error :messages="$errors->get('ollamaUrl')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="ollamaModel" value="Model" />
                        <x-text-input wire:model="ollamaModel" id="ollamaModel" class="mt-1 block w-full font-mono text-sm" />
                        <x-input-error :messages="$errors->get('ollamaModel')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="ollamaTimeout" value="Batas waktu (detik)" />
                        <x-text-input wire:model="ollamaTimeout" id="ollamaTimeout" type="number" min="10" max="{{ \App\Reports\OllamaWriter::MAX_TIMEOUT }}" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('ollamaTimeout')" class="mt-1" />
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button" wire:click="checkOllama" wire:loading.attr="disabled" wire:target="checkOllama" class="inline-flex h-9 items-center rounded-control border border-line-control bg-raised px-3 font-display text-sm font-bold uppercase tracking-[0.08em] text-ink hover:border-accent hover:bg-inset">Periksa koneksi Ollama</button>
                    <span wire:loading wire:target="checkOllama" class="text-sm text-ink-3">Memeriksa…</span>
                    @if ($ollamaStatus)
                        <span class="text-sm {{ $ollamaStatus['ok'] ? 'text-aman' : 'text-waspada' }}" role="status" data-testid="status-ollama">{{ $ollamaStatus['message'] }}</span>
                    @endif
                </div>
            </section>

            <div class="flex items-center gap-3">
                <x-primary-button>Simpan pengaturan</x-primary-button>
                @if ($saved)
                    <span class="text-sm text-aman" role="status">{{ $saved }}</span>
                @endif
            </div>
        </form>
    </div>
</div>
