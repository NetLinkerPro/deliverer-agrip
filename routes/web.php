<?php


use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::domain(config('deliverer-agrip.domain'))
    ->name('deliverer-agrip.')
    ->prefix(config('deliverer-agrip.prefix'))
    ->middleware(['web'])
    ->group(function () {

        # Assets AWES
        Route::get('assets/images/{filename}', config('deliverer-agrip.controllers.assets') . '@getImage')
            ->where('filename', '(.*)')
            ->name('assets.image');
        Route::get('assets/{module}/{type}/{filename}', config('deliverer-agrip.controllers.assets') . '@getAwes')->name('assets.awes');
    });

Route::domain(config('deliverer-agrip.domain'))
    ->name('deliverer-agrip.')
    ->prefix(config('deliverer-agrip.prefix'))
    ->middleware(['web', 'auth'])
    ->group(function () {

        # Dashboard
        Route::prefix('/')->as('dashboard.')->group(function () {
            Route::get('/', config('deliverer-agrip.controllers.dashboard') . '@index')->name('index');
        });

        # Introductions
        Route::prefix('introductions')->as('introductions.')->group(function () {
            Route::get('/', config('deliverer-agrip.controllers.introductions') . '@index')->name('index');
        });

        # Configurations
        Route::prefix('configurations')->as('configurations.')->group(function () {
            Route::get('/', config('deliverer-agrip.controllers.configurations') . '@index')->name('index');
            Route::get('scope', config('deliverer-agrip.controllers.configurations') . '@scope')->name('scope');
            Route::post('store', config('deliverer-agrip.controllers.configurations') . '@store')->name('store');
            Route::patch('{id?}', config('deliverer-agrip.controllers.configurations') . '@update')->name('update');
            Route::delete('{id?}', config('deliverer-agrip.controllers.configurations') . '@destroy')->name('destroy');
        });

        # Settings
        Route::prefix('settings')->as('settings.')->group(function () {
            Route::get('/', config('deliverer-agrip.controllers.settings') . '@index')->name('index');
            Route::patch('/', config('deliverer-agrip.controllers.settings') . '@update')->name('update');
            Route::get('assigned-ean', config('deliverer-agrip.controllers.settings') . '@assignedEan')->name('assigned_ean');
        });

        # Formatters
        Route::prefix('formatters')->as('formatters.')->group(function () {
            Route::get('/', config('deliverer-agrip.controllers.formatters') . '@index')->name('index');
            Route::get('scope', config('deliverer-agrip.controllers.formatters') . '@scope')->name('scope');
            Route::post('store', config('deliverer-agrip.controllers.formatters') . '@store')->name('store');
            Route::patch('{id?}', config('deliverer-agrip.controllers.formatters') . '@update')->name('update');
            Route::delete('{id?}', config('deliverer-agrip.controllers.formatters') . '@destroy')->name('destroy');
        });

        # Formatter ranges
        Route::prefix('formatter-ranges')->as('formatter_ranges.')->group(function () {
            Route::get('/', config('deliverer-agrip.controllers.formatter_ranges') . '@index')->name('index');
            Route::get('scope', config('deliverer-agrip.controllers.formatter_ranges') . '@scope')->name('scope');
            Route::post('store', config('deliverer-agrip.controllers.formatter_ranges') . '@store')->name('store');
            Route::patch('{id?}', config('deliverer-agrip.controllers.formatter_ranges') . '@update')->name('update');
            Route::delete('{id?}', config('deliverer-agrip.controllers.formatter_ranges') . '@destroy')->name('destroy');
            Route::get('ranges', config('deliverer-agrip.controllers.formatter_ranges') . '@ranges')->name('ranges');
            Route::get('actions', config('deliverer-agrip.controllers.formatter_ranges') . '@actions')->name('actions');
        });

        # Formatter ranges
        Route::prefix('formatter-ranges')->as('formatter_ranges.')->group(function () {
            Route::get('/', config('deliverer-agrip.controllers.formatter_ranges') . '@index')->name('index');
            Route::get('scope', config('deliverer-agrip.controllers.formatter_ranges') . '@scope')->name('scope');
            Route::post('store', config('deliverer-agrip.controllers.formatter_ranges') . '@store')->name('store');
            Route::patch('{id?}', config('deliverer-agrip.controllers.formatter_ranges') . '@update')->name('update');
            Route::delete('{id?}', config('deliverer-agrip.controllers.formatter_ranges') . '@destroy')->name('destroy');
            Route::get('ranges', config('deliverer-agrip.controllers.formatter_ranges') . '@ranges')->name('ranges');
            Route::get('actions', config('deliverer-agrip.controllers.formatter_ranges') . '@actions')->name('actions');
        });

        # Categories
        Route::prefix('categories')->as('categories.')->group(function () {
            Route::get('/', config('deliverer-agrip.controllers.categories') . '@index')->name('index');
            Route::get('scope', config('deliverer-agrip.controllers.categories') . '@scope')->name('scope');
            Route::post('store', config('deliverer-agrip.controllers.categories') . '@store')->name('store');
            Route::patch('{id?}', config('deliverer-agrip.controllers.categories') . '@update')->name('update');
            Route::delete('{id?}', config('deliverer-agrip.controllers.categories') . '@destroy')->name('destroy');
        });
    });