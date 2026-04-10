<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Overtime;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeHomeController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->role === 'Admin') {
            return redirect()->route('dashboard');
        }

        $user = $request->user();
        $today = Carbon::today()->toDateString();
        $setting = Setting::query()->latest('id')->first();
        $todayAttendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        $approvedTodayOvertimes = Overtime::query()
            ->where('user_id', $user->id)
            ->whereDate('overtime_date', $today)
            ->where('approval_status', 'Approved')
            ->orderBy('planned_start')
            ->get();

        $recentAttendances = Attendance::query()
            ->where('user_id', $user->id)
            ->orderByDesc('date')
            ->limit(7)
            ->get();

        $recentOvertimes = Overtime::query()
            ->with('approver:id,full_name')
            ->where('user_id', $user->id)
            ->orderByDesc('overtime_date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('Home', [
            'setting' => [
                'latitude' => $setting?->latitude,
                'longitude' => $setting?->longitude,
                'radius_meters' => $setting?->radius_meters ?? 100,
                'check_in_time' => $setting?->check_in_time ? substr($setting->check_in_time, 0, 5) : null,
                'check_out_time' => $setting?->check_out_time ? substr($setting->check_out_time, 0, 5) : null,
                'is_configured' => filled($setting?->latitude) && filled($setting?->longitude),
            ],
            'todayAttendance' => $todayAttendance ? [
                'id' => $todayAttendance->id,
                'date' => $todayAttendance->date,
                'clock_in_at' => $todayAttendance->clock_in_at,
                'clock_out_at' => $todayAttendance->clock_out_at,
                'clock_in_photo' => $todayAttendance->clock_in_photo ? Storage::disk('public')->url($todayAttendance->clock_in_photo) : null,
                'clock_out_photo' => $todayAttendance->clock_out_photo ? Storage::disk('public')->url($todayAttendance->clock_out_photo) : null,
                'clock_in_location' => $todayAttendance->clock_in_location,
                'clock_out_location' => $todayAttendance->clock_out_location,
            ] : null,
            'approvedTodayOvertimes' => $approvedTodayOvertimes->map(fn (Overtime $overtime): array => [
                'id' => $overtime->id,
                'overtime_date' => $overtime->overtime_date,
                'planned_start' => $overtime->planned_start,
                'planned_end' => $overtime->planned_end,
                'reason' => $overtime->reason,
                'approval_status' => $overtime->approval_status,
                'actual_start' => $overtime->actual_start,
                'actual_end' => $overtime->actual_end,
                'overtime_start_photo' => $overtime->overtime_start_photo ? Storage::disk('public')->url($overtime->overtime_start_photo) : null,
                'overtime_end_photo' => $overtime->overtime_end_photo ? Storage::disk('public')->url($overtime->overtime_end_photo) : null,
            ]),
            'recentAttendances' => $recentAttendances->map(fn (Attendance $attendance): array => [
                'id' => $attendance->id,
                'date' => $attendance->date,
                'clock_in_at' => $attendance->clock_in_at,
                'clock_out_at' => $attendance->clock_out_at,
                'clock_in_location' => $attendance->clock_in_location,
                'clock_out_location' => $attendance->clock_out_location,
            ]),
            'recentOvertimes' => $recentOvertimes->map(fn (Overtime $overtime): array => [
                'id' => $overtime->id,
                'overtime_date' => $overtime->overtime_date,
                'planned_start' => $overtime->planned_start,
                'planned_end' => $overtime->planned_end,
                'reason' => $overtime->reason,
                'approval_status' => $overtime->approval_status,
                'approved_by' => $overtime->approver?->full_name,
                'actual_start' => $overtime->actual_start,
                'actual_end' => $overtime->actual_end,
            ]),
        ]);
    }

    public function clockIn(Request $request): RedirectResponse
    {
        $user = $this->ensureEmployee($request);
        $payload = $this->validatePresencePayload($request);
        $setting = $this->getAttendanceSetting();

        $attendance = Attendance::query()->firstOrNew([
            'user_id' => $user->id,
            'date' => Carbon::today()->toDateString(),
        ]);

        if ($attendance->clock_in_at) {
            throw ValidationException::withMessages([
                'attendance' => 'Anda sudah melakukan absen masuk hari ini.',
            ]);
        }

        $this->assertWithinOfficeRadius($payload['latitude'], $payload['longitude'], $setting);

        $attendance->fill([
            'clock_in_at' => now()->format('H:i:s'),
            'clock_in_photo' => $this->storeCapturedPhoto($payload['photo'], 'attendance/clock-in'),
            'clock_in_location' => $this->formatLocation($payload['latitude'], $payload['longitude']),
        ]);
        $attendance->save();

        return back()->with('success', 'Absen masuk berhasil disimpan.');
    }

    public function clockOut(Request $request): RedirectResponse
    {
        $user = $this->ensureEmployee($request);
        $payload = $this->validatePresencePayload($request);
        $setting = $this->getAttendanceSetting();

        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', Carbon::today()->toDateString())
            ->first();

        if (! $attendance || ! $attendance->clock_in_at) {
            throw ValidationException::withMessages([
                'attendance' => 'Absen pulang hanya bisa dilakukan setelah absen masuk.',
            ]);
        }

        if ($attendance->clock_out_at) {
            throw ValidationException::withMessages([
                'attendance' => 'Anda sudah melakukan absen pulang hari ini.',
            ]);
        }

        $this->assertWithinOfficeRadius($payload['latitude'], $payload['longitude'], $setting);

        $attendance->update([
            'clock_out_at' => now()->format('H:i:s'),
            'clock_out_photo' => $this->storeCapturedPhoto($payload['photo'], 'attendance/clock-out'),
            'clock_out_location' => $this->formatLocation($payload['latitude'], $payload['longitude']),
        ]);

        return back()->with('success', 'Absen pulang berhasil disimpan.');
    }

    public function storeOvertime(Request $request): RedirectResponse
    {
        $user = $this->ensureEmployee($request);

        $validated = $request->validate([
            'overtime_date' => ['required', 'date', 'after_or_equal:today'],
            'planned_start' => ['required', 'date_format:H:i'],
            'planned_end' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($validated['planned_end'] <= $validated['planned_start']) {
            throw ValidationException::withMessages([
                'planned_end' => 'Jam selesai harus setelah jam mulai pada hari yang sama.',
            ]);
        }

        Overtime::query()->create([
            'user_id' => $user->id,
            'overtime_date' => $validated['overtime_date'],
            'planned_start' => $validated['planned_start'],
            'planned_end' => $validated['planned_end'],
            'reason' => $validated['reason'],
            'approval_status' => 'Pending',
            'approved_by' => null,
            'actual_start' => null,
            'overtime_start_photo' => null,
            'actual_end' => null,
            'overtime_end_photo' => null,
        ]);

        return back()->with('success', 'Pengajuan lembur berhasil dikirim dan menunggu approval admin.');
    }

    public function startOvertime(Request $request, Overtime $overtime): RedirectResponse
    {
        $user = $this->ensureEmployee($request);
        $this->assertOvertimeOwnership($overtime, $user->id);

        $payload = $this->validatePresencePayload($request);
        $setting = $this->getAttendanceSetting();

        if ($overtime->approval_status !== 'Approved') {
            throw ValidationException::withMessages([
                'overtime' => 'Presensi lembur hanya tersedia untuk pengajuan yang sudah disetujui.',
            ]);
        }

        if ($overtime->overtime_date !== Carbon::today()->toDateString()) {
            throw ValidationException::withMessages([
                'overtime' => 'Absen mulai lembur hanya bisa dilakukan pada tanggal lembur yang disetujui.',
            ]);
        }

        if ($overtime->actual_start) {
            throw ValidationException::withMessages([
                'overtime' => 'Anda sudah melakukan absen mulai lembur.',
            ]);
        }

        $this->assertWithinOfficeRadius($payload['latitude'], $payload['longitude'], $setting);

        $overtime->update([
            'actual_start' => now()->format('H:i:s'),
            'overtime_start_photo' => $this->storeCapturedPhoto($payload['photo'], 'overtime/start'),
        ]);

        return back()->with('success', 'Absen mulai lembur berhasil disimpan.');
    }

    public function finishOvertime(Request $request, Overtime $overtime): RedirectResponse
    {
        $user = $this->ensureEmployee($request);
        $this->assertOvertimeOwnership($overtime, $user->id);

        $payload = $this->validatePresencePayload($request);
        $setting = $this->getAttendanceSetting();

        if ($overtime->approval_status !== 'Approved') {
            throw ValidationException::withMessages([
                'overtime' => 'Presensi lembur hanya tersedia untuk pengajuan yang sudah disetujui.',
            ]);
        }

        if ($overtime->overtime_date !== Carbon::today()->toDateString()) {
            throw ValidationException::withMessages([
                'overtime' => 'Absen selesai lembur hanya bisa dilakukan pada tanggal lembur yang disetujui.',
            ]);
        }

        if (! $overtime->actual_start) {
            throw ValidationException::withMessages([
                'overtime' => 'Absen selesai lembur hanya bisa dilakukan setelah absen mulai lembur.',
            ]);
        }

        if ($overtime->actual_end) {
            throw ValidationException::withMessages([
                'overtime' => 'Anda sudah melakukan absen selesai lembur.',
            ]);
        }

        $this->assertWithinOfficeRadius($payload['latitude'], $payload['longitude'], $setting);

        $overtime->update([
            'actual_end' => now()->format('H:i:s'),
            'overtime_end_photo' => $this->storeCapturedPhoto($payload['photo'], 'overtime/end'),
        ]);

        return back()->with('success', 'Absen selesai lembur berhasil disimpan.');
    }

    private function ensureEmployee(Request $request)
    {
        $user = $request->user();

        abort_unless($user && $user->role === 'Employee', 403);

        return $user;
    }

    private function validatePresencePayload(Request $request): array
    {
        return $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['required', 'string'],
        ]);
    }

    private function getAttendanceSetting(): Setting
    {
        $setting = Setting::query()->latest('id')->first();

        if (! $setting || blank($setting->latitude) || blank($setting->longitude)) {
            throw ValidationException::withMessages([
                'setting' => 'Lokasi kantor belum dikonfigurasi admin, jadi presensi belum bisa digunakan.',
            ]);
        }

        return $setting;
    }

    private function assertWithinOfficeRadius(float $latitude, float $longitude, Setting $setting): void
    {
        $officeLatitude = (float) $setting->latitude;
        $officeLongitude = (float) $setting->longitude;
        $distance = $this->calculateDistanceInMeters($officeLatitude, $officeLongitude, $latitude, $longitude);

        if ($distance > (float) $setting->radius_meters) {
            throw ValidationException::withMessages([
                'location' => 'Lokasi Anda berada di luar radius kantor. Presensi ditolak.',
            ]);
        }
    }

    private function calculateDistanceInMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $latFrom = deg2rad($lat1);
        $latTo = deg2rad($lat2);
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $angle = 2 * asin(sqrt(
            sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2
        ));

        return $angle * $earthRadius;
    }

    private function storeCapturedPhoto(string $photoDataUrl, string $directory): string
    {
        if (! preg_match('/^data:image\/(png|jpe?g|webp);base64,(.+)$/', $photoDataUrl, $matches)) {
            throw ValidationException::withMessages([
                'photo' => 'Foto wajah wajib diambil langsung dari kamera.',
            ]);
        }

        $binary = base64_decode(str_replace(' ', '+', $matches[2]), true);

        if ($binary === false) {
            throw ValidationException::withMessages([
                'photo' => 'Format foto tidak valid.',
            ]);
        }

        if (strlen($binary) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'photo' => 'Ukuran foto terlalu besar. Coba ambil ulang foto yang lebih ringan.',
            ]);
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $path = $directory.'/'.Carbon::now()->format('Y/m/d').'/'.Str::uuid().'.'.$extension;

        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function formatLocation(float $latitude, float $longitude): string
    {
        return number_format($latitude, 6, '.', '').','.number_format($longitude, 6, '.', '');
    }

    private function assertOvertimeOwnership(Overtime $overtime, int $userId): void
    {
        abort_unless($overtime->user_id === $userId, 404);
    }
}
