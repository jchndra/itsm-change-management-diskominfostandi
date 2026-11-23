<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('memuat markup hover/fokus sidebar desktop pada layout', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $html = $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    $start = strpos($html, '<aside');
    $end = strpos($html, '</aside>');
    $sidebar = ($start === false || $end === false) ? '' : substr($html, $start, $end - $start);

    expect($sidebar)->toContain('x-on:mouseenter')
        ->and($sidebar)->toContain('x-on:mouseleave')
        ->and($sidebar)->toContain('x-on:focusin')
        ->and($sidebar)->toContain('x-on:focusout')
        ->and($sidebar)->toContain('sidebarCompact')
        ->and($html)->toContain('sidebarHovered')
        ->and($html)->toContain('sidebarFocused')
        ->and($html)->toContain('supportsHover');
});
