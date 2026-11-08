<?php

use App\Enums\RfcStatus;
use App\Livewire\Notifications;
use App\Livewire\Profile;
use App\Livewire\Requester\Dashboard;
use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders requester dashboard shell', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso']);
    ChangeRequest::factory()->count(3)->create([
        'submitted_by_id' => $user->id,
        'created_by_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get('/requester/dashboard')
        ->assertOk()
        ->assertSee('Dashboard Pemohon')
        ->assertSee('Ajukan RFC Baru')
        ->assertSee('RFC Terbaru Saya')
        ->assertSee('Status Permohonan Saya')
        ->assertSee('Notifikasi')
        ->assertSee('Budi Santoso');
});

it('renders notifications and profile pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('Notifikasi');
    $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Profil Saya');

    Livewire::actingAs($user)->test(Notifications::class)->assertOk();
    Livewire::actingAs($user)->test(Profile::class)->assertOk();
});

it('renders dashboard timeline states for active rfc', function () {
    $user = User::factory()->create();
    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $user->id,
        'created_by_id' => $user->id,
        'status' => RfcStatus::APPROVED->value,
        'rfc_number' => 'RFC-2026-9001',
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertOk()
        ->assertSee('RFC-2026-9001')
        ->assertSee('Dikerjakan')
        ->assertSee('Ditinjau Change Manager');
});
