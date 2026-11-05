<?php

namespace App\Enums;

enum RiskEvent: string
{
    case POLICY_NOT_ADAPTED = 'R01';
    case SPBE_TARGET_NOT_MET = 'R02';
    case EVIDENCE_NOT_ADEQUATE = 'R03';
    case CYBER_INCIDENT = 'R04';
    case PERSONAL_DATA_LEAK = 'R05';
    case DATACENTER_DISRUPTION = 'R06';
    case INSUFFICIENT_PD_CAPACITY = 'R07';
    case REDUNDANT_APPLICATIONS = 'R08';
    case VENDOR_DEPENDENCY = 'R09';
    case FLOOD_DISRUPTION = 'R10';
    case NATIONAL_SERVICE_INTEGRATION = 'R11';
    case SATISFACTION_SURVEY = 'R12';

    /**
     * Kejadian risiko indikatif dari Register Risiko Indikatif Tingkat Kota (Lampiran B).
     */
    public function event(): string
    {
        return match ($this) {
            self::POLICY_NOT_ADAPTED => 'Kebijakan MR daerah belum disesuaikan dengan rezim Pemerintah Digital',
            self::SPBE_TARGET_NOT_MET => 'Target Indeks SPBE RPJMD tidak tercapai dan tidak terkalibrasi ke Indeks Pemda',
            self::EVIDENCE_NOT_ADEQUATE => 'Bukti dukung Indikator 2 tidak memadai saat evaluasi',
            self::CYBER_INCIDENT => 'Insiden siber (ransomware, peretasan, defacement) pada layanan digital',
            self::PERSONAL_DATA_LEAK => 'Kebocoran data pribadi warga dari aplikasi layanan',
            self::DATACENTER_DISRUPTION => 'Gangguan pusat data menghentikan layanan digital prioritas',
            self::INSUFFICIENT_PD_CAPACITY => 'Kapasitas pengelola risiko di PD tidak memadai',
            self::REDUNDANT_APPLICATIONS => 'Aplikasi redundan dan belum terintegrasi menurunkan mutu layanan',
            self::VENDOR_DEPENDENCY => 'Ketergantungan pada vendor menghambat pemeliharaan aplikasi',
            self::FLOOD_DISRUPTION => 'Banjir mengganggu ruang server dan perangkat jaringan',
            self::NATIONAL_SERVICE_INTEGRATION => 'Pemanfaatan layanan digital nasional mempercepat keterpaduan layanan',
            self::SATISFACTION_SURVEY => 'Survei kepuasan digital berkala meningkatkan mutu layanan dan nilai P20',
        };
    }

    /**
     * Label jadi untuk dropdown: "R01 — Kebijakan MR daerah ...".
     */
    public function optionLabel(): string
    {
        return "{$this->value} — {$this->event()}";
    }

    /**
     * Daftar pasangan kode dan kejadian untuk tampilan harga-tetap.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $event): array => [$event->value => $event->optionLabel()])
            ->all();
    }
}
