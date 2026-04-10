<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Setting;
use App\Models\Attendance;
use App\Models\Overtime;
use Faker\Factory as Faker;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // 1. Create Default Setting (Palu, Sulawesi Tengah)
        Setting::create([
            'latitude' => '-0.891700',
            'longitude' => '119.870700',
            'radius_meters' => 100,
            'check_in_time' => '08:00:00',
            'check_out_time' => '17:00:00',
        ]);

        // 2. Create 1 Admin
        $admin = User::create([
            'id_number' => '1234567890',
            'full_name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'Admin'
        ]);

        // 3. Create 20 Karyawan (Employees)
        $employees = [];
        for ($i = 0; $i < 20; $i++) {
            $employees[] = User::create([
                'id_number' => $faker->unique()->nik(),
                'full_name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'password' => Hash::make('password'),
                'role' => 'Employee'
            ]);
        }

        // Dummy Photos and Locations array
        $dummyPhotos = [
            'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=300&q=80',
            'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=300&q=80',
            'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=300&q=80'
        ];

        $dummyLocations = [
            '-6.2088,106.8456', // Jakarta Pusat
            '-6.2615,106.8106', // Jakarta Selatan
            '-6.1500,106.8900', // Jakarta Timur
            '-6.1600,106.7600'  // Jakarta Barat
        ];

        // 4. Seed Attendances and Overtimes untuk karyawan
        foreach ($employees as $emp) {
            // Kita buat riwayat selama 5 hari ke belakang
            for ($days = 1; $days <= 5; $days++) {
                $date = Carbon::now()->subDays($days)->format('Y-m-d');
                
                // --- Attendance dummy ---
                $clockIn = Carbon::parse($date . ' 07:30:00')->addMinutes(rand(0, 45))->format('H:i:s');
                $clockOut = Carbon::parse($date . ' 17:00:00')->addMinutes(rand(0, 120))->format('H:i:s');
                
                Attendance::create([
                    'user_id' => $emp->id,
                    'date' => $date,
                    'clock_in_at' => $clockIn,
                    'clock_in_photo' => $faker->randomElement($dummyPhotos),
                    'clock_in_location' => $faker->randomElement($dummyLocations),
                    'clock_out_at' => $clockOut,
                    'clock_out_photo' => $faker->randomElement($dummyPhotos),
                    'clock_out_location' => $faker->randomElement($dummyLocations),
                ]);

                // --- Overtime dummy --- (probabilitas 30% per karyawan dalam satu hari)
                if (rand(1, 100) <= 30) {
                    $plannedStart = '17:30:00';
                    $plannedEnd = Carbon::parse($date . ' ' . $plannedStart)->addHours(rand(1, 4))->format('H:i:s');
                    $status = $faker->randomElement(['Pending', 'Approved', 'Rejected']);
                    
                    Overtime::create([
                        'user_id' => $emp->id,
                        'overtime_date' => $date,
                        'planned_start' => $plannedStart,
                        'planned_end' => $plannedEnd,
                        'reason' => 'Penyelesaian target harian/mingguan - ' . $faker->sentence(3),
                        'approval_status' => $status,
                        // Jika status pending, approved_by nullable. Lainnya dari admin.
                        'approved_by' => $status === 'Pending' ? null : $admin->id,
                        'actual_start' => $status === 'Approved' ? $plannedStart : null,
                        'overtime_start_photo' => $status === 'Approved' ? $faker->randomElement($dummyPhotos) : null,
                        'actual_end' => $status === 'Approved' ? $plannedEnd : null,
                        'overtime_end_photo' => $status === 'Approved' ? $faker->randomElement($dummyPhotos) : null,
                    ]);
                }
            }
        }
    }
}
