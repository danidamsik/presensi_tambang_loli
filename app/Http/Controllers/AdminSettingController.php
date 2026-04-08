<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingController extends Controller
{
    public function index(): Response
    {
        $setting = Setting::query()->latest('id')->first();

        return Inertia::render('AdminSettings', [
            'setting' => [
                'latitude' => $setting?->latitude,
                'longitude' => $setting?->longitude,
                'radius_meters' => $setting?->radius_meters ?? 100,
                'check_in_time' => $setting?->check_in_time ? substr($setting->check_in_time, 0, 5) : null,
                'check_out_time' => $setting?->check_out_time ? substr($setting->check_out_time, 0, 5) : null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:1', 'max:50000'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
        ]);

        $payload = [
            'latitude' => isset($validated['latitude']) ? (string) $validated['latitude'] : null,
            'longitude' => isset($validated['longitude']) ? (string) $validated['longitude'] : null,
            'radius_meters' => $validated['radius_meters'],
            'check_in_time' => $validated['check_in_time'] ?? null,
            'check_out_time' => $validated['check_out_time'] ?? null,
        ];

        $setting = Setting::query()->latest('id')->first();

        if ($setting) {
            $setting->update($payload);
        } else {
            Setting::query()->create($payload);
        }

        return back()->with('success', 'Pengaturan lokasi dan jam kerja berhasil disimpan.');
    }
}
