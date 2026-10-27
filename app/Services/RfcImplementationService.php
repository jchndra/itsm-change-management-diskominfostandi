<?php

namespace App\Services;

use App\Enums\FollowUpAction;
use App\Enums\ImplementationResult;
use App\Enums\RfcStatus;
use App\Exceptions\RfcImplementationException;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestImplementation;
use App\Models\Evidence;
use App\Models\User;
use App\StateMachines\RfcStatusTransition;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class RfcImplementationService
{
    public const MAX_FILES = 5;

    public const MAX_FILE_BYTES = 5 * 1024 * 1024;

    /**
     * @var array<string, string>
     */
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
    ];

    /**
     * Catat implementasi RFC beserta bukti pendukungnya.
     *
     * Seluruh penjagaan status dan duplikasi berada di service ini agar aturan
     * bisnis tetap berlaku terlepas dari policy route.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function record(ChangeRequest $rfc, User $actor, array $data, array $files): ChangeRequestImplementation
    {
        $result = ImplementationResult::tryFrom((string) ($data['implementation_result'] ?? ''));

        if (! $result) {
            throw new RfcImplementationException('Hasil implementasi wajib dipilih dengan benar.');
        }

        $startedAt = $this->parseDate($data['started_at'] ?? null, 'Waktu mulai');
        $finishedAt = $this->parseDate($data['finished_at'] ?? null, 'Waktu selesai');

        if ($startedAt->greaterThan(now()) || $finishedAt->greaterThan(now())) {
            throw new RfcImplementationException('Waktu pelaksanaan tidak boleh melewati waktu saat ini.');
        }

        if ($finishedAt->lessThan($startedAt)) {
            throw new RfcImplementationException('Waktu selesai tidak boleh lebih awal dari waktu mulai.');
        }

        $executorName = trim((string) ($data['executor_name'] ?? ''));
        $reportSource = trim((string) ($data['report_source'] ?? ''));
        $summary = trim((string) ($data['summary'] ?? ''));
        $testResults = trim((string) ($data['test_results'] ?? ''));

        foreach ([
            'Pelaksana perubahan' => $executorName,
            'Sumber laporan' => $reportSource,
            'Ringkasan hasil' => $summary,
            'Hasil pengujian' => $testResults,
        ] as $label => $value) {
            if ($value === '') {
                throw new RfcImplementationException($label.' wajib diisi.');
            }
        }

        $isFailure = $result !== ImplementationResult::SUCCESS;
        $failureNotes = null;
        $followUpAction = null;
        $followUpNotes = null;

        if ($isFailure) {
            $failureNotes = trim((string) ($data['failure_notes'] ?? ''));

            if ($failureNotes === '') {
                throw new RfcImplementationException('Catatan kegagalan atau rollback wajib diisi.');
            }

            $followUpAction = FollowUpAction::tryFrom((string) ($data['follow_up_action'] ?? ''));

            if (! $followUpAction) {
                throw new RfcImplementationException('Pilihan tindak lanjut wajib ditentukan.');
            }

            $followUpNotes = trim((string) ($data['follow_up_notes'] ?? ''));

            if ($followUpAction !== FollowUpAction::NONE && $followUpNotes === '') {
                throw new RfcImplementationException('Keterangan tindak lanjut wajib diisi.');
            }
        }

        $files = array_values($files);

        $storedPaths = [];

        try {
            $implementation = DB::transaction(function () use (
                $rfc,
                $actor,
                $result,
                $startedAt,
                $finishedAt,
                $executorName,
                $reportSource,
                $summary,
                $testResults,
                $isFailure,
                $failureNotes,
                $followUpAction,
                $followUpNotes,
                $files,
                &$storedPaths,
            ): ChangeRequestImplementation {
                // Baca ulang status dari database di bawah row lock. Pengecekan ini
                // berlaku untuk semua pengguna, termasuk Super Admin.
                $lockedRfc = ChangeRequest::query()
                    ->whereKey($rfc->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $lockedRfc) {
                    throw new RfcImplementationException('RFC tidak ditemukan atau sudah dihapus.');
                }

                if ($lockedRfc->implementation()->exists()) {
                    throw new RfcImplementationException('RFC ini sudah memiliki catatan implementasi.');
                }

                $status = RfcStatus::fromValue($lockedRfc->status);

                if (! in_array($status, [RfcStatus::APPROVED, RfcStatus::IN_PROGRESS], true)) {
                    throw new RfcImplementationException(
                        'Implementasi hanya dapat dicatat untuk RFC yang sudah disetujui atau sedang dikerjakan.'
                    );
                }

                $this->validateFiles($files);

                $evidenceDrafts = [];

                foreach ($files as $file) {
                    $mimeType = strtolower((string) $file->getMimeType());
                    $extension = self::MIME_EXTENSIONS[$mimeType];
                    $path = $file->storeAs(
                        'implementation-evidences/'.$lockedRfc->id,
                        Str::uuid()->toString().'.'.$extension,
                        'local',
                    );

                    if (! is_string($path)) {
                        throw new RfcImplementationException('Berkas bukti gagal disimpan.');
                    }

                    $storedPaths[] = $path;

                    $evidenceDrafts[] = [
                        'file_path' => $path,
                        'original_filename' => $this->originalFilename($file, $extension),
                        'mime_type' => $mimeType,
                        'file_size' => (int) $file->getSize(),
                        'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                    ];
                }

                $implementation = $lockedRfc->implementation()->create([
                    'recorded_by_id' => $actor->id,
                    'implementation_result' => $result->value,
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                    'executor_name' => $executorName,
                    'report_source' => $reportSource,
                    'summary' => $summary,
                    'test_results' => $testResults,
                    'failure_notes' => $isFailure ? $failureNotes : null,
                    'follow_up_action' => $followUpAction?->value,
                    'follow_up_notes' => $isFailure ? ($followUpNotes !== '' ? $followUpNotes : null) : null,
                ]);

                $evidences = [];

                foreach ($evidenceDrafts as $draft) {
                    $evidences[] = $lockedRfc->evidences()->create([
                        'release_task_id' => null,
                        'uploaded_by_id' => $actor->id,
                        'evidence_type' => 'implementation',
                        'file_path' => $draft['file_path'],
                        'original_filename' => $draft['original_filename'],
                        'mime_type' => $draft['mime_type'],
                        'file_size' => $draft['file_size'],
                        'checksum_sha256' => $draft['checksum_sha256'],
                    ]);
                }

                if ($status === RfcStatus::APPROVED) {
                    RfcStatusTransition::transitionTo($lockedRfc, RfcStatus::IN_PROGRESS, $actor);
                }

                RfcStatusTransition::transitionTo(
                    $lockedRfc,
                    $result === ImplementationResult::SUCCESS ? RfcStatus::IMPLEMENTED : RfcStatus::FAILED,
                    $actor,
                    null,
                    [
                        'event' => 'implementation_recorded',
                        'implementation_id' => $implementation->id,
                        'implementation_result' => $result->value,
                        'executor_name' => $executorName,
                        'started_at' => $startedAt->toDateTimeString(),
                        'finished_at' => $finishedAt->toDateTimeString(),
                        'report_source' => $reportSource,
                        'summary' => $summary,
                        'test_results' => $testResults,
                        'failure_notes' => $isFailure ? $failureNotes : null,
                        'follow_up_action' => $followUpAction?->value,
                        'follow_up_notes' => $isFailure && $followUpNotes !== '' ? $followUpNotes : null,
                        'evidence_count' => count($evidences),
                        'evidence_files' => array_map(
                            fn (Evidence $evidence): array => [
                                'name' => $evidence->original_filename,
                                'size' => (int) $evidence->file_size,
                                'mime' => $evidence->mime_type,
                                'checksum' => $evidence->checksum_sha256,
                            ],
                            $evidences,
                        ),
                    ],
                );

                return $implementation;
            });
        } catch (Throwable $exception) {
            // Database sudah rollback; berkas yang sempat tersimpan wajib dibersihkan.
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }

        return $implementation;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function validateFiles(array $files): void
    {
        if ($files === []) {
            throw new RfcImplementationException('Minimal satu berkas bukti wajib diunggah.');
        }

        if (count($files) > self::MAX_FILES) {
            throw new RfcImplementationException('Jumlah berkas bukti maksimal '.self::MAX_FILES.' file.');
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                throw new RfcImplementationException('Berkas bukti tidak valid.');
            }

            if ((int) $file->getSize() > self::MAX_FILE_BYTES) {
                throw new RfcImplementationException('Ukuran setiap berkas bukti maksimal 5 MB.');
            }

            $mimeType = strtolower((string) $file->getMimeType());

            if (! isset(self::MIME_EXTENSIONS[$mimeType])) {
                throw new RfcImplementationException('Berkas bukti harus berformat PDF, PNG, JPG, atau JPEG.');
            }
        }
    }

    private function parseDate(mixed $value, string $label): Carbon
    {
        if (blank($value)) {
            throw new RfcImplementationException($label.' wajib diisi.');
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            throw new RfcImplementationException($label.' tidak valid.');
        }
    }

    private function originalFilename(UploadedFile $file, string $extension): string
    {
        $name = trim((string) $file->getClientOriginalName());

        return $name !== '' ? $name : 'bukti.'.$extension;
    }
}
