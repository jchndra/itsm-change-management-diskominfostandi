<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('merender kategori mentah dan label dashboard dengan teks, ikon, dan warna yang benar', function (string $value, string $label, string $category, string $colorClass) {
    $this->blade('<x-category-badge :kategori="$kategori" />', ['kategori' => $value])
        ->assertSee($label)
        ->assertSeeHtml('data-category="'.$category.'"')
        ->assertSeeHtml('data-category-icon="'.$category.'"')
        ->assertSeeHtml($colorClass)
        ->assertSeeHtml('<svg');
})->with([
    'standard mentah' => ['standard', 'Standar', 'standard', 'border-slate-300'],
    'normal mentah' => ['normal', 'Normal', 'normal', 'border-blue-300'],
    'emergency mentah' => ['emergency', 'Emergency', 'emergency', 'border-red-300'],
    'standard label' => ['Standar', 'Standar', 'standard', 'border-slate-300'],
    'normal label' => ['Normal', 'Normal', 'normal', 'border-blue-300'],
    'emergency label' => ['Emergency', 'Emergency', 'emergency', 'border-red-300'],
    'tidak tercatat' => ['Tidak tercatat', 'Belum dikategorikan', 'uncategorized', 'border-dashed'],
]);

it('menampilkan kategori kosong atau tidak dikenal sebagai belum dikategorikan', function (?string $value) {
    $this->blade('<x-category-badge :kategori="$kategori" />', ['kategori' => $value])
        ->assertSee('Belum dikategorikan')
        ->assertSeeHtml('data-category="uncategorized"')
        ->assertSeeHtml('border-dashed');
})->with([
    'null' => [null],
    'kosong' => [''],
    'tidak dikenal' => ['nilai-lama'],
]);

it('menambahkan label kategori yang diajukan pada mode diajukan', function () {
    $this->blade('<x-category-badge :kategori="$kategori" mode="diajukan" />', ['kategori' => 'emergency'])
        ->assertSee('Kategori yang diajukan:')
        ->assertSee('Emergency')
        ->assertSeeHtml('data-category="emergency"');
});

it('merender lencana risiko dan prioritas hanya sebagai komponen terpisah dengan ikon', function () {
    $this->blade('<x-risk-badge risiko="Tinggi" />')
        ->assertSee('Risiko: Tinggi')
        ->assertSeeHtml('data-risk-level="high"')
        ->assertSeeHtml('<svg');

    $this->blade('<x-priority-badge prioritas="high" />')
        ->assertSee('Prioritas: Tinggi')
        ->assertSeeHtml('data-priority="high"')
        ->assertSeeHtml('<svg');
});

it('menampilkan nilai risiko tak dikenal apa adanya dan menyembunyikan nilai kosong', function () {
    $this->blade('<x-risk-badge risiko="Nilai-Risiko-Lama" />')
        ->assertSee('Risiko: Nilai-Risiko-Lama')
        ->assertSeeHtml('data-risk-level="unknown"');

    $this->blade('<x-risk-badge :risiko="null" />')
        ->assertDontSee('Risiko:');

    $this->blade('<x-priority-badge :prioritas="null" />')
        ->assertDontSee('Prioritas:');
});
