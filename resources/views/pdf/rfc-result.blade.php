@php
    $formatDate = function ($value): ?string {
        if (blank($value)) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($value)
            ->timezone('Asia/Jakarta')
            ->locale('id')
            ->translatedFormat('d F Y, H:i');
    };

    $logoPath = public_path('images/logo-diskominfostandi.png');
    $logoData = is_file($logoPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
        : null;

    $changeTypeLabel = match ((string) $rfc->change_type) {
        'standard' => 'Standar',
        'normal' => 'Normal',
        'emergency' => 'Emergency',
        default => null,
    };
    $categoryLabel = $rfc->category?->label();
    $priorityLabel = $rfc->priority?->label();
    $latestDecision = $rfc->decisions->sortByDesc('decision_date')->first();
    $implementation = $rfc->implementation;
    $evaluationNotes = $implementation?->evaluation_notes;
    $canViewSatisfaction = \Illuminate\Support\Facades\Gate::forUser($viewer)->allows('viewSatisfaction', $rfc);
    $generatedAt = now()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y, H:i').' WIB';
    $footerText = 'Dihasilkan oleh sistem pada '.$generatedAt.' untuk '.$viewer->name;
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Hasil RFC {{ $rfc->displayIdentifier() }}</title>
    <style>
        @page {
            margin: 118px 36px 72px 36px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #1e293b;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10pt;
            line-height: 1.45;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0f172a;
            margin-bottom: 14px;
        }

        .header-table td {
            vertical-align: middle;
            padding: 0 0 9px;
        }

        .header-logo {
            width: 78px;
        }

        .header-logo img {
            width: 66px;
            height: auto;
        }

        .header-title {
            text-align: center;
        }

        .header-title .agency {
            margin: 0;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .header-title .system {
            margin: 2px 0 0;
            font-size: 9pt;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .document-title {
            margin: 0 0 3px;
            font-size: 15pt;
            text-align: center;
            text-transform: uppercase;
        }

        .document-number {
            margin: 0 0 14px;
            text-align: center;
            color: #475569;
            font-size: 9.5pt;
        }

        h2 {
            margin: 15px 0 6px;
            padding: 5px 8px;
            background: #e2e8f0;
            border-left: 4px solid #1e40af;
            font-size: 10.5pt;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        table.data th,
        table.data td {
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            vertical-align: top;
            text-align: left;
        }

        table.data th {
            width: 27%;
            background: #f1f5f9;
            font-weight: bold;
            color: #334155;
        }

        table.data td {
            white-space: pre-wrap;
        }

        .muted {
            color: #64748b;
        }

        .stars {
            color: #b45309;
            font-size: 13pt;
            letter-spacing: 1px;
        }

        .task-block {
            border: 1px solid #cbd5e1;
            padding: 8px;
            margin-bottom: 8px;
        }

        .task-title {
            margin: 0 0 5px;
            font-weight: bold;
        }

        ol,
        ul {
            margin: 4px 0 4px 18px;
            padding: 0;
        }

        li {
            margin: 0 0 2px;
        }
    </style>
</head>

<body>
    <table class="header-table">
        <tr>
            <td class="header-logo">
                @if ($logoData)
                    <img src="{{ $logoData }}" alt="Logo Diskominfostandi Kota Bekasi">
                @endif
            </td>
            <td class="header-title">
                <p class="agency">Diskominfostandi Kota Bekasi</p>
                <p class="system">Sistem Manajemen Perubahan Layanan TI</p>
            </td>
            <td class="header-logo"></td>
        </tr>
    </table>

    <h1 class="document-title">Hasil RFC</h1>
    <p class="document-number">{{ $rfc->displayIdentifier() }}</p>

    <h2>Identitas RFC</h2>
    <table class="data">
        <tr>
            <th>Nomor RFC</th>
            <td>{{ $rfc->displayIdentifier() }}</td>
        </tr>
        @if (filled($rfc->ticket_number))
            <tr>
                <th>Nomor Tiket</th>
                <td>{{ $rfc->ticket_number }}</td>
            </tr>
        @endif
        <tr>
            <th>Judul</th>
            <td>{{ $rfc->title }}</td>
        </tr>
        @if (filled($rfc->application?->name))
            <tr>
                <th>Aplikasi</th>
                <td>{{ $rfc->application->name }}</td>
            </tr>
        @endif
        @if (filled($rfc->submittedBy?->name))
            <tr>
                <th>Pemohon</th>
                <td>{{ $rfc->submittedBy->name }}</td>
            </tr>
        @endif
        @if (filled($rfc->submittedBy?->opd?->name))
            <tr>
                <th>OPD Pemohon</th>
                <td>{{ $rfc->submittedBy->opd->name }}</td>
            </tr>
        @endif
        @if (filled($changeTypeLabel))
            <tr>
                <th>Jenis Perubahan</th>
                <td>{{ $changeTypeLabel }}</td>
            </tr>
        @endif
        @if (filled($categoryLabel))
            <tr>
                <th>Kategori Layanan</th>
                <td>{{ $categoryLabel }}</td>
            </tr>
        @endif
        @if (filled($priorityLabel))
            <tr>
                <th>Prioritas</th>
                <td>{{ $priorityLabel }}</td>
            </tr>
        @endif
        @if ($submittedAt = $formatDate($rfc->created_at))
            <tr>
                <th>Tanggal Pengajuan</th>
                <td>{{ $submittedAt }}</td>
            </tr>
        @endif
        @if ($ticketCreatedAt = $formatDate($rfc->ticket_created_at))
            <tr>
                <th>Tanggal Tiket Dibuat</th>
                <td>{{ $ticketCreatedAt }}</td>
            </tr>
        @endif
        <tr>
            <th>Status Akhir</th>
            <td>{{ $rfc->displayStatusLabel() }}</td>
        </tr>
    </table>

    @if (filled($rfc->reason_for_change)
        || filled($rfc->description)
        || filled($rfc->legal_basis)
        || filled($rfc->impact_description)
        || filled($rfc->change_risk)
        || $rfc->target_implementation_date)
        <h2>Latar Belakang</h2>
        <table class="data">
            @if (filled($rfc->reason_for_change))
                <tr>
                    <th>Alasan Perubahan</th>
                    <td>{{ $rfc->reason_for_change }}</td>
                </tr>
            @endif
            @if (filled($rfc->description))
                <tr>
                    <th>Deskripsi</th>
                    <td>{{ $rfc->description }}</td>
                </tr>
            @endif
            @if (filled($rfc->legal_basis))
                <tr>
                    <th>Dasar Hukum</th>
                    <td>{{ $rfc->legal_basis }}</td>
                </tr>
            @endif
            @if (filled($rfc->impact_description))
                <tr>
                    <th>Dampak</th>
                    <td>{{ $rfc->impact_description }}</td>
                </tr>
            @endif
            @if (filled($rfc->change_risk))
                <tr>
                    <th>Risiko Perubahan</th>
                    <td>{{ $rfc->change_risk }}</td>
                </tr>
            @endif
            @if ($targetDate = $formatDate($rfc->target_implementation_date))
                <tr>
                    <th>Target Tanggal Implementasi</th>
                    <td>{{ $targetDate }}</td>
                </tr>
            @endif
        </table>
    @endif

    @if ($rfc->riskAssessment)
        @php($risk = $rfc->riskAssessment)
        <h2>Analisis Risiko</h2>
        <table class="data">
            @if (filled($risk->risk_level))
                <tr>
                    <th>Tingkat Risiko</th>
                    <td>{{ $risk->risk_level }}</td>
                </tr>
            @endif
            @if (filled($risk->impact_level))
                <tr>
                    <th>Tingkat Dampak</th>
                    <td>{{ $risk->impact_level }}</td>
                </tr>
            @endif
            @if (filled($risk->urgency_level))
                <tr>
                    <th>Urgensi Perubahan</th>
                    <td>{{ $risk->urgency_level }}</td>
                </tr>
            @endif
            @if (filled($risk->classification))
                <tr>
                    <th>Klasifikasi Perubahan</th>
                    <td>{{ $risk->classification }}</td>
                </tr>
            @endif
            <tr>
                <th>Skor Kritis Sistem</th>
                <td>{{ $risk->system_criticality_score }}</td>
            </tr>
            <tr>
                <th>Skor Cakupan Dampak</th>
                <td>{{ $risk->impact_scope_score }}</td>
            </tr>
            <tr>
                <th>Skor Kesiapan Rollback</th>
                <td>{{ $risk->rollback_readiness_score }}</td>
            </tr>
            <tr>
                <th>Total Skor</th>
                <td>{{ $risk->total_score }}</td>
            </tr>
            @if (filled($risk->analysis_notes))
                <tr>
                    <th>Catatan Analisis Risiko</th>
                    <td>{{ $risk->analysis_notes }}</td>
                </tr>
            @endif
            @if (filled($risk->mitigation_plan))
                <tr>
                    <th>Rencana Mitigasi</th>
                    <td>{{ $risk->mitigation_plan }}</td>
                </tr>
            @endif
            @if (filled($risk->rollback_plan))
                <tr>
                    <th>Rencana Rollback</th>
                    <td>{{ $risk->rollback_plan }}</td>
                </tr>
            @endif
            @if (filled($risk->assessedBy?->name))
                <tr>
                    <th>Dinilai Oleh</th>
                    <td>{{ $risk->assessedBy->name }}</td>
                </tr>
            @endif
            @if ($assessedAt = $formatDate($risk->created_at))
                <tr>
                    <th>Tanggal Penilaian</th>
                    <td>{{ $assessedAt }}</td>
                </tr>
            @endif
        </table>
    @endif

    @if ($latestDecision)
        <h2>Keputusan Change Manager</h2>
        <table class="data">
            <tr>
                <th>Keputusan</th>
                <td>{{ \App\Enums\DecisionStatus::labelFor($latestDecision->decision_status) }}</td>
            </tr>
            @if (filled($latestDecision->reason))
                <tr>
                    <th>Catatan/Alasan</th>
                    <td>{{ $latestDecision->reason }}</td>
                </tr>
            @endif
            @if (filled($latestDecision->snapshot_authority_name))
                <tr>
                    <th>Nama Change Manager</th>
                    <td>{{ $latestDecision->snapshot_authority_name }}</td>
                </tr>
            @endif
            @if (filled($latestDecision->snapshot_authority_nip))
                <tr>
                    <th>NIP</th>
                    <td>{{ $latestDecision->snapshot_authority_nip }}</td>
                </tr>
            @endif
            @if (filled($latestDecision->snapshot_authority_position))
                <tr>
                    <th>Jabatan</th>
                    <td>{{ $latestDecision->snapshot_authority_position }}</td>
                </tr>
            @endif
            @if ($decisionAt = $formatDate($latestDecision->decision_date))
                <tr>
                    <th>Tanggal</th>
                    <td>{{ $decisionAt }}</td>
                </tr>
            @endif
        </table>
    @endif

    @if ($rfc->releaseTasks->isNotEmpty() || $implementation)
        <h2>Pelaksanaan Tim Rilis</h2>

        @foreach ($rfc->releaseTasks as $task)
            <div class="task-block">
                <p class="task-title">{{ $task->releaseTeamMember?->name ?? 'Anggota Tim Rilis' }}</p>
                <table class="data">
                    @if (filled($task->releaseTeamMember?->role_title))
                        <tr>
                            <th>Peran</th>
                            <td>{{ $task->releaseTeamMember->role_title }}</td>
                        </tr>
                    @endif
                    @if ($task->estimated_days !== null)
                        <tr>
                            <th>Estimasi</th>
                            <td>{{ $task->estimated_days }} hari</td>
                        </tr>
                    @endif
                    @if ($startAt = $formatDate($task->scheduled_start))
                        <tr>
                            <th>Waktu Mulai</th>
                            <td>{{ $startAt }}</td>
                        </tr>
                    @endif
                    @if ($endAt = $formatDate($task->scheduled_end))
                        <tr>
                            <th>Waktu Selesai</th>
                            <td>{{ $endAt }}</td>
                        </tr>
                    @endif
                </table>

                @php($revisions = $task->updates->where('type', 'estimate_revised')->sortBy('created_at'))
                @if ($revisions->isNotEmpty())
                    <p class="task-title" style="margin-top: 7px;">Riwayat Revisi Estimasi</p>
                    <ul>
                        @foreach ($revisions as $revision)
                            <li>
                                {{ $revision->old_estimated_days }} hari &rarr; {{ $revision->new_estimated_days }} hari
                                @if (filled($revision->reason))
                                    &middot; Alasan: {{ $revision->reason }}
                                @endif
                                @if ($revisionAt = $formatDate($revision->created_at))
                                    &middot; {{ $revisionAt }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach

        @if ($implementation)
            <table class="data">
                <tr>
                    <th>Hasil Implementasi</th>
                    <td>{{ \App\Enums\ImplementationResult::labelFor($implementation->implementation_result) }}</td>
                </tr>
                @if (filled($implementation->executor_name))
                    <tr>
                        <th>Pelaksana</th>
                        <td>{{ $implementation->executor_name }}</td>
                    </tr>
                @endif
                @if ($startedAt = $formatDate($implementation->started_at))
                    <tr>
                        <th>Mulai Dikerjakan</th>
                        <td>{{ $startedAt }}</td>
                    </tr>
                @endif
                @if ($finishedAt = $formatDate($implementation->finished_at))
                    <tr>
                        <th>Selesai Dikerjakan</th>
                        <td>{{ $finishedAt }}</td>
                    </tr>
                @endif
                @if (filled($implementation->summary))
                    <tr>
                        <th>Ringkasan Hasil</th>
                        <td>{{ $implementation->summary }}</td>
                    </tr>
                @endif
                @if (filled($implementation->test_results))
                    <tr>
                        <th>Hasil Uji</th>
                        <td>{{ $implementation->test_results }}</td>
                    </tr>
                @endif
                @if (filled($implementation->failure_notes))
                    <tr>
                        <th>Catatan Kegagalan / Rollback</th>
                        <td>{{ $implementation->failure_notes }}</td>
                    </tr>
                @endif
                @if (filled($implementation->follow_up_action))
                    <tr>
                        <th>Tindak Lanjut</th>
                        <td>{{ \App\Enums\FollowUpAction::labelFor($implementation->follow_up_action) }}</td>
                    </tr>
                @endif
                @if (filled($implementation->follow_up_notes))
                    <tr>
                        <th>Keterangan Tindak Lanjut</th>
                        <td>{{ $implementation->follow_up_notes }}</td>
                    </tr>
                @endif
                @php($evidenceNames = $rfc->evidences->pluck('original_filename')->filter()->values())
                @if ($evidenceNames->isNotEmpty())
                    <tr>
                        <th>Berkas Bukti</th>
                        <td>
                            <ul>
                                @foreach ($evidenceNames as $evidenceName)
                                    <li>{{ $evidenceName }}</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endif
            </table>
        @endif
    @endif

    @if ($implementation && filled($evaluationNotes))
        <h2>Evaluasi Change Manager</h2>
        <table class="data">
            <tr>
                <th>Catatan Evaluasi</th>
                <td>{{ $evaluationNotes }}</td>
            </tr>
            @if (filled($implementation->evaluatedBy?->name))
                <tr>
                    <th>Dievaluasi Oleh</th>
                    <td>{{ $implementation->evaluatedBy->name }}</td>
                </tr>
            @endif
            @if ($evaluatedAt = $formatDate($implementation->evaluated_at))
                <tr>
                    <th>Tanggal Evaluasi</th>
                    <td>{{ $evaluatedAt }}</td>
                </tr>
            @endif
        </table>
    @endif

    @if ($rfc->closed_at || filled($rfc->closure_notes))
        <h2>Penutupan</h2>
        <table class="data">
            @if ($closedAt = $formatDate($rfc->closed_at))
                <tr>
                    <th>Tanggal Penutupan</th>
                    <td>{{ $closedAt }}</td>
                </tr>
            @endif
            @if (filled($rfc->closedBy?->name))
                <tr>
                    <th>Ditutup Oleh</th>
                    <td>{{ $rfc->closedBy->name }}</td>
                </tr>
            @endif
            @if (filled($rfc->closure_notes))
                <tr>
                    <th>Catatan Penutupan</th>
                    <td>{{ $rfc->closure_notes }}</td>
                </tr>
            @endif
        </table>
    @endif

    @if ($canViewSatisfaction && $rfc->satisfaction)
        <h2>Penilaian Pemohon</h2>
        <table class="data">
            <tr>
                <th>Penilaian</th>
                <td>
                    <span class="stars">
                        {!! str_repeat('&#9733;', $rfc->satisfaction->rating) !!}{!! str_repeat('&#9734;', 5 - $rfc->satisfaction->rating) !!}
                    </span>
                    <span class="muted">({{ $rfc->satisfaction->rating }}/5)</span>
                </td>
            </tr>
            @if (filled($rfc->satisfaction->comment))
                <tr>
                    <th>Komentar</th>
                    <td>{{ $rfc->satisfaction->comment }}</td>
                </tr>
            @endif
            @if ($satisfactionAt = $formatDate($rfc->satisfaction->created_at))
                <tr>
                    <th>Tanggal Penilaian</th>
                    <td>{{ $satisfactionAt }}</td>
                </tr>
            @endif
        </table>
    @endif

    @if ($rfc->attachments->isNotEmpty())
        <h2>Lampiran</h2>
        <table class="data">
            <tr>
                <th>Nama Berkas</th>
                <td>
                    <ul>
                        @foreach ($rfc->attachments as $attachment)
                            <li>{{ $attachment->original_name }}</li>
                        @endforeach
                    </ul>
                </td>
            </tr>
        </table>
    @endif

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont("DejaVu Sans", "normal");
            $pdf->page_text(36, 818, {!! json_encode($footerText) !!}, $font, 7, [0.35, 0.4, 0.45]);
            $pdf->page_text(500, 818, "Halaman {PAGE_NUM} dari {PAGE_COUNT}", $font, 7, [0.35, 0.4, 0.45]);
        }
    </script>
</body>

</html>
