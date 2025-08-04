<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome')->name('home');
});


Route::get('/test', function () {
    return 'Laravel is working!';
});
