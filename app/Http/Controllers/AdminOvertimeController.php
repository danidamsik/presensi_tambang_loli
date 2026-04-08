<?php

namespace App\Http\Controllers;

use App\Models\Overtime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminOvertimeController extends Controller
{
    public function index(Request $request): Response
    {
        $dateFrom = (string) $request->string('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo = (string) $request->string('date_to', Carbon::now()->toDateString());
        $status = (string) $request->string('status', 'all');
        $employeeId = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;

        $query = Overtime::query()
            ->with([
                'user:id,full_name,id_number',
                'approver:id,full_name',
            ])
            ->whereBetween('overtime_date', [$dateFrom, $dateTo])
            ->whereHas('user', fn ($userQuery) => $userQuery->where('role', 'Employee'));

        if ($status !== 'all') {
            $query->where('approval_status', $status);
        }

        if ($employeeId) {
            $query->where('user_id', $employeeId);
        }

        $totalRequests = (clone $query)->count();
        $pendingRequests = (clone $query)->where('approval_status', 'Pending')->count();
        $approvedRequests = (clone $query)->where('approval_status', 'Approved')->count();
        $rejectedRequests = (clone $query)->where('approval_status', 'Rejected')->count();

        $approvedHours = (clone $query)
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

        $overtimes = (clone $query)
            ->orderByDesc('overtime_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('AdminOvertimes', [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'status' => $status,
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
                'totalRequests' => $totalRequests,
                'pendingRequests' => $pendingRequests,
                'approvedRequests' => $approvedRequests,
                'rejectedRequests' => $rejectedRequests,
                'approvedHours' => round($approvedHours, 1),
            ],
            'overtimes' => $overtimes->through(function (Overtime $overtime): array {
                return [
                    'id' => $overtime->id,
                    'overtime_date' => $overtime->overtime_date,
                    'employee_name' => $overtime->user?->full_name ?? '-',
                    'id_number' => $overtime->user?->id_number,
                    'planned_start' => $overtime->planned_start,
                    'planned_end' => $overtime->planned_end,
                    'reason' => $overtime->reason,
                    'approval_status' => $overtime->approval_status,
                    'approved_by' => $overtime->approver?->full_name,
                    'actual_start' => $overtime->actual_start,
                    'actual_end' => $overtime->actual_end,
                    'overtime_start_photo' => $overtime->overtime_start_photo,
                    'overtime_end_photo' => $overtime->overtime_end_photo,
                ];
            }),
        ]);
    }

    public function approve(Request $request, Overtime $overtime): RedirectResponse
    {
        if ($overtime->approval_status !== 'Pending') {
            return back()->with('error', 'Pengajuan lembur sudah diproses sebelumnya.');
        }

        $overtime->update([
            'approval_status' => 'Approved',
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Lembur berhasil disetujui.');
    }

    public function reject(Request $request, Overtime $overtime): RedirectResponse
    {
        if ($overtime->approval_status !== 'Pending') {
            return back()->with('error', 'Pengajuan lembur sudah diproses sebelumnya.');
        }

        $overtime->update([
            'approval_status' => 'Rejected',
            'approved_by' => $request->user()->id,
            'actual_start' => null,
            'overtime_start_photo' => null,
            'actual_end' => null,
            'overtime_end_photo' => null,
        ]);

        return back()->with('success', 'Lembur berhasil ditolak.');
    }
}
