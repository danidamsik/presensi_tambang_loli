<?php

use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminEmployeeController;
use App\Http\Controllers\AdminOvertimeController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminSettingController;
use App\Http\Controllers\EmployeeHomeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/admin/employees', [AdminEmployeeController::class, 'index'])->name('admin.employees.index');
    Route::post('/admin/employees', [AdminEmployeeController::class, 'store'])->name('admin.employees.store');
    Route::patch('/admin/employees/{employee}', [AdminEmployeeController::class, 'update'])->name('admin.employees.update');
    Route::delete('/admin/employees/{employee}', [AdminEmployeeController::class, 'destroy'])->name('admin.employees.destroy');

    Route::get('/admin/settings', [AdminSettingController::class, 'index'])->name('admin.settings.index');
    Route::put('/admin/settings', [AdminSettingController::class, 'update'])->name('admin.settings.update');

    Route::get('/admin/attendances', [AdminAttendanceController::class, 'index'])->name('admin.attendances.index');

    Route::get('/admin/overtimes', [AdminOvertimeController::class, 'index'])->name('admin.overtimes.index');
    Route::patch('/admin/overtimes/{overtime}/approve', [AdminOvertimeController::class, 'approve'])
        ->name('admin.overtimes.approve');
    Route::patch('/admin/overtimes/{overtime}/reject', [AdminOvertimeController::class, 'reject'])
        ->name('admin.overtimes.reject');

    Route::get('/admin/reports', [AdminReportController::class, 'index'])->name('admin.reports.index');
    Route::get('/admin/reports/attendance.csv', [AdminReportController::class, 'attendanceCsv'])
        ->name('admin.reports.attendance.csv');
    Route::get('/admin/reports/overtime.csv', [AdminReportController::class, 'overtimeCsv'])
        ->name('admin.reports.overtime.csv');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/home', [EmployeeHomeController::class, 'index'])->name('home');
    Route::post('/employee/attendance/clock-in', [EmployeeHomeController::class, 'clockIn'])
        ->name('employee.attendance.clock-in');
    Route::post('/employee/attendance/clock-out', [EmployeeHomeController::class, 'clockOut'])
        ->name('employee.attendance.clock-out');
    Route::post('/employee/overtimes', [EmployeeHomeController::class, 'storeOvertime'])
        ->name('employee.overtimes.store');
    Route::post('/employee/overtimes/{overtime}/start', [EmployeeHomeController::class, 'startOvertime'])
        ->name('employee.overtimes.start');
    Route::post('/employee/overtimes/{overtime}/finish', [EmployeeHomeController::class, 'finishOvertime'])
        ->name('employee.overtimes.finish');
});

require __DIR__.'/auth.php';
