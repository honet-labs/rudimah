<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('rudimah:status', function () {
    $this->info('RUDIMAH (Ruang Digital Manajemen Harian) - Operational');
})->purpose('Menampilkan status platform RUDIMAH');

Artisan::command('admin:reset-password {email? : Alamat email akun administrator} {--password= : Kata sandi baru} {--reset-mfa : Hapus metode MFA akun}', function () {
    $email = $this->argument('email') ?: $this->ask('Masukkan alamat email administrator', env('INITIAL_ADMIN_EMAIL', 'admin@rudimah.id'));

    $user = \App\Core\Identity\Models\User::where('email', $email)->first();

    if (!$user) {
        // Cari akun administrator super admin yang ada jika email tidak cocok
        $admins = \App\Core\Identity\Models\User::where('is_super_admin', true)->get();
        if ($admins->isNotEmpty()) {
            $this->warn("Pengguna dengan email '{$email}' tidak ditemukan.");
            $this->line("Daftar administrator yang tersedia:");
            foreach ($admins as $adm) {
                $this->line(" - {$adm->email} ({$adm->full_name})");
            }

            $chosen = $this->choice('Pilih akun yang ingin di-reset kata sandinya', $admins->pluck('email')->toArray());
            $user = \App\Core\Identity\Models\User::where('email', $chosen)->first();
            $email = $chosen;
        } else {
            $this->error("Tidak ditemukan akun administrator dalam sistem basis data.");
            return 1;
        }
    }

    $newPassword = $this->option('password');
    if (!$newPassword) {
        $newPassword = $this->secret('Masukkan kata sandi baru (tekan Enter untuk membuat kata sandi acak aman)');
    }

    if (empty($newPassword)) {
        $newPassword = 'Rudimah!' . \Illuminate\Support\Str::random(10) . '2026';
        $this->line("[*] Kata sandi acak berhasil dibuat.");
    }

    $user->password = \Illuminate\Support\Facades\Hash::make($newPassword);
    $user->is_active = true;
    $user->is_super_admin = true;
    $user->save();

    // Reset MFA jika diminta
    $resetMfa = $this->option('reset-mfa');
    if ($resetMfa || ($this->input->isInteractive() && $user->mfaMethods()->exists() && $this->confirm('Apakah Anda ingin mereset metode autentikasi MFA untuk akun ini?', true))) {
        $user->mfaMethods()->delete();
        $this->info("[*] Seluruh metode MFA akun telah di-reset agar Anda dapat langsung masuk.");
    }

    // Bersihkan sesi aktif lama
    \Illuminate\Support\Facades\DB::connection('platform')->table('auth_sessions')->where('user_id', $user->id)->delete();

    $this->info("==================================================================");
    $this->info("          KATA SANDI ADMINISTRATOR BERHASIL DI-RESET             ");
    $this->info("==================================================================");
    $this->line("Akun Administrator : {$user->full_name} ({$user->email})");
    $this->line("Kata Sandi Baru    : {$newPassword}");
    $this->line("Status Akun        : Aktif (Super Admin)");
    $this->info("==================================================================");

    return 0;
})->purpose('Reset kata sandi akun administrator RUDIMAH');

Schedule::command('queue:prune-failed --hours=48')->daily();
