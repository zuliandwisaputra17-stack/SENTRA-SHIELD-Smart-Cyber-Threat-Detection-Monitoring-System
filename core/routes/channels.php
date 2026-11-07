<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Sinyal pembaruan dashboard live. Isinya hanya jenis perubahan; data diambil
// ulang per pengguna sesuai satuannya.
Broadcast::channel('sentra.dashboard', fn (User $user) => $user->can('lihat-dashboard'));
