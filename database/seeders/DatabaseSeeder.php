<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Device;
use App\Models\License;
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
        // 1. Central Admin Kullanıcısı
        User::updateOrCreate(
            ['email' => 'admin@adisyon.com'],
            [
                'name' => 'Sistem Yöneticisi',
                'email' => 'admin@adisyon.com',
                'restaurant_id' => 'REST-ADMIN',
                'password' => Hash::make('password'),
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
                'password' => Hash::make('password'),
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
                'password' => Hash::make('password'),
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
        \App\Models\StaffProfile::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Ahmet Yılmaz'],
            [
                'role' => 'Garson',
                'pin_code' => '1234',
                'avatar_color' => 'indigo',
                'is_active' => true,
            ]
        );

        \App\Models\StaffProfile::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Mehmet Usta'],
            [
                'role' => 'Mutfak',
                'pin_code' => '4321',
                'avatar_color' => 'emerald',
                'is_active' => true,
            ]
        );

        \App\Models\StaffProfile::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Ayşe Kaya'],
            [
                'role' => 'Kasa',
                'pin_code' => '5555',
                'avatar_color' => 'amber',
                'is_active' => true,
            ]
        );

        \App\Models\StaffProfile::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Canan Kaptan'],
            [
                'role' => 'Kaptan',
                'pin_code' => '9999',
                'avatar_color' => 'rose',
                'is_active' => true,
            ]
        );
    }
}
