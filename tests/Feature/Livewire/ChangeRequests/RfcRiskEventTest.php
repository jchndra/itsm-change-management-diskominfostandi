<?php

use App\Enums\RfcStatus;
use App\Enums\RiskEvent;
use App\Livewire\ChangeRequests\CreateChangeRequest;
use App\Livewire\ChangeRequests\EditChangeRequest;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Storage::fake('local');
    Storage::fake('public');

    $this->pemohon = User::factory()->create([
        'name' => 'Pemohon Risiko',
        'position' => 'Staf Operasional',
        'phone_number' => '081234567890',
    ]);
    $this->pemohon->assignRole('Pemohon');

    $this->application = Application::factory()->create();

    $this->actingAs($this->pemohon);
});

it('menyimpan kejadian risiko terpilih saat membuat RFC dan menampilkannya di detail', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pembaruan Kanal Dukungan Digital')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Kanal dukungan diperbarui untuk mempercepat tanggapan insiden.')
        ->set('risk_event', RiskEvent::CYBER_INCIDENT->value)
        ->set('reason_for_change', 'Kejadian risiko ini wajib ditangani karena memengaruhi layanan prioritas.')
        ->set('legal_basis', 'Kebijakan penanganan insiden siber pada layanan digital.')
        ->set('change_risk', 'Insiden siber dapat mengganggu kanal dukungan digital.')
        ->set('head_of_department_approved', true)
        ->set('attachments', [UploadedFile::fake()->create('surat-persetujuan.pdf', 100, 'application/pdf')])
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasNoErrors();

    $rfc = ChangeRequest::latest('id')->firstOrFail();

    expect($rfc->risk_event)->toBe(RiskEvent::CYBER_INCIDENT);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertSee('Register Risiko')
        ->assertSee($rfc->risk_event->optionLabel())
        ->assertSee(asset('images/register-risiko-indikatif.png'), false);
});
it('menampilkan semua 12 kejadian risiko register pada form create', function () {
    $html = Livewire::test(CreateChangeRequest::class)->html();

    foreach (RiskEvent::cases() as $riskEvent) {
        expect($html)->toContain('value="'.$riskEvent->value.'"')
            ->and($html)->toContain($riskEvent->optionLabel());
    }
});

it('mengizinkan register risiko kosong saat menyimpan RFC', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pembaruan Kanal Dukungan Digital')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Kanal dukungan diperbarui untuk mempercepat tanggapan insiden.')
        ->set('risk_event', '')
        ->set('reason_for_change', 'Register risiko bersifat opsional pada form pengajuan.')
        ->set('legal_basis', 'Kebijakan penanganan insiden siber pada layanan digital.')
        ->set('change_risk', 'Insiden siber dapat mengganggu kanal dukungan digital.')
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasNoErrors();

    $rfc = ChangeRequest::latest('id')->firstOrFail();

    expect($rfc->risk_event)->toBeNull()
        ->and($rfc->status)->toBe(RfcStatus::DRAFT->value);
});

it('menolak kode kejadian risiko yang tidak terdaftar di register', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Penggantian Konfigurasi Tidak Terdaftar')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Penggantian mekanisme alokasi sumber daya untuk layanan digital.')
        ->set('risk_event', 'R99')
        ->set('reason_for_change', 'Kode di luar register tidak boleh tersimpan pada catatan RFC.')
        ->set('legal_basis', 'Kebijakan register risiko indikatif tingkat kota.')
        ->set('change_risk', 'Kode risiko yang tidak terdaftar dapat menyulitkan pelaporan.')
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasErrors(['risk_event' => 'Register risiko tidak valid.']);
});

it('mempertahankan kejadian risiko saat form edit dibuka dan disimpan', function () {
    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $this->pemohon->id,
        'created_by_id' => $this->pemohon->id,
        'application_id' => $this->application->id,
        'status' => RfcStatus::DRAFT->value,
        'risk_event' => RiskEvent::DATACENTER_DISRUPTION,
    ]);

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
        ->assertSet('risk_event', RiskEvent::DATACENTER_DISRUPTION->value);

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
        ->set('risk_event', RiskEvent::SATISFACTION_SURVEY->value)
        ->call('saveChanges')
        ->assertHasNoErrors();

    expect($rfc->fresh()->risk_event)->toBe(RiskEvent::SATISFACTION_SURVEY);
});
