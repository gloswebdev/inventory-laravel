<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Auto Sync Product Master (daily at 01:00 AM)
        $schedule->call(function () {
            try {
                app(\App\Http\Controllers\ProductController::class)->syncFromApiRaw('Scheduled Sync');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Scheduled Product Master Sync Error: ' . $e->getMessage());
            }
        })->dailyAt('01:00');

        $autoSync = \App\Models\AppSetting::get('purchase_sync_auto') === 'enabled';
        if ($autoSync) {
            $frequency = \App\Models\AppSetting::get('purchase_sync_frequency', 'daily');
            $time = \App\Models\AppSetting::get('purchase_sync_time', '02:00');
            $day = \App\Models\AppSetting::get('purchase_sync_day', 'Sunday');

            if ($frequency === 'daily') {
                $schedule->call(function () {
                    try {
                        app(\App\Http\Controllers\CostingController::class)->syncPurchaseRegisterRaw();
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Scheduled Sync Error: ' . $e->getMessage());
                    }
                })->dailyAt($time);
            } elseif ($frequency === 'weekly') {
                $days = [
                    'Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3,
                    'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6
                ];
                $dayIndex = $days[$day] ?? 0;
                $schedule->call(function () {
                    try {
                        app(\App\Http\Controllers\CostingController::class)->syncPurchaseRegisterRaw();
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Scheduled Sync Error: ' . $e->getMessage());
                    }
                })->weeklyOn($dayIndex, $time);
            }
        }

        $pricelistAutoSync = \App\Models\AppSetting::get('pricelist_sync_auto') === 'enabled';
        if ($pricelistAutoSync) {
            $frequency = \App\Models\AppSetting::get('pricelist_sync_frequency', 'daily');
            $time = \App\Models\AppSetting::get('pricelist_sync_time', '02:00');
            $day = \App\Models\AppSetting::get('pricelist_sync_day', 'Sunday');

            if ($frequency === 'daily') {
                $schedule->call(function () {
                    try {
                        app(\App\Http\Controllers\CostingController::class)->syncPricelistRaw();
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Scheduled Pricelist Sync Error: ' . $e->getMessage());
                    }
                })->dailyAt($time);
            } elseif ($frequency === 'weekly') {
                $days = [
                    'Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3,
                    'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6
                ];
                $dayIndex = $days[$day] ?? 0;
                $schedule->call(function () {
                    try {
                        app(\App\Http\Controllers\CostingController::class)->syncPricelistRaw();
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Scheduled Pricelist Sync Error: ' . $e->getMessage());
                    }
                })->weeklyOn($dayIndex, $time);
            }
        }
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'interface' => \App\Http\Middleware\EnforceInterface::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
