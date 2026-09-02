<?php

namespace Database\Seeders;

use App\Modules\Instagram\Models\InstagramAutomationSetting;
use App\Modules\Instagram\Models\InstagramStorySchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class InstagramStoryAutomationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            InstagramAutomationSetting::query()->firstOrCreate(['id' => 1], ['enabled' => false]);

            foreach ([['name' => 'Pedidos abiertos', 'time' => '11:00'], ['name' => 'Menú', 'time' => '18:30']] as $schedule) {
                InstagramStorySchedule::query()->firstOrCreate(
                    ['name' => $schedule['name']],
                    ['time' => $schedule['time'], 'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'asset_id' => null, 'is_active' => false],
                );
            }
        });
    }
}
