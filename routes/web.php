<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('asesorias', function () {
    return Inertia::render('Asesorias');
})->name('asesorias');

Route::get('asesorias2', function () {
    return Inertia::render('Asesorias2');
})->name('asesorias2');

##Agregar Auth luego



require __DIR__.'/settings.php';
