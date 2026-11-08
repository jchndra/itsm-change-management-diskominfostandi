<?php

use App\Enums\RfcStatus;
use App\Exceptions\RfcCommentException;
use App\Livewire\ChangeRequests\Discussion;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\RfcCommentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->pemohon = User::factory()->create(['name' => 'Pemohon Diskusi']);
    $this->pemohon->assignRole('Pemohon');

    $this->pemohonLain = User::factory()->create(['name' => 'Pemohon Lain Diskusi']);
    $this->pemohonLain->assignRole('Pemohon');

    $this->cm = User::factory()->create(['name' => 'CM Diskusi', 'is_active' => true]);
    $this->cm->assignRole('Change Manager');

    $this->cmKedua = User::factory()->create(['name' => 'CM Kedua Diskusi', 'is_active' => true]);
    $this->cmKedua->assignRole('Change Manager');

    $this->admin = User::factory()->create(['name' => 'Admin Diskusi', 'is_active' => true]);
    $this->admin->assignRole('Admin');

    $this->timRilis = User::factory()->create(['name' => 'Tim Rilis Diskusi', 'is_active' => true]);
    $this->timRilis->assignRole('Tim Rilis');

    $this->timRilisLain = User::factory()->create(['name' => 'Tim Rilis Lain Diskusi', 'is_active' => true]);
    $this->timRilisLain->assignRole('Tim Rilis');

    $this->makeRfc = function (string $status = ''): ChangeRequest {
        return ChangeRequest::factory()->create([
            'submitted_by_id' => $this->pemohon->id,
            'created_by_id' => $this->pemohon->id,
            'status' => $status !== '' ? $status : RfcStatus::APPROVED->value,
        ]);
    };

    $this->assignRelease = function (ChangeRequest $rfc): void {
        $member = ReleaseTeamMember::factory()->linkedTo($this->timRilis)->create();

        ReleaseTask::factory()->forRfc($rfc)->assignedTo($member)->create([
            'assigned_by_id' => $this->cm->id,
        ]);
    };
});

it('mengizinkan Pemohon pemilik, Change Manager, dan Tim Rilis tertugas menulis komentar', function () {
    $rfc = ($this->makeRfc)();
    ($this->assignRelease)($rfc);

    $service = app(RfcCommentService::class);

    $service->add($rfc, $this->pemohon, 'Komentar dari pemohon.');
    $service->add($rfc, $this->cm, 'Komentar dari change manager.');
    $service->add($rfc, $this->timRilis, 'Komentar dari tim rilis.');

    expect($rfc->comments()->count())->toBe(3);

    $this->actingAs($this->pemohon);

    Livewire::test(Discussion::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Komentar dari pemohon.')
        ->assertSee('Komentar dari change manager.')
        ->assertSee('Komentar dari tim rilis.')
        ->set('body', 'Komentar baru lewat form.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('Komentar berhasil dikirim.');

    expect($rfc->comments()->count())->toBe(4);
});

it('menolak Tim Rilis tak tertugas, Pemohon lain, Admin, dan tamu untuk menulis', function () {
    $rfc = ($this->makeRfc)();
    ($this->assignRelease)($rfc);

    $service = app(RfcCommentService::class);

    foreach ([$this->timRilisLain, $this->pemohonLain, $this->admin] as $actor) {
        expect(fn () => $service->add($rfc, $actor, 'Halo dunia'))
            ->toThrow(RfcCommentException::class);
    }

    expect(Gate::forUser($this->timRilisLain)->allows('comment', $rfc))->toBeFalse()
        ->and(Gate::forUser($this->pemohonLain)->allows('comment', $rfc))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('comment', $rfc))->toBeFalse();

    $this->get(route('change-requests.show', ['rfc' => $rfc->id]))
        ->assertRedirect(route('login'));

    expect($rfc->comments()->count())->toBe(0);
});

it('memberi Admin akses baca diskusi tanpa hak menulis', function () {
    $rfc = ($this->makeRfc)();

    app(RfcCommentService::class)->add($rfc, $this->pemohon, 'Komentar publik terlihat.');

    expect(Gate::forUser($this->admin)->allows('view', $rfc))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows('comment', $rfc))->toBeFalse();

    $this->actingAs($this->admin);

    Livewire::test(Discussion::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Komentar publik terlihat.')
        ->assertDontSee('Catatan internal (tidak terlihat Pemohon)')
        ->assertSee('Hanya Pemohon, Change Manager, dan Tim Rilis yang ditugaskan yang dapat menulis komentar.');
});

it('menyembunyikan catatan internal dari Pemohon tetapi terlihat staf dan Tim Rilis', function () {
    $rfc = ($this->makeRfc)();
    ($this->assignRelease)($rfc);

    $internalBody = 'Rahasia internal hanya untuk staf.';

    app(RfcCommentService::class)->add($rfc, $this->cm, $internalBody, true);

    $this->actingAs($this->pemohon);

    Livewire::test(Discussion::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertDontSee($internalBody)
        ->assertSee('Belum ada komentar.');

    foreach ([$this->cm, $this->timRilis, $this->admin] as $reader) {
        $this->actingAs($reader);

        Livewire::test(Discussion::class, ['rfc' => $rfc->fresh()])
            ->assertSee($internalBody)
            ->assertSee('Internal');
    }
});

it('menolak Pemohon membuat catatan internal', function () {
    $rfc = ($this->makeRfc)();

    expect(fn () => app(RfcCommentService::class)->add($rfc, $this->pemohon, 'Coba catatan internal.', true))
        ->toThrow(
            RfcCommentException::class,
            'Catatan internal hanya dapat dibuat oleh Change Manager atau Tim Rilis yang ditugaskan.'
        );

    expect($rfc->comments()->count())->toBe(0);

    $this->actingAs($this->pemohon);

    Livewire::test(Discussion::class, ['rfc' => $rfc->fresh()])
        ->assertDontSee('Catatan internal (tidak terlihat Pemohon)');
});

it('menolak menulis pada RFC draft, closed, rejected, dan terhapus', function () {
    $service = app(RfcCommentService::class);

    foreach ([RfcStatus::DRAFT, RfcStatus::CLOSED, RfcStatus::REJECTED] as $status) {
        $rfc = ($this->makeRfc)($status->value);

        expect(Gate::forUser($this->pemohon)->allows('comment', $rfc))->toBeFalse();

        expect(fn () => $service->add($rfc, $this->pemohon, 'Halo dunia'))
            ->toThrow(RfcCommentException::class);
    }

    $trashed = ($this->makeRfc)();
    $trashed->delete();

    expect(Gate::forUser($this->pemohon)->allows('comment', $trashed))->toBeFalse();

    expect(fn () => $service->add($trashed, $this->pemohon, 'Halo dunia'))
        ->toThrow(RfcCommentException::class);
});

it('menolak komentar kurang dari 2 karakter atau lebih dari 1000 karakter', function () {
    $rfc = ($this->makeRfc)();
    $service = app(RfcCommentService::class);

    expect(fn () => $service->add($rfc, $this->pemohon, ' a '))
        ->toThrow(RfcCommentException::class);

    expect(fn () => $service->add($rfc, $this->pemohon, str_repeat('a', 1001)))
        ->toThrow(RfcCommentException::class);

    expect($rfc->comments()->count())->toBe(0);

    $this->actingAs($this->pemohon);

    Livewire::test(Discussion::class, ['rfc' => $rfc->fresh()])
        ->set('body', 'x')
        ->call('submit')
        ->assertHasErrors(['body' => 'min'])
        ->assertSee('Komentar minimal 2 karakter.');
});

it('membatasi maksimal sepuluh komentar per menit per pengguna', function () {
    $rfc = ($this->makeRfc)();
    $service = app(RfcCommentService::class);

    foreach (range(1, 10) as $index) {
        $service->add($rfc, $this->pemohon, "Komentar ke {$index}.");
    }

    expect(fn () => $service->add($rfc, $this->pemohon, 'Komentar ke sebelas.'))
        ->toThrow(RfcCommentException::class, 'Terlalu banyak komentar');

    expect($rfc->comments()->count())->toBe(10);
});

it('mengirim notifikasi komentar publik ke peserta lain tanpa isi komentar', function () {
    $rfc = ($this->makeRfc)();
    ($this->assignRelease)($rfc);

    $body = 'Isi komentar publik rahasia-XYZ.';

    app(RfcCommentService::class)->add($rfc, $this->pemohon, $body);

    expect($this->cm->notifications()->count())->toBe(1)
        ->and($this->cmKedua->notifications()->count())->toBe(1)
        ->and($this->timRilis->notifications()->count())->toBe(1)
        ->and($this->timRilisLain->notifications()->count())->toBe(0)
        ->and($this->pemohon->notifications()->count())->toBe(0)
        ->and($this->admin->notifications()->count())->toBe(0);

    $data = $this->cm->notifications()->firstOrFail()->data;

    expect(data_get($data, 'event'))->toBe('comment_added')
        ->and(data_get($data, 'message'))
        ->toBe("Pemohon Diskusi menambahkan komentar pada RFC {$rfc->rfc_number}")
        ->and(data_get($data, 'url'))
        ->toBe(route('change-requests.show', ['rfc' => $rfc->id]).'#diskusi')
        ->and(data_get($data, 'status'))->toBe($rfc->status)
        ->and(data_get($data, 'rfc_id'))->toBe($rfc->id)
        ->and(json_encode($data))->not->toContain($body);

    $activity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->get()
        ->first(fn (Activity $item): bool => data_get($item->properties, 'event') === 'comment_added');

    expect($activity)->not->toBeNull()
        ->and(data_get($activity->properties, 'is_internal'))->toBeFalse()
        ->and(json_encode($activity->properties))->not->toContain($body);
});

it('mengirim notifikasi catatan internal hanya ke Change Manager dan Tim Rilis tertugas', function () {
    $rfc = ($this->makeRfc)();
    ($this->assignRelease)($rfc);

    $body = 'Catatan internal rahasia.';

    app(RfcCommentService::class)->add($rfc, $this->cm, $body, true);

    expect($this->cm->notifications()->count())->toBe(0)
        ->and($this->cmKedua->notifications()->count())->toBe(1)
        ->and($this->timRilis->notifications()->count())->toBe(1)
        ->and($this->timRilisLain->notifications()->count())->toBe(0)
        ->and($this->pemohon->notifications()->count())->toBe(0)
        ->and($this->admin->notifications()->count())->toBe(0);

    $data = $this->cmKedua->notifications()->firstOrFail()->data;

    expect(data_get($data, 'event'))->toBe('comment_added')
        ->and(data_get($data, 'message'))
        ->toBe("CM Diskusi menambahkan catatan internal pada RFC {$rfc->rfc_number}")
        ->and(json_encode($data))->not->toContain($body);

    $activity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->get()
        ->first(fn (Activity $item): bool => data_get($item->properties, 'event') === 'comment_added');

    expect($activity)->not->toBeNull()
        ->and(data_get($activity->properties, 'is_internal'))->toBeTrue()
        ->and(json_encode($activity->properties))->not->toContain($body);
});
