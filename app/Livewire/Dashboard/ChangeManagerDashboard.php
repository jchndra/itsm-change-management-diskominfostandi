<?php

namespace App\Livewire\Dashboard;

use App\Models\User;
use App\Services\DashboardMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class ChangeManagerDashboard extends Component
{
    #[Url(as: 'periode')]
    public string $period = '30';

    #[Url(as: 'dari')]
    public string $dateFrom = '';

    #[Url(as: 'sampai')]
    public string $dateTo = '';

    /**
     * Periksa otorisasi pada setiap request Livewire, termasuk request lanjutan.
     */
    public function boot(): void
    {
        Gate::authorize('viewChangeManagerDashboard');
    }

    /**
     * Rentang tren terpilih; nilai tidak valid dikembalikan ke 30 hari.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    public function resolvePeriod(): array
    {
        $range = $this->periodRange($this->today());

        return [$range['start'], $range['end'], $range['label']];
    }

    public function periodErrorMessage(): string
    {
        return $this->periodRange($this->today())['error'];
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        [$trendStart, $trendEnd, $periodLabel] = $this->resolvePeriod();

        return view('livewire.dashboard.change-manager-dashboard', [
            'user' => $user,
            'overview' => app(DashboardMetricsService::class)
                ->changeManagerOverview(null, $trendStart, $trendEnd),
            'periodLabel' => $periodLabel,
            'periodError' => $this->periodErrorMessage(),
        ])->title('Dashboard Change Manager');
    }

    private function today(): Carbon
    {
        return now()->timezone(config('app.timezone'))->startOfDay();
    }

    /**
     * @return array{start: Carbon, end: Carbon, label: string, error: string}
     */
    private function periodRange(Carbon $today): array
    {
        $default = [
            'start' => $today->copy()->subDays(29),
            'end' => $today->copy(),
            'label' => '30 Hari',
            'error' => '',
        ];

        $range = match ($this->period) {
            '7' => [
                'start' => $today->copy()->subDays(6),
                'end' => $today->copy(),
                'label' => '7 Hari',
            ],
            '30' => [],
            '90' => [
                'start' => $today->copy()->subDays(89),
                'end' => $today->copy(),
                'label' => '90 Hari',
            ],
            'this_month' => [
                'start' => $today->copy()->startOfMonth(),
                'end' => $today->copy(),
                'label' => 'Bulan Ini',
            ],
            'last_month' => [
                'start' => $today->copy()->subMonthNoOverflow()->startOfMonth(),
                'end' => $today->copy()->subMonthNoOverflow()->startOfMonth()->endOfMonth()->startOfDay(),
                'label' => 'Bulan Lalu',
            ],
            'custom' => $this->customRange($today),
            default => [
                'error' => 'Periode tidak dikenal. Menampilkan 30 hari terakhir.',
            ],
        };

        return array_merge($default, $range);
    }

    /**
     * @return array{start?: Carbon, end?: Carbon, label?: string, error?: string}
     */
    private function customRange(Carbon $today): array
    {
        $start = $this->parseDate($this->dateFrom);
        $end = $this->parseDate($this->dateTo);

        if ($start === null || $end === null) {
            return ['error' => 'Isi tanggal mulai dan akhir dengan format YYYY-MM-DD.'];
        }

        if ($start->greaterThan($end)) {
            return ['error' => 'Tanggal mulai tidak boleh melebihi tanggal akhir.'];
        }

        if ($end->greaterThan($today)) {
            return ['error' => 'Tanggal akhir tidak boleh melewati hari ini.'];
        }

        if ((int) $start->diffInDays($end) + 1 > 180) {
            return ['error' => 'Rentang kustom maksimal 180 hari.'];
        }

        return [
            'start' => $start,
            'end' => $end,
            'label' => $start->copy()->locale('id')->translatedFormat('d M')
                .' - '.$end->copy()->locale('id')->translatedFormat('d M Y'),
            'error' => '',
        ];
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        if ($date === null || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date->startOfDay();
    }
}
