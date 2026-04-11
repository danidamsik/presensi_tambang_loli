<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Overtime;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_employee_can_access_dashboard_attendance_and_overtime_pages_then_handle_their_flows(): void
    {
        Storage::fake('public');

        $employee = $this->createEmployee();

        Setting::query()->create([
            'latitude' => '-6.200000',
            'longitude' => '106.816000',
            'radius_meters' => 150,
            'check_in_time' => '08:00:00',
            'check_out_time' => '17:00:00',
        ]);

        Carbon::setTestNow('2026-04-11 08:10:00');

        $homeResponse = $this->actingAs($employee)->get(route('home'));
        $homeResponse->assertOk();
        $homeResponse->assertInertia(fn (Assert $page) => $page->component('Employee/Home'));

        $attendancePageResponse = $this->actingAs($employee)->get(route('employee.attendance.index'));
        $attendancePageResponse->assertOk();
        $attendancePageResponse->assertInertia(fn (Assert $page) => $page->component('Employee/Attendance'));

        $overtimePageResponse = $this->actingAs($employee)->get(route('employee.overtimes.index'));
        $overtimePageResponse->assertOk();
        $overtimePageResponse->assertInertia(fn (Assert $page) => $page->component('Employee/Overtimes'));

        $clockInResponse = $this->actingAs($employee)->post(route('employee.attendance.clock-in'), [
            'latitude' => -6.20001,
            'longitude' => 106.81601,
            'photo' => $this->fakePhotoDataUrl(),
        ]);
        $clockInResponse->assertRedirect();

        $attendance = Attendance::query()->where('user_id', $employee->id)->firstOrFail();
        $this->assertNotNull($attendance->clock_in_at);
        $this->assertSame('-6.200010,106.816010', $attendance->clock_in_location);
        Storage::disk('public')->assertExists($attendance->clock_in_photo);

        Carbon::setTestNow('2026-04-11 17:05:00');

        $clockOutResponse = $this->actingAs($employee)->post(route('employee.attendance.clock-out'), [
            'latitude' => -6.20002,
            'longitude' => 106.81602,
            'photo' => $this->fakePhotoDataUrl(),
        ]);
        $clockOutResponse->assertRedirect();

        $attendance->refresh();
        $this->assertNotNull($attendance->clock_out_at);
        $this->assertSame('-6.200020,106.816020', $attendance->clock_out_location);
        Storage::disk('public')->assertExists($attendance->clock_out_photo);

        $overtimeRequestResponse = $this->actingAs($employee)->post(route('employee.overtimes.store'), [
            'overtime_date' => now()->addDay()->toDateString(),
            'planned_start' => '18:00',
            'planned_end' => '20:00',
            'reason' => 'Closing laporan harian.',
            'request_photo' => $this->fakePhotoDataUrl(),
        ]);
        $overtimeRequestResponse->assertRedirect();

        $this->assertDatabaseHas('overtimes', [
            'user_id' => $employee->id,
            'approval_status' => 'Pending',
            'reason' => 'Closing laporan harian.',
        ]);

        $approvedOvertime = Overtime::query()->create([
            'user_id' => $employee->id,
            'overtime_date' => now()->toDateString(),
            'planned_start' => '18:00:00',
            'planned_end' => '20:00:00',
            'reason' => 'Support operasional lapangan',
            'approval_status' => 'Approved',
        ]);

        $startResponse = $this->actingAs($employee)->post(route('employee.overtimes.start', $approvedOvertime), [
            'latitude' => -6.20003,
            'longitude' => 106.81603,
            'photo' => $this->fakePhotoDataUrl(),
        ]);
        $startResponse->assertRedirect();

        $approvedOvertime->refresh();
        $this->assertNotNull($approvedOvertime->actual_start);
        Storage::disk('public')->assertExists($approvedOvertime->overtime_start_photo);

        $finishResponse = $this->actingAs($employee)->post(route('employee.overtimes.finish', $approvedOvertime), [
            'latitude' => -6.20004,
            'longitude' => 106.81604,
            'photo' => $this->fakePhotoDataUrl(),
        ]);
        $finishResponse->assertRedirect();

        $approvedOvertime->refresh();
        $this->assertNotNull($approvedOvertime->actual_end);
        Storage::disk('public')->assertExists($approvedOvertime->overtime_end_photo);
    }

    public function test_employee_attendance_is_rejected_when_outside_office_radius(): void
    {
        Storage::fake('public');

        $employee = $this->createEmployee();

        Setting::query()->create([
            'latitude' => '-6.200000',
            'longitude' => '106.816000',
            'radius_meters' => 100,
            'check_in_time' => '08:00:00',
        ]);

        Carbon::setTestNow('2026-04-11 08:10:00');

        $response = $this->actingAs($employee)->from(route('employee.attendance.index'))->post(route('employee.attendance.clock-in'), [
            'latitude' => -6.210000,
            'longitude' => 106.826000,
            'photo' => $this->fakePhotoDataUrl(),
        ]);

        $response->assertRedirect(route('employee.attendance.index'));
        $response->assertSessionHasErrors('location');
        $this->assertDatabaseCount('attendances', 0);
    }

    private function createEmployee(): User
    {
        return User::query()->create([
            'id_number' => '1000000000000099',
            'full_name' => 'Employee Test',
            'email' => 'employee.feature@example.com',
            'password' => Hash::make('password'),
            'role' => 'Employee',
        ]);
    }

    private function fakePhotoDataUrl(): string
    {
        return 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WnR2c8AAAAASUVORK5CYII=', true));
    }
}
