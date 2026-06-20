<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
 * Reproduces the real-request behaviour (full middleware stack + routing) of a
 * signed-in landlord opening the customer /app panel. It must land on /admin,
 * never a hard 403 — exercised over HTTP, not with a hand-set current panel.
 */
it('sends a signed-in landlord who opens /app to /admin, not a 403', function () {
    $admin = User::query()->create([
        'name' => 'Boss', 'email' => 'boss@central.test', 'password' => Hash::make('secret'),
    ]); // central user (no role column) → may only reach /admin

    $this->actingAs($admin)->get('/app')->assertRedirect('/admin');
});

it('still sends an anonymous visitor of /app to the sign-in page, not a 403', function () {
    $this->get('/app')->assertRedirect('/app/login');
});
