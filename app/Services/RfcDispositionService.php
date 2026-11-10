<?php

namespace App\Services;

use App\Enums\RfcStatus;
use App\Exceptions\RfcDispositionException;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RfcDispositionService
{
    public const MIN_INSTRUCTION_LENGTH = 10;

    public const MAX_INSTRUCTION_LENGTH = 1000;

    /**
     * Teruskan RFC yang sudah disetujui kepada anggota Tim Rilis.
     *
     * Status RFC sengaja tidak diubah. Seluruh penjagaan status, duplikasi,
     * dan kelayakan penerima berada di service agar request yang dimanipulasi
     * tetap ditolak, termasuk untuk Super Admin.
     *
     * @param  array<int, int|string>  $memberIds
     * @return Collection<int, ReleaseTask>
     */
    public function assign(ChangeRequest $rfc, User $actor, array $memberIds, string $instruction): Collection
    {
        $instruction = $this->validateInstruction($instruction);
        $normalizedMemberIds = $this->normalizeMemberIds($memberIds);
        $this->assertSingleRecipient($normalizedMemberIds);

        $tasks = DB::transaction(function () use ($rfc, $actor, $normalizedMemberIds, $instruction): Collection {
            // Baca ulang di bawah row lock agar dua Change Manager tidak dapat
            // mendisposisikan RFC yang sama secara bersamaan.
            $lockedRfc = ChangeRequest::withTrashed()
                ->whereKey($rfc->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedRfc || $lockedRfc->trashed()) {
                throw new RfcDispositionException('RFC tidak ditemukan atau sudah dihapus.');
            }

            if (RfcStatus::fromValue($lockedRfc->status) !== RfcStatus::APPROVED) {
                throw new RfcDispositionException('Disposisi hanya dapat dilakukan untuk RFC yang sudah disetujui.');
            }

            return $this->createDispositionTasks($lockedRfc, $actor, $normalizedMemberIds, $instruction);
        });

        // Pengiriman notifikasi tidak boleh membatalkan disposisi yang sudah
        // tersimpan; kegagalannya ditangani di dalam notifier.
        app(RfcNotifier::class)->dispositionAssigned($rfc->fresh() ?? $rfc);

        return $tasks;
    }

    /**
     * Inti pembuatan tugas disposisi tanpa membuka transaksi sendiri.
     *
     * Pemanggil wajib sudah berada di dalam transaksi dan mengunci RFC. Jalur
     * lama tetap memeriksa status APPROVED sebelum memanggil method ini.
     *
     * @param  array<int, int|string>  $memberIds
     * @return Collection<int, ReleaseTask>
     */
    public function createDispositionTasks(ChangeRequest $rfc, User $actor, array $memberIds, string $instruction): Collection
    {
        $instruction = $this->validateInstruction($instruction);
        $normalizedMemberIds = $this->normalizeMemberIds($memberIds);
        $this->assertSingleRecipient($normalizedMemberIds);

        if ($rfc->releaseTasks()->exists()) {
            throw new RfcDispositionException('RFC ini sudah pernah didisposisikan dan tidak dapat diubah.');
        }

        $members = ReleaseTeamMember::query()
            ->with(['user.roles'])
            ->whereKey($normalizedMemberIds)
            ->get()
            ->keyBy('id');

        if ($members->count() !== count($normalizedMemberIds)) {
            throw new RfcDispositionException('Sebagian penerima disposisi tidak ditemukan.');
        }

        $eligibleMembers = [];

        foreach ($normalizedMemberIds as $memberId) {
            $member = $members->get($memberId);

            if (! $member instanceof ReleaseTeamMember || ! $member->isEligibleForAssignment()) {
                throw new RfcDispositionException(
                    'Penerima disposisi harus anggota aktif yang tertaut ke akun aktif dengan role Tim Rilis.'
                );
            }

            $eligibleMembers[] = $member;
        }

        $createdTasks = new Collection;

        foreach ($eligibleMembers as $member) {
            $createdTasks->push($rfc->releaseTasks()->create([
                'release_team_member_id' => $member->id,
                'assigned_by_id' => $actor->id,
                'instruction' => $instruction,
                'scheduled_start' => null,
                'scheduled_end' => null,
                'execution_status' => 'pending',
                'executed_at' => null,
                'execution_notes' => null,
            ]));
        }

        activity()
            ->performedOn($rfc)
            ->causedBy($actor)
            ->withProperties([
                'event' => 'release_disposition',
                'recipient_names' => array_map(
                    fn (ReleaseTeamMember $member): string => $member->name,
                    $eligibleMembers,
                ),
                'recipient_count' => count($eligibleMembers),
                'instruction' => $instruction,
                'release_task_ids' => $createdTasks->modelKeys(),
                'assigned_by_id' => $actor->id,
            ])
            ->log('Disposisi ke Tim Rilis');

        return $createdTasks;
    }

    private function validateInstruction(string $instruction): string
    {
        $instruction = trim($instruction);

        if (mb_strlen($instruction) < self::MIN_INSTRUCTION_LENGTH) {
            throw new RfcDispositionException(
                'Instruksi minimal '.self::MIN_INSTRUCTION_LENGTH.' karakter.'
            );
        }

        if (mb_strlen($instruction) > self::MAX_INSTRUCTION_LENGTH) {
            throw new RfcDispositionException(
                'Instruksi maksimal '.self::MAX_INSTRUCTION_LENGTH.' karakter.'
            );
        }

        return $instruction;
    }

    /**
     * @param  array<int, int|string>  $memberIds
     * @return array<int, int>
     */
    private function normalizeMemberIds(array $memberIds): array
    {
        $normalized = [];

        foreach ($memberIds as $memberId) {
            $validated = filter_var($memberId, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($validated === false) {
                throw new RfcDispositionException('Pilihan penerima disposisi tidak valid.');
            }

            $normalized[] = (int) $validated;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<int, int>  $normalizedMemberIds
     */
    private function assertSingleRecipient(array $normalizedMemberIds): void
    {
        if (count($normalizedMemberIds) !== 1) {
            throw new RfcDispositionException('Pilih tepat satu anggota Tim Rilis sebagai penerima disposisi.');
        }
    }
}
