<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Contract;
use App\Models\DesignTask;
use App\Models\Location;
use App\Models\Order;
use App\Models\User;
use App\Observers\CategoryObserver;
use App\Observers\ContractObserver;
use App\Observers\DesignTaskObserver;
use App\Observers\LocationObserver;
use App\Observers\OrderObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::policy(\Spatie\Permission\Models\Role::class, \App\Policies\RolePolicy::class);

        $this->initGoogleDrive();

        User::observe(UserObserver::class);
        DesignTask::observe(DesignTaskObserver::class);
        Order::observe(OrderObserver::class);
        Category::observe(CategoryObserver::class);
        Location::observe(LocationObserver::class);
        Contract::observe(ContractObserver::class);

        $this->syncSystemSettings();
    }

    private function syncSystemSettings(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
                config(['filesystems.max_file_size' => \App\Models\SystemSetting::getMaxFileSize()]);
            }
        } catch (\Throwable) {
            // Silently fallback if database is not available
        }
    }

    private function initGoogleDrive()
    {
        try {
            \Storage::extend('google', function ($app, $config) {
                $options = [];

                if (! empty($config['teamDriveId'] ?? null)) {
                    $options['teamDriveId'] = $config['teamDriveId'];
                }

                if (! empty($config['sharedFolderId'] ?? null)) {
                    $options['sharedFolderId'] = $config['sharedFolderId'];
                }

                $client = new \Google\Client;
                $client->setClientId($config['clientId']);
                $client->setClientSecret($config['clientSecret']);
                $client->refreshToken($config['refreshToken']);

                $service = new \Google\Service\Drive($client);
                $adapter = new \Masbug\Flysystem\GoogleDriveAdapter($service, $config['folder'] ?? '/', $options);
                $driver = new \League\Flysystem\Filesystem($adapter);

                return new \Illuminate\Filesystem\FilesystemAdapter($driver, $adapter);
            });
        } catch (\Exception $e) {
            // your exception handling logic
        }
    }
}
