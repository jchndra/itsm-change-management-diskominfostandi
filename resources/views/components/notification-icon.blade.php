@props([
    'status' => null,
    'outcome' => null,
])

@php
    $status = \App\Enums\RfcStatus::fromValue(
        is_object($status) && isset($status->value) ? $status->value : $status
    );

    $outcome = is_object($outcome) || is_array($outcome) ? null : $outcome;

    // Notifikasi lama tanpa outcome tetap menggunakan perilaku lama.
    if ($outcome === 'unsuccessful'
        && in_array($status, [\App\Enums\RfcStatus::FAILED, \App\Enums\RfcStatus::CLOSED], true)) {
        // Gagal atau rollback: merah, tidak pernah tampak seperti berhasil.
        [$tone, $icon] = [
            'bg-red-100 text-red-600',
            'M6 18 18 6M6 6l12 12',
        ];
    } elseif ($status === \App\Enums\RfcStatus::IMPLEMENTED && $outcome === 'successful') {
        // implemented menunggu evaluasi: kuning agar staf menindak; untuk
        // Pemohon cukup tampil sebagai informasi biru.
        $tone = auth()->user()?->isStaff() ?? false
            ? 'bg-amber-100 text-amber-600'
            : 'bg-blue-100 text-blue-600';
        $icon = 'M12 9v4m0 4h.01M10.34 3.94l-8.4 14.53A1.5 1.5 0 0 0 3.23 21h17.54a1.5 1.5 0 0 0 1.3-2.53l-8.4-14.53a1.5 1.5 0 0 0-2.6 0Z';
    } else {
        [$tone, $icon] = match ($status) {
            \App\Enums\RfcStatus::APPROVED,
            \App\Enums\RfcStatus::IMPLEMENTED,
            \App\Enums\RfcStatus::CLOSED => [
                'bg-emerald-100 text-emerald-600',
                'M4.5 12.75l6 6 9-13.5',
            ],
            \App\Enums\RfcStatus::NEEDS_REVISION => [
                'bg-amber-100 text-amber-600',
                'M12 9v4m0 4h.01M10.34 3.94l-8.4 14.53A1.5 1.5 0 0 0 3.23 21h17.54a1.5 1.5 0 0 0 1.3-2.53l-8.4-14.53a1.5 1.5 0 0 0-2.6 0Z',
            ],
            \App\Enums\RfcStatus::REJECTED,
            \App\Enums\RfcStatus::FAILED => [
                'bg-rose-100 text-rose-600',
                'M6 18 18 6M6 6l12 12',
            ],
            default => [
                'bg-blue-100 text-blue-600',
                'M12 8.25h.01M12 11.25v5.25',
            ],
        };
    }
@endphp

<span {{ $attributes->merge(['class' => "grid shrink-0 place-items-center rounded-full {$tone}"]) }}>
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}" />
    </svg>
</span>
