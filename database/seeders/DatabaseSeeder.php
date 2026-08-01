<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Device;
use App\Models\License;
use App\Models\Organization;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production') && (! env('SEED_ADMIN_PASSWORD') || ! env('SEED_CASHIER_PASSWORD') || ! env('SEED_CHAIN_PASSWORD'))) {
            throw new \RuntimeException('Production seed için SEED_ADMIN_PASSWORD, SEED_CASHIER_PASSWORD ve SEED_CHAIN_PASSWORD zorunludur.');
        }

        $adminPassword = env('SEED_ADMIN_PASSWORD', 'password');
        $cashierPassword = env('SEED_CASHIER_PASSWORD', 'password');
        $chainPassword = env('SEED_CHAIN_PASSWORD', 'password');

        // 1. Central Admin Kullanıcısı
        User::updateOrCreate(
            ['email' => 'admin@adisyon.com'],
            [
                'name' => 'Sistem Yöneticisi',
                'email' => 'admin@adisyon.com',
                'restaurant_id' => 'REST-ADMIN',
                'password' => Hash::make($adminPassword),
                'is_admin' => true,
            ]
        );

        // 2. Restoran Kasa Kullanıcısı
        User::updateOrCreate(
            ['email' => 'kasa@adisyon.com'],
            [
                'name' => 'Restoran Kasa Görevlisi',
                'email' => 'kasa@adisyon.com',
                'restaurant_id' => 'REST-101',
                'password' => Hash::make($cashierPassword),
                'is_admin' => false,
            ]
        );

        // 3. Merkez Şube Kullanıcısı
        User::updateOrCreate(
            ['email' => 'merkez@synaptropic.com'],
            [
                'name' => 'Antigravity Merkez Şube Yöneticisi',
                'email' => 'merkez@synaptropic.com',
                'restaurant_id' => 'REST-102',
                'password' => Hash::make($cashierPassword),
                'is_admin' => false,
            ]
        );

        // 3. Örnek Şube
        $branch = Branch::updateOrCreate(
            ['code' => 'MERKEZ-01'],
            [
                'name' => 'Antigravity Merkez Restoran',
                'contact_email' => 'merkez@synaptropic.com',
                'phone' => '0212 555 0000',
                'address' => 'İstanbul, Türkiye',
                'is_active' => true,
            ]
        );

        User::where('is_admin', false)->whereNull('branch_id')->update(['branch_id' => $branch->id]);

        // Zincir yönetim paneli için örnek organizasyon ve yönetici.
        $organization = Organization::updateOrCreate(
            ['code' => 'ANTIGRAVITY'],
            ['name' => 'Antigravity Restoranları', 'is_active' => true]
        );
        $organization->branches()->syncWithoutDetaching([$branch->id]);

        User::updateOrCreate(
            ['email' => 'zincir@adisyon.com'],
            [
                'name' => 'Zincir Genel Müdürü',
                'restaurant_id' => null,
                'branch_id' => null,
                'organization_id' => $organization->id,
                'chain_role' => 'owner',
                'password' => Hash::make($chainPassword),
                'is_admin' => false,
            ]
        );

        // 4. C# Servisinin kullandığı Aktif Lisans Anahtarı
        $license = License::updateOrCreate(
            ['license_key' => 'ALTF4-8899-7711-XYZ9'],
            [
                'branch_id' => $branch->id,
                'device_token' => 'a1b2c3d4-e5f6-7890-abcd-1234567890ab',
                'status' => 'Active',
                'expires_at' => now()->addYear(),
                'max_devices' => 10,
                'notes' => 'Varsayılan C# Servis Lisans Key',
            ]
        );

        // 5. Örnek Kasa Cihazı
        Device::updateOrCreate(
            ['device_code' => 'KASA-01'],
            [
                'branch_id' => $branch->id,
                'license_id' => $license->id,
                'device_guid' => '12345678-1234-1234-1234-123456789abc',
                'ip_address' => '127.0.0.1',
                'os_info' => 'Windows 11 Pro 64-bit',
                'status' => 'Online',
                'last_ping_at' => now(),
                'app_version' => '1.0.0',
            ]
        );

        // 6. Netflix Tarzı Örnek Personel Profilleri (4-6 Haneli PIN Kodlu)
        StaffProfile::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Ahmet Yılmaz'],
            [
                'role' => 'Garson',
                'pin_code' => 'migrated',
                'pin_hash' => Hash::make(env('SEED_WAITER_PIN', '1234')),
                'avatar_color' => 'indigo',
                'is_active' => true,
            ]
        );

        StaffProfile::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Mehmet Usta'],
            [
                'role' => 'Mutfak',
                'pin_code' => 'migrated',
                'pin_hash' => Hash::make(env('SEED_KITCHEN_PIN', '4321')),
                'avatar_color' => 'emerald',
                'is_active' => true,
            ]
        );

        StaffProfile::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Ayşe Kaya'],
            [
                'role' => 'Kasa',
                'pin_code' => 'migrated',
                'pin_hash' => Hash::make(env('SEED_CASHIER_PIN', '5555')),
                'avatar_color' => 'amber',
                'is_active' => true,
            ]
        );

        StaffProfile::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Canan Kaptan'],
            [
                'role' => 'Kaptan',
                'pin_code' => 'migrated',
                'pin_hash' => Hash::make(env('SEED_CAPTAIN_PIN', '9999')),
                'avatar_color' => 'rose',
                'is_active' => true,
            ]
        );

        // 7. Masa, Salon, Kategori ve Ürün Seeder'ını Çalıştır
        $this->call(TableDemoSeeder::class);
    }
}
