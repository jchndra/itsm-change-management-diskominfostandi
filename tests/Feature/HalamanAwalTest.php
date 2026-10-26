<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('halaman awal mengarahkan pengunjung ke halaman login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('pengunjung yang belum login tidak dapat membuka daftar RFC', function () {
    $this->get(route('change-requests.index'))->assertRedirect();
});
