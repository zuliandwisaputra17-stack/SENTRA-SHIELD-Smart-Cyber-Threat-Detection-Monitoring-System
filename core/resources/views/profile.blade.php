<x-app-layout>
    <x-slot name="title">Profil</x-slot>
    <x-slot name="header">
        <h2 class="font-stensil text-[30px] uppercase leading-tight tracking-[0.06em] text-ink">Profil</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-[1600px] space-y-4 px-4 sm:px-6 lg:px-8">
            <div class="panel p-4 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="panel p-4 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
