<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Overtime;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminReportController extends Controller
{
    public function index(Request $request): Response
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $employeeId = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;

        $attendanceQuery = Attendance::query()
            ->with('user:id,full_name,id_number')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereHas('user', fn ($userQuery) => $userQuery->where('role', 'Employee'));

        if ($employeeId) {
            $attendanceQuery->where('user_id', $employeeId);
        }

        $overtimeQuery = Overtime::query()
            ->with(['user:id,full_name,id_number', 'approver:id,full_name'])
            ->whereBetween('overtime_date', [$dateFrom, $dateTo])
            ->whereHas('user', fn ($userQuery) => $userQuery->where('role', 'Employee'));

        if ($employeeId) {
            $overtimeQuery->where('user_id', $employeeId);
        }

        $setting = Setting::query()->latest('id')->first();
        $lateThreshold = $setting?->check_in_time;

        $attendanceRecords = (clone $attendanceQuery)->count();
        $attendanceEmployees = (clone $attendanceQuery)->distinct()->count('user_id');
        $completedAttendance = (clone $attendanceQuery)->whereNotNull('clock_out_at')->count();
        $lateAttendance = $lateThreshold
            ? (clone $attendanceQuery)->whereNotNull('clock_in_at')->where('clock_in_at', '>', $lateThreshold)->count()
            : 0;

        $overtimeRecords = (clone $overtimeQuery)->count();
        $overtimePending = (clone $overtimeQuery)->where('approval_status', 'Pending')->count();
        $overtimeApproved = (clone $overtimeQuery)->where('approval_status', 'Approved')->count();
        $overtimeRejected = (clone $overtimeQuery)->where('approval_status', 'Rejected')->count();

        $approvedHours = (clone $overtimeQuery)
            ->where('approval_status', 'Approved')
            ->whereNotNull('actual_start')
            ->whereNotNull('actual_end')
            ->get(['actual_start', 'actual_end'])
            ->sum(function (Overtime $overtime): float {
                $start = Carbon::createFromFormat('H:i:s', $overtime->actual_start);
                $end = Carbon::createFromFormat('H:i:s', $overtime->actual_end);

                if ($end->lessThan($start)) {
                    $end->addDay();
                }

                return $start->diffInMinutes($end) / 60;
            });

        $attendanceByEmployee = (clone $attendanceQuery)
            ->get()
            ->groupBy('user_id')
            ->map(function (Collection $rows): array {
                /** @var Attendance $sample */
                $sample = $rows->first();
                $firstAttendance = $rows->min('date');
                $lastAttendance = $rows->max('date');

                return [
                    'employee_name' => $sample->user?->full_name ?? '-',
                    'id_number' => $sample->user?->id_number,
                    'attendance_days' => $rows->count(),
                    'completed_days' => $rows->whereNotNull('clock_out_at')->count(),
                    'first_attendance' => $firstAttendance ? (string) $firstAttendance : null,
                    'last_attendance' => $lastAttendance ? (string) $lastAttendance : null,
                ];
            })
            ->sortByDesc('attendance_days')
            ->values();

        $overtimeByEmployee = (clone $overtimeQuery)
            ->get()
            ->groupBy('user_id')
            ->map(function (Collection $rows): array {
                /** @var Overtime $sample */
                $sample = $rows->first();

                $hours = $rows
                    ->where('approval_status', 'Approved')
                    ->whereNotNull('actual_start')
                    ->whereNotNull('actual_end')
                    ->sum(function (Overtime $overtime): float {
                        $start = Carbon::createFromFormat('H:i:s', $overtime->actual_start);
                        $end = Carbon::createFromFormat('H:i:s', $overtime->actual_end);

                        if ($end->lessThan($start)) {
                            $end->addDay();
                        }

                        return $start->diffInMinutes($end) / 60;
                    });

                return [
                    'employee_name' => $sample->user?->full_name ?? '-',
                    'id_number' => $sample->user?->id_number,
                    'total_requests' => $rows->count(),
                    'pending' => $rows->where('approval_status', 'Pending')->count(),
                    'approved' => $rows->where('approval_status', 'Approved')->count(),
                    'rejected' => $rows->where('approval_status', 'Rejected')->count(),
                    'approved_hours' => round($hours, 1),
                ];
            })
            ->sortByDesc('total_requests')
            ->values();

        $attendanceDetails = (clone $attendanceQuery)
            ->orderByDesc('date')
            ->orderByDesc('clock_in_at')
            ->limit(100)
            ->get()
            ->map(function (Attendance $attendance): array {
                return [
                    'date' => $attendance->date,
                    'employee_name' => $attendance->user?->full_name ?? '-',
                    'id_number' => $attendance->user?->id_number,
                    'clock_in_at' => $attendance->clock_in_at,
                    'clock_out_at' => $attendance->clock_out_at,
                    'clock_in_location' => $attendance->clock_in_location,
                    'clock_out_location' => $attendance->clock_out_location,
                ];
            });

        $overtimeDetails = (clone $overtimeQuery)
            ->orderByDesc('overtime_date')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function (Overtime $overtime): array {
                return [
                    'overtime_date' => $overtime->overtime_date,
                    'employee_name' => $overtime->user?->full_name ?? '-',
                    'id_number' => $overtime->user?->id_number,
                    'approval_status' => $overtime->approval_status,
                    'planned_start' => $overtime->planned_start,
                    'planned_end' => $overtime->planned_end,
                    'actual_start' => $overtime->actual_start,
                    'actual_end' => $overtime->actual_end,
                    'approved_by' => $overtime->approver?->full_name,
                ];
            });

        return Inertia::render('AdminReports', [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'employee_id' => $employeeId,
            ],
            'employees' => User::query()
                ->where('role', 'Employee')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'id_number'])
                ->map(fn (User $employee): array => [
                    'id' => $employee->id,
                    'full_name' => $employee->full_name,
                    'id_number' => $employee->id_number,
                ]),
            'summary' => [
                'totalEmployees' => User::query()->where('role', 'Employee')->count(),
                'attendanceRecords' => $attendanceRecords,
                'attendanceEmployees' => $attendanceEmployees,
                'completedAttendance' => $completedAttendance,
                'lateAttendance' => $lateAttendance,
                'overtimeRecords' => $overtimeRecords,
                'overtimePending' => $overtimePending,
                'overtimeApproved' => $overtimeApproved,
                'overtimeRejected' => $overtimeRejected,
                'approvedHours' => round($approvedHours, 1),
            ],
            'attendanceByEmployee' => $attendanceByEmployee,
            'overtimeByEmployee' => $overtimeByEmployee,
            'attendanceDetails' => $attendanceDetails,
            'overtimeDetails' => $overtimeDetails,
        ]);
    }

    public function attendanceCsv(Request $request): StreamedResponse
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $employeeId = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;

        $rows = Attendance::query()
            ->with('user:id,full_name,id_number')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereHas('user', fn ($userQuery) => $userQuery->where('role', 'Employee'))
            ->when($employeeId, fn ($query) => $query->where('user_id', $employeeId))
            ->orderBy('date')
            ->orderBy('user_id')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'Tanggal',
                'ID Number',
                'Nama Karyawan',
                'Clock In',
                'Clock Out',
                'Lokasi Clock In',
                'Lokasi Clock Out',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->date,
                    $row->user?->id_number,
                    $row->user?->full_name,
                    $row->clock_in_at,
                    $row->clock_out_at,
                    $row->clock_in_location,
                    $row->clock_out_location,
                ]);
            }

            fclose($handle);
        }, sprintf('laporan-presensi-%s-sd-%s.csv', $dateFrom, $dateTo), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function overtimeCsv(Request $request): StreamedResponse
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $employeeId = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;

        $rows = Overtime::query()
            ->with(['user:id,full_name,id_number', 'approver:id,full_name'])
            ->whereBetween('overtime_date', [$dateFrom, $dateTo])
            ->whereHas('user', fn ($userQuery) => $userQuery->where('role', 'Employee'))
            ->when($employeeId, fn ($query) => $query->where('user_id', $employeeId))
            ->orderBy('overtime_date')
            ->orderBy('user_id')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'Tanggal Lembur',
                'ID Number',
                'Nama Karyawan',
                'Planned Start',
                'Planned End',
                'Actual Start',
                'Actual End',
                'Status',
                'Approver',
                'Alasan',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->overtime_date,
                    $row->user?->id_number,
                    $row->user?->full_name,
                    $row->planned_start,
                    $row->planned_end,
                    $row->actual_start,
                    $row->actual_end,
                    $row->approval_status,
                    $row->approver?->full_name,
                    $row->reason,
                ]);
            }

            fclose($handle);
        }, sprintf('laporan-lembur-%s-sd-%s.csv', $dateFrom, $dateTo), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveDateRange(Request $request): array
    {
        $defaultFrom = Carbon::now()->startOfMonth()->toDateString();
        $defaultTo = Carbon::now()->toDateString();

        $dateFrom = (string) $request->string('date_from', $defaultFrom);
        $dateTo = (string) $request->string('date_to', $defaultTo);

        return [$dateFrom, $dateTo];
    }
}
