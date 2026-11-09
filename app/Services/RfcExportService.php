<?php

namespace App\Services;

use App\Enums\DecisionStatus;
use App\Enums\ImplementationResult;
use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class RfcExportService
{
    private const DATE_BASES = ['created', 'ticket'];

    private const CHANGE_TYPES = ['standard', 'normal', 'emergency'];

    /**
     * Lingkup dasar mengikuti cabang Change Manager/staf pada Daftar RFC:
     * seluruh RFC aktif (soft-deleted otomatis dikecualikan) tanpa pengecualian draft.
     *
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): Builder
    {
        $filters = $this->normalizeFilters($filters);
        $dateColumn = $filters['basis'] === 'ticket' ? 'ticket_created_at' : 'created_at';

        return ChangeRequest::query()
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$this->escapeLike($filters['q']).'%';

                $query->where(function (Builder $search) use ($term): void {
                    $search->where('title', 'like', $term)
                        ->orWhere('rfc_number', 'like', $term)
                        ->orWhere('ticket_number', 'like', $term);
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['change_type'] !== '', fn (Builder $query) => $query->where('change_type', $filters['change_type']))
            ->when($filters['application_id'] !== null, fn (Builder $query) => $query->where('application_id', $filters['application_id']))
            ->when($filters['opd_id'] !== null, fn (Builder $query) => $query->whereHas(
                'submittedBy',
                fn (Builder $user) => $user->where('opd_id', $filters['opd_id'])
            ))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->whereDate($dateColumn, '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->whereDate($dateColumn, '<=', $filters['to']));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    public function headers(): array
    {
        return [
            'No',
            'Nomor RFC',
            'Nomor Tiket',
            'Judul',
            'Aplikasi',
            'Jenis Perubahan',
            'Kategori Layanan',
            'Prioritas',
            'Pemohon',
            'OPD',
            'Status',
            'Tanggal Diajukan',
            'Tanggal Tiket',
            'Keputusan CM',
            'Tanggal Keputusan',
            'Tim Rilis',
            'Estimasi (hari)',
            'Mulai Pengerjaan',
            'Target Selesai',
            'Hasil Implementasi',
            'Tanggal Ditutup',
            'Rating Kepuasan',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Generator<int, array<int, mixed>>
     */
    public function rows(array $filters = []): \Generator
    {
        $query = $this->query($filters)->with([
            'application:id,name',
            'submittedBy:id,name,opd_id',
            'submittedBy.opd:id,name',
            'decisions' => fn ($decisions) => $decisions
                ->orderByDesc('decision_date')
                ->orderByDesc('id'),
            'releaseTask.releaseTeamMember:id,name',
            'implementation',
            'satisfaction',
        ]);

        $number = 0;

        foreach ($query->orderBy('id')->lazyById(500) as $rfc) {
            $number++;

            yield $this->row($rfc, $number);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summarize(array $filters, User $user): array
    {
        $filters = $this->normalizeFilters($filters);
        $query = $this->query($filters);

        $total = (clone $query)->count();

        $counts = (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $perStatus = [];

        foreach (RfcStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);

            if ($count > 0) {
                $perStatus[$status->label()] = $count;
            }
        }

        return [
            'periode' => $this->periodLabel($filters),
            'basis' => $filters['basis'] === 'ticket' ? 'Tanggal tiket' : 'Tanggal diajukan',
            'dicetak_oleh' => $user->name,
            'waktu_cetak' => Carbon::now('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
            'total' => $total,
            'per_status' => $perStatus,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{q: string, status: string, change_type: string, application_id: int|null, opd_id: int|null, from: string, to: string, basis: string}
     */
    public function normalizeFilters(array $filters): array
    {
        $normalized = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'status' => trim((string) ($filters['status'] ?? '')),
            'change_type' => trim((string) ($filters['change_type'] ?? '')),
            'application_id' => $filters['application_id'] ?? null,
            'opd_id' => $filters['opd_id'] ?? null,
            'from' => trim((string) ($filters['from'] ?? '')),
            'to' => trim((string) ($filters['to'] ?? '')),
            'basis' => trim((string) ($filters['basis'] ?? 'created')),
        ];

        if ($normalized['status'] !== '' && RfcStatus::fromValue($normalized['status']) === null) {
            throw new InvalidArgumentException('Filter status tidak valid.');
        }

        if ($normalized['change_type'] !== '' && ! in_array($normalized['change_type'], self::CHANGE_TYPES, true)) {
            throw new InvalidArgumentException('Filter jenis perubahan tidak valid.');
        }

        if (! in_array($normalized['basis'], self::DATE_BASES, true)) {
            throw new InvalidArgumentException('Filter basis tanggal tidak valid.');
        }

        foreach (['application_id', 'opd_id'] as $key) {
            $value = $normalized[$key];

            if ($value === null || $value === '') {
                $normalized[$key] = null;

                continue;
            }

            if (! is_numeric($value) || (int) $value < 1) {
                throw new InvalidArgumentException('Filter ID tidak valid.');
            }

            $normalized[$key] = (int) $value;
        }

        foreach (['from', 'to'] as $key) {
            if ($normalized[$key] === '') {
                continue;
            }

            if (! $this->isValidDate($normalized[$key])) {
                throw new InvalidArgumentException('Format tanggal filter harus Y-m-d.');
            }
        }

        if ($normalized['from'] !== '' && $normalized['to'] !== '' && $normalized['from'] > $normalized['to']) {
            throw new InvalidArgumentException('Tanggal awal filter tidak boleh melebihi tanggal akhir.');
        }

        return $normalized;
    }

    /**
     * @return array<int, mixed>
     */
    private function row(ChangeRequest $rfc, int $number): array
    {
        $decision = $rfc->decisions->first();
        $releaseTask = $rfc->releaseTask;

        return array_map(fn (mixed $value) => $this->sanitizeCell($value), [
            $number,
            $rfc->rfc_number,
            $rfc->ticket_number,
            $rfc->title,
            $rfc->application?->name,
            $this->changeTypeLabel($rfc->change_type),
            $rfc->category?->label(),
            $rfc->priority?->label(),
            $rfc->submittedBy?->name,
            $rfc->submittedBy?->opd?->name,
            RfcStatus::labelFor($rfc->status),
            $this->dateTimeWib($rfc->created_at),
            $this->dateTimeWib($rfc->ticket_created_at),
            $decision ? DecisionStatus::labelFor($decision->decision_status) : '',
            $this->dateTimeWib($decision?->decision_date),
            $releaseTask?->releaseTeamMember?->name,
            $releaseTask?->estimated_days,
            $this->dateTimeWib($releaseTask?->scheduled_start),
            $this->dateTimeWib($releaseTask?->scheduled_end),
            $rfc->implementation?->implementation_result !== null
                ? ImplementationResult::labelFor($rfc->implementation->implementation_result)
                : '',
            $this->dateTimeWib($rfc->closed_at),
            $rfc->satisfaction?->rating,
        ]);
    }

    private function changeTypeLabel(?string $changeType): string
    {
        return match ($changeType) {
            'standard' => 'Standard',
            'normal' => 'Normal',
            'emergency' => 'Darurat (Emergency)',
            default => (string) $changeType,
        };
    }

    private function dateTimeWib(mixed $value): string
    {
        if (! $value instanceof DateTimeInterface) {
            return '';
        }

        return Carbon::instance($value)->timezone('Asia/Jakarta')->format('d/m/Y H:i');
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function isValidDate(string $value): bool
    {
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, config('app.timezone'));
        } catch (\Throwable) {
            return false;
        }

        return $date !== null && $date->format('Y-m-d') === $value;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function periodLabel(array $filters): string
    {
        $from = $filters['from'] !== ''
            ? Carbon::createFromFormat('!Y-m-d', $filters['from'])->format('d/m/Y')
            : null;
        $to = $filters['to'] !== ''
            ? Carbon::createFromFormat('!Y-m-d', $filters['to'])->format('d/m/Y')
            : null;

        return match (true) {
            $from !== null && $to !== null => $from.' s.d. '.$to,
            $from !== null => 'Dari '.$from,
            $to !== null => 'Sampai '.$to,
            default => 'Semua',
        };
    }

    /**
     * Cegah formula injection pada seluruh keluaran teks.
     */
    public function sanitizeCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
