<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class MaintenanceMode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:maintenance {status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Команда для перевода приложения в режим обслуживания и наоборот';

    public function handle()
    {
        $status = $this->argument('status');

        if(!in_array($status, ['ON', 'OFF'])) {
            $this->error('Передан неверный статус приложения');
            return 0;
        }

        if ($this->setEnvVariable("MAINTENANCE_MODE", $status)) {
            $this->info("Приложение переведено в статус $status");
            Artisan::call('optimize');
            $this->info('Команда php artisan optimize выполнена');
        } else {
            $this->error('Ошибка изменения статуса приложения');
        }

        return 0;
    }

    /**
     * Функция для изменения значения переменной в .env файле
     */
    protected function setEnvVariable($key, $value)
    {
        $envPath = base_path('.env');
        if (File::exists($envPath)) {
            $envContent = File::get($envPath);
            if (strpos($envContent, "{$key}=") !== false) {
                $envContent = preg_replace(
                    "/^{$key}=.*/m",
                    "{$key}={$value}",
                    $envContent
                );
            } else {
                $envContent .= "\n{$key}={$value}\n";
            }
            File::put($envPath, $envContent);
            return true;
        }
        return false;
    }
}
