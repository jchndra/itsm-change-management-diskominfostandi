<?php

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Enums\ServiceCategory;
use App\Enums\TicketPriority;
use App\Livewire\ChangeRequests\ReviewCompleteness;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Livewire\Notifications;
use App\Models\Application;
use App\Models\Authority;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestAttachment;
use App\Models\Decision;
use App\Models\Opd;
use App\Models\User;
use App\Notifications\ChangeManagerTicketNotification;
use App\Notifications\RfcStatusChangedNotification;
use App\Services\RfcHistoryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->opd = Opd::factory()->create(['name' => 'Dinas Uji Tiket']);
    $this->pemohon = User::factory()->create([
        'name' => 'Pemohon Tiket',
        'opd_id' => $this->opd->id,
    ]);
    $this->pemohon->assignRole('Pemohon');

    $this->admin = User::factory()->create(['name' => 'Admin Tiket']);
    $this->admin->assignRole('Admin');

    $this->cm = User::factory()->create(['name' => 'Change Manager Tiket']);
    $this->cm->assignRole('Change Manager');

    $this->application = Application::factory()->create([
        'name' => 'Aplikasi Uji Tiket',
        'opd_id' => $this->opd->id,
    ]);

    $this->makeRfc = fn (array $attributes = []): ChangeRequest => ChangeRequest::factory()->create(array_merge([
        'submitted_by_id' => $this->pemohon->id,
        'created_by_id' => $this->pemohon->id,
        'application_id' => $this->application->id,
        'status' => RfcStatus::SUBMITTED->value,
        'change_type' => 'normal',
        'title' => 'Perubahan Aplikasi Uji',
    ], $attributes));

    $this->fillTicketForm = function ($component, array $overrides = []): void {
        $values = array_merge([
            'priority' => TicketPriority::HIGH->value,
            'category' => ServiceCategory::SECURITY->value,
            'admin_note' => 'Catatan untuk Change Manager dari pengujian.',
            'data_complete' => true,
            'attachments_verified' => true,
            'application_verified' => true,
        ], $overrides);

        foreach ($values as $property => $value) {
            $component->set($property, $value);
        }
    };
});

test('admin membuat tiket change dari RFC submitted dan menyimpan metadata tiket', function () {
    $this->actingAs($this->admin);

    $rfc = ($this->makeRfc)();
    $year = now()->format('Y');
    $admin_note = 'Dokumen lengkap, mohon periksa dampak keamanan.';

    $component = Livewire::test(ReviewCompleteness::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('Buat Tiket Change')
        ->assertSee('Data RFC sudah lengkap')
        ->assertSee('Lampiran dan nota dinas sudah sesuai')
        ->assertSee('Sistem/aplikasi sudah sesuai');

    ($this->fillTicketForm)($component, ['admin_note' => $admin_note]);

    $component->call('createTicket')
        ->assertHasNoErrors()
        ->assertRedirect(route('change-requests.index'));

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($fresh->ticket_number)->toBe("CHG-{$year}-0001")
        ->and($fresh->priority)->toBe(TicketPriority::HIGH)
        ->and($fresh->category)->toBe(ServiceCategory::SECURITY)
        ->and($fresh->admin_note)->toBe($admin_note)
        ->and($fresh->ticket_created_by_id)->toBe($this->admin->id)
        ->and($fresh->ticket_created_at)->not->toBeNull();

    expect(session('message'))->toBe("Tiket Change {$fresh->ticket_number} dibuat dan dikirim ke Change Manager.");

    $activity = Activity::query()
        ->where('subject_type', $fresh->getMorphClass())
        ->where('subject_id', $fresh->id)
        ->get()
        ->first(fn (Activity $item): bool => data_get($item->properties, 'event') === 'ticket_created');

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and(data_get($activity->properties, 'ticket_number'))->toBe($fresh->ticket_number)
        ->and(data_get($activity->properties, 'priority'))->toBe(TicketPriority::HIGH->value)
        ->and(data_get($activity->properties, 'category'))->toBe(ServiceCategory::SECURITY->value)
        ->and(data_get($activity->properties, 'checklist.data_rfc_lengkap'))->toBeTrue()
        ->and(data_get($activity->properties, 'checklist.lampiran_nota_dinas_sesuai'))->toBeTrue()
        ->and(data_get($activity->properties, 'checklist.sistem_aplikasi_sesuai'))->toBeTrue();
});

test('dua tiket berurutan memperoleh nomor tiket berurutan', function () {
    $this->actingAs($this->admin);

    $firstRfc = ($this->makeRfc)();
    $secondRfc = ($this->makeRfc)();

    $first = Livewire::test(ReviewCompleteness::class, ['rfc' => $firstRfc]);
    ($this->fillTicketForm)($first);
    $first->call('createTicket')->assertHasNoErrors();

    $second = Livewire::test(ReviewCompleteness::class, ['rfc' => $secondRfc]);
    ($this->fillTicketForm)($second);
    $second->call('createTicket')->assertHasNoErrors();

    $year = now()->format('Y');

    expect($firstRfc->fresh()->ticket_number)->toBe("CHG-{$year}-0001")
        ->and($secondRfc->fresh()->ticket_number)->toBe("CHG-{$year}-0002");
});

test('generator nomor tiket memperhitungkan RFC yang sudah soft deleted', function () {
    $this->actingAs($this->admin);

    $deleted = ($this->makeRfc)([
        'status' => RfcStatus::APPROVED->value,
        'ticket_number' => 'CHG-'.now()->format('Y').'-0007',
    ]);
    $deleted->delete();

    $rfc = ($this->makeRfc)();
    $component = Livewire::test(ReviewCompleteness::class, ['rfc' => $rfc]);
    ($this->fillTicketForm)($component);
    $component->call('createTicket')->assertHasNoErrors();

    expect($rfc->fresh()->ticket_number)->toBe('CHG-'.now()->format('Y').'-0008');
});

test('RFC returned_to_admin memakai kembali nomor tiket lama dan memuat isian sebelumnya', function () {
    $this->actingAs($this->admin);

    $ticketNumber = 'CHG-'.now()->format('Y').'-0456';
    $rfc = ($this->makeRfc)([
        'status' => RfcStatus::RETURNED_TO_ADMIN->value,
        'ticket_number' => $ticketNumber,
        'priority' => TicketPriority::URGENT->value,
        'category' => ServiceCategory::DATABASE->value,
        'admin_note' => 'Catatan tiket sebelumnya.',
        'ticket_created_at' => now()->subDay(),
        'ticket_created_by_id' => $this->admin->id,
    ]);

    $component = Livewire::test(ReviewCompleteness::class, ['rfc' => $rfc])
        ->assertSet('priority', TicketPriority::URGENT->value)
        ->assertSet('category', ServiceCategory::DATABASE->value)
        ->assertSet('admin_note', 'Catatan tiket sebelumnya.');

    ($this->fillTicketForm)($component, [
        'priority' => TicketPriority::LOW->value,
        'category' => ServiceCategory::NETWORK->value,
        'admin_note' => 'Catatan tiket setelah dikembalikan.',
    ]);

    $component->call('createTicket')->assertHasNoErrors();

    $fresh = $rfc->fresh();

    expect($fresh->ticket_number)->toBe($ticketNumber)
        ->and($fresh->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($fresh->priority)->toBe(TicketPriority::LOW)
        ->and($fresh->category)->toBe(ServiceCategory::NETWORK)
        ->and($fresh->admin_note)->toBe('Catatan tiket setelah dikembalikan.')
        ->and($fresh->ticket_created_by_id)->toBe($this->admin->id);
});

test('prioritas kategori dan checklist wajib untuk buat tiket tetapi tidak untuk revisi atau tolak', function () {
    $this->actingAs($this->admin);

    $rfc = ($this->makeRfc)();
    $component = Livewire::test(ReviewCompleteness::class, ['rfc' => $rfc])
        ->set('priority', '')
        ->set('category', '');

    $component->call('createTicket')
        ->assertHasErrors([
            'priority',
            'category',
            'data_complete',
            'attachments_verified',
            'application_verified',
        ]);

    $component->set('priority', TicketPriority::MEDIUM->value)
        ->set('category', ServiceCategory::APPLICATION->value)
        ->call('createTicket')
        ->assertHasErrors([
            'data_complete',
            'attachments_verified',
            'application_verified',
        ]);

    $revisionRfc = ($this->makeRfc)();
    Livewire::test(ReviewCompleteness::class, ['rfc' => $revisionRfc])
        ->set('action', RfcStatus::NEEDS_REVISION->value)
        ->set('note', 'Lengkapi berkas pendukung.')
        ->call('submitReview')
        ->assertHasNoErrors();

    expect($revisionRfc->fresh()->status)->toBe(RfcStatus::NEEDS_REVISION->value);

    $rejectedRfc = ($this->makeRfc)();
    Livewire::test(ReviewCompleteness::class, ['rfc' => $rejectedRfc])
        ->set('action', RfcStatus::REJECTED->value)
        ->set('note', 'Tidak memenuhi ketentuan.')
        ->call('submitReview')
        ->assertHasNoErrors();

    expect($rejectedRfc->fresh()->status)->toBe(RfcStatus::REJECTED->value);
});

test('prioritas bawaan adalah urgent untuk emergency unconfirmed dan medium untuk pengajuan biasa', function () {
    $this->actingAs($this->admin);

    $normalRfc = ($this->makeRfc)();

    Livewire::test(ReviewCompleteness::class, ['rfc' => $normalRfc])
        ->assertSet('priority', TicketPriority::MEDIUM->value);

    $emergencyRfc = ($this->makeRfc)([
        'change_type' => 'emergency',
        'emergency_unconfirmed' => true,
    ]);

    $component = Livewire::test(ReviewCompleteness::class, ['rfc' => $emergencyRfc])
        ->assertSet('priority', TicketPriority::URGENT->value)
        ->set('category', ServiceCategory::INFRASTRUCTURE->value)
        ->set('data_complete', true)
        ->set('attachments_verified', true)
        ->set('application_verified', true);

    $component->call('createTicket')->assertHasNoErrors();

    expect($emergencyRfc->fresh()->priority)->toBe(TicketPriority::URGENT);
});

test('otorisasi halaman buat tiket dan pembatasan status RFC', function () {
    $rfc = ($this->makeRfc)();

    $this->actingAs($this->pemohon)
        ->get(route('change-requests.review', $rfc))
        ->assertForbidden();

    $this->actingAs($this->cm)
        ->get(route('change-requests.review', $rfc))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('change-requests.review', $rfc))
        ->assertOk()
        ->assertSee('Buat Tiket Change');

    expect($this->admin->can('rfc.create'))->toBeFalse();

    foreach ([RfcStatus::DRAFT, RfcStatus::TICKET_CREATED, RfcStatus::APPROVED] as $status) {
        $invalidRfc = ($this->makeRfc)(['status' => $status->value]);

        $this->actingAs($this->admin)
            ->get(route('change-requests.review', $invalidRfc))
            ->assertRedirect(route('change-requests.show', $invalidRfc))
            ->assertSessionHas('error');
    }
});

test('notifikasi tiket hanya dikirim ke semua Change Manager dan tidak menduplikasi notifikasi Pemohon', function () {
    $secondCm = User::factory()->create(['name' => 'Change Manager Kedua']);
    $secondCm->assignRole('Change Manager');

    $this->actingAs($this->admin);

    $rfc = ($this->makeRfc)();
    $component = Livewire::test(ReviewCompleteness::class, ['rfc' => $rfc]);
    ($this->fillTicketForm)($component);
    $component->call('createTicket')->assertHasNoErrors();

    $fresh = $rfc->fresh();
    $message = "Tiket {$fresh->ticket_number} ({$fresh->rfc_number}) menunggu review Anda";

    foreach ([$this->cm, $secondCm] as $changeManager) {
        $notifications = $changeManager->notifications()->get();

        expect($notifications)->toHaveCount(1)
            ->and($notifications->first()->type)->toBe(ChangeManagerTicketNotification::class)
            ->and(data_get($notifications->first()->data, 'message'))->toBe($message)
            ->and(data_get($notifications->first()->data, 'rfc_id'))->toBe($fresh->id)
            ->and(data_get($notifications->first()->data, 'ticket_number'))->toBe($fresh->ticket_number);
    }

    $ownerNotifications = $this->pemohon->notifications()->get();

    expect($ownerNotifications)->toHaveCount(1)
        ->and($ownerNotifications->first()->type)->toBe(RfcStatusChangedNotification::class)
        ->and(data_get($ownerNotifications->first()->data, 'message'))
        ->toBe("RFC {$fresh->rfc_number} sedang ditinjau")
        ->and($this->pemohon->notifications()
            ->where('type', ChangeManagerTicketNotification::class)
            ->count())->toBe(0);

    $this->actingAs($this->cm);
    $cmNotification = $this->cm->notifications()->firstOrFail();

    Livewire::test(Notifications::class)
        ->assertSee($message)
        ->assertSeeHtml('bg-blue-100 text-blue-600')
        ->assertSeeHtml('M12 8.25h.01M12 11.25v5.25')
        ->call('markAsRead', $cmNotification->id)
        ->assertRedirect(route('change-requests.show', ['rfc' => $fresh->id]));

    expect($cmNotification->fresh()->read_at)->not->toBeNull();
});

test('Pemohon hanya melihat nomor tiket sedangkan staf melihat prioritas kategori dan catatan admin', function () {
    $this->actingAs($this->admin);

    $rfc = ($this->makeRfc)();
    $admin_note = 'Catatan internal Admin untuk Change Manager.';
    $component = Livewire::test(ReviewCompleteness::class, ['rfc' => $rfc]);
    ($this->fillTicketForm)($component, ['admin_note' => $admin_note]);
    $component->call('createTicket')->assertHasNoErrors();

    $fresh = $rfc->fresh();

    $this->actingAs($this->pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $fresh])
        ->assertOk()
        ->assertSee($fresh->ticket_number)
        ->assertDontSee('Prioritas')
        ->assertDontSee('Tinggi')
        ->assertDontSee('Kategori Layanan')
        ->assertDontSee('Keamanan')
        ->assertDontSee($admin_note)
        ->assertDontSee('Catatan Admin untuk Change Manager')
        ->assertDontSee('Ditetapkan Petugas');

    $this->actingAs($this->admin);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $fresh])
        ->assertOk()
        ->assertSee('Nomor Tiket')
        ->assertSee($fresh->ticket_number)
        ->assertSee('Prioritas')
        ->assertSee('Tinggi')
        ->assertSee('Kategori Layanan')
        ->assertSee('Keamanan')
        ->assertSee('Catatan Admin untuk Change Manager')
        ->assertSee($admin_note);
});

test('pencarian daftar staf menemukan RFC melalui nomor tiket', function () {
    $this->actingAs($this->admin);

    $year = now()->format('Y');
    $target = ($this->makeRfc)([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => "CHG-{$year}-0042",
        'title' => 'Target Pencarian Nomor Tiket',
    ]);
    $other = ($this->makeRfc)(['title' => 'RFC Yang Tidak Cocok']);

    $this->get(route('change-requests.index', ['q' => $target->ticket_number]))
        ->assertOk()
        ->assertSee('No. Tiket')
        ->assertSee($target->ticket_number)
        ->assertSee($target->title)
        ->assertDontSee($other->title);
});

test('klik ganda tidak membuat nomor tiket kedua ketika status berubah di tengah proses', function () {
    $this->actingAs($this->admin);

    $rfc = ($this->makeRfc)();
    $first = Livewire::test(ReviewCompleteness::class, ['rfc' => $rfc]);
    $second = Livewire::test(ReviewCompleteness::class, ['rfc' => $rfc]);

    ($this->fillTicketForm)($first);
    ($this->fillTicketForm)($second);

    $first->call('createTicket')->assertHasNoErrors();

    $ticketNumber = $rfc->fresh()->ticket_number;
    $activityCount = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->get()
        ->filter(fn (Activity $activity): bool => data_get($activity->properties, 'event') === 'ticket_created')
        ->count();

    $second->call('createTicket')->assertHasErrors(['status']);

    expect($rfc->fresh()->ticket_number)->toBe($ticketNumber)
        ->and(ChangeRequest::whereNotNull('ticket_number')->count())->toBe(1)
        ->and(Activity::query()
            ->where('subject_type', $rfc->getMorphClass())
            ->where('subject_id', $rfc->id)
            ->get()
            ->filter(fn (Activity $activity): bool => data_get($activity->properties, 'event') === 'ticket_created')
            ->count())->toBe($activityCount)
        ->and($activityCount)->toBe(1);
});

test('riwayat proses menampilkan kejadian tiket sesuai peran tanpa duplikasi', function () {
    $this->actingAs($this->admin);

    $rfc = ($this->makeRfc)();
    $admin_note = 'Catatan riwayat untuk Change Manager.';
    $component = Livewire::test(ReviewCompleteness::class, ['rfc' => $rfc]);
    ($this->fillTicketForm)($component, ['admin_note' => $admin_note]);
    $component->call('createTicket')->assertHasNoErrors();

    $fresh = $rfc->fresh();
    $staffEvents = collect(app(RfcHistoryService::class)->build($fresh, true)['events'])
        ->where('key', RfcStatus::TICKET_CREATED->value);

    expect($staffEvents)->toHaveCount(1);

    $staffEvent = $staffEvents->sole();

    expect($staffEvent['label'])->toBe("Tiket Change {$fresh->ticket_number} dibuat dan diteruskan ke Change Manager")
        ->and($staffEvent['note'])->toContain('Prioritas: Tinggi')
        ->and($staffEvent['note'])->toContain('Kategori layanan: Keamanan')
        ->and($staffEvent['note'])->toContain($admin_note)
        ->and($staffEvent['actor_name'])->toBe($this->admin->name);

    $ownerEvents = collect(app(RfcHistoryService::class)->build($fresh, false)['events'])
        ->where('key', RfcStatus::TICKET_CREATED->value);

    expect($ownerEvents)->toHaveCount(1);

    $ownerEvent = $ownerEvents->sole();

    expect($ownerEvent['label'])->toBe('Diverifikasi Admin dan diteruskan ke Change Manager')
        ->and($ownerEvent['note'])->toBe("Nomor tiket: {$fresh->ticket_number}")
        ->and($ownerEvent['actor_name'])->toBeNull()
        ->and($ownerEvent['note'])->not->toContain($admin_note)
        ->and($ownerEvent['note'])->not->toContain('Prioritas');
});

test('halaman buat tiket menampilkan ringkasan lampiran peringatan emergency dan catatan CM', function () {
    Storage::fake('local');

    $rfc = ($this->makeRfc)([
        'status' => RfcStatus::RETURNED_TO_ADMIN->value,
        'emergency_unconfirmed' => true,
        'change_type' => 'emergency',
        'description' => 'Deskripsi lengkap perubahan.',
        'reason_for_change' => 'Alasan bisnis perubahan.',
        'impact_description' => 'Estimasi dampak perubahan.',
        'is_physical_letter' => true,
        'physical_letter_number' => 'ND-777/2026',
    ]);

    $authority = Authority::factory()->create();
    $cmNote = 'Lengkapi analisis dampak sebelum tiket dikirim ulang.';

    Decision::create([
        'change_request_id' => $rfc->id,
        'authority_id' => $authority->id,
        'recorded_by_id' => $this->cm->id,
        'snapshot_authority_name' => $authority->name,
        'snapshot_authority_position' => $authority->position,
        'decision_status' => DecisionStatus::RETURNED_TO_ADMIN->value,
        'decision_date' => now(),
        'reason' => $cmNote,
    ]);

    $attachment = ChangeRequestAttachment::create([
        'change_request_id' => $rfc->id,
        'uploaded_by_id' => $this->pemohon->id,
        'original_name' => 'nota-dinas-tiket.pdf',
        'path' => 'attachments/nota-dinas-tiket.pdf',
        'mime_type' => 'application/pdf',
        'size' => 2048,
    ]);

    $this->actingAs($this->admin)
        ->get(route('change-requests.review', $rfc))
        ->assertOk()
        ->assertSee('Pemohon mengajukan kategori Emergency; Change Manager akan mengonfirmasi')
        ->assertSee('Catatan Change Manager yang Mengembalikan')
        ->assertSee($cmNote)
        ->assertSee('Deskripsi lengkap perubahan.')
        ->assertSee('Alasan bisnis perubahan.')
        ->assertSee('Estimasi dampak perubahan.')
        ->assertSee('ND-777/2026')
        ->assertSee($attachment->original_name)
        ->assertSee('Lihat')
        ->assertSee('Unduh')
        ->assertSee('Buat Tiket dan Kirim ke Change Manager')
        ->assertSee('Minta Revisi')
        ->assertSee('Tolak');
});

test('menu dan antrean buat tiket admin menampilkan RFC masuk dengan urutan terlama', function () {
    $older = ($this->makeRfc)([
        'status' => RfcStatus::SUBMITTED->value,
        'title' => 'RFC Masuk Lebih Lama',
    ]);
    $older->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();

    $newer = ($this->makeRfc)([
        'status' => RfcStatus::RETURNED_TO_ADMIN->value,
        'title' => 'RFC Masuk Lebih Baru',
    ]);
    $newer->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();

    $excluded = ($this->makeRfc)([
        'status' => RfcStatus::TICKET_CREATED->value,
        'title' => 'RFC Sudah Menjadi Tiket',
    ]);

    $this->actingAs($this->admin)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Buat Tiket Change')
        ->assertDontSee('Ajukan RFC')
        ->assertSee(route('change-requests.index', ['queue' => 'incoming']));

    $response = $this->get(route('change-requests.index', ['queue' => 'incoming']))
        ->assertOk()
        ->assertSee('Antrean Buat Tiket Change')
        ->assertSee($older->rfc_number)
        ->assertSee($newer->rfc_number)
        ->assertDontSee($excluded->rfc_number)
        ->assertSee('Buat Tiket');

    $html = $response->getContent();

    expect(strpos($html, $older->rfc_number))->toBeLessThan(strpos($html, $newer->rfc_number));

    $this->actingAs($this->pemohon)
        ->get(route('requester.dashboard'))
        ->assertOk()
        ->assertDontSee('Buat Tiket Change');

    $this->actingAs($this->cm)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertDontSee('Buat Tiket Change');
});

test('halaman Change Manager menampilkan prioritas dan catatan Admin secara baca-saja', function () {
    $rfc = ($this->makeRfc)([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'CHG-'.now()->format('Y').'-0099',
        'priority' => TicketPriority::HIGH->value,
        'category' => ServiceCategory::SECURITY->value,
        'admin_note' => 'Catatan Admin yang harus dibaca Change Manager.',
    ]);

    $this->actingAs($this->cm);

    foreach ([
        route('change-requests.assess-risk', $rfc),
        route('change-requests.record-decision', $rfc),
    ] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('Informasi Tiket dari Admin')
            ->assertSee('Nomor Tiket')
            ->assertSee($rfc->ticket_number)
            ->assertSee('Prioritas')
            ->assertSee('Tinggi')
            ->assertSee('Kategori Layanan')
            ->assertSee('Keamanan')
            ->assertSee('Catatan Admin untuk Change Manager')
            ->assertSee('Catatan Admin yang harus dibaca Change Manager.');
    }
});
