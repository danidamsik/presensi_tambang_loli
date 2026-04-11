<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Support\PublicFileUrl;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->role === 'Admin') {
            return redirect()->route('dashboard');
        }

        $leaveRequests = LeaveRequest::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('leave_date')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Employee/Leaves', [
            'leaveRequests' => $leaveRequests->through(fn (LeaveRequest $leaveRequest): array => [
                'id' => $leaveRequest->id,
                'leave_date' => $leaveRequest->leave_date,
                'leave_type' => $leaveRequest->leave_type,
                'reason' => $leaveRequest->reason,
                'proof_photo' => PublicFileUrl::make($leaveRequest->proof_photo),
                'approval_status' => $leaveRequest->approval_status,
                'created_at' => optional($leaveRequest->created_at)->toDateTimeString(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role === 'Employee', 403);

        $validated = $request->validate([
            'leave_date' => ['required', 'date', 'after_or_equal:today'],
            'leave_type' => ['required', 'in:Sakit,Izin'],
            'reason' => ['required', 'string', 'max:1000'],
            'proof_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        LeaveRequest::query()->create([
            'user_id' => $request->user()->id,
            'leave_date' => Carbon::parse($validated['leave_date'])->toDateString(),
            'leave_type' => $validated['leave_type'],
            'reason' => $validated['reason'],
            'proof_photo' => $request->file('proof_photo')->store('leave-requests', 'public'),
            'approval_status' => 'Pending',
            'approved_by' => null,
        ]);

        return back()->with('success', 'Pengajuan izin berhasil dikirim dan menunggu approval admin.');
    }
}
