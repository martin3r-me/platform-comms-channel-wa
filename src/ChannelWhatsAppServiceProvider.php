<?php

namespace Platform\Comms\ChannelWhatsApp;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Comms\Registry\ChannelRegistry;
use Platform\Comms\Registry\ChannelProviderRegistry;
use Platform\Comms\ChannelWhatsApp\Services\WhatsAppChannelProvider;

class ChannelWhatsAppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        ChannelRegistry::addRegistrar(
            ChannelWhatsAppRegistrar::class
        );

        ChannelProviderRegistry::addProvider('whatsapp', WhatsAppChannelProvider::class);

        // Konfiguration
        $this->publishes([
            __DIR__ . '/../config/channel-whatsapp.php' => config_path('channel-whatsapp.php'),
        ], 'channel-whatsapp-config');

        $this->mergeConfigFrom(
            __DIR__ . '/../config/channel-whatsapp.php',
            'channel-whatsapp'
        );

        // Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'channel-whatsapp-migrations');

        // Views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'comms-channel-whatsapp');
        $this->registerLivewireComponents();

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/channel-whatsapp'),
        ], 'channel-whatsapp-views');

        // Routen (für Webhooks)
        $this->loadRoutesFrom(__DIR__ . '/../routes/webhook.php');

        // Routen (für OAuth)
        $this->loadRoutesFrom(__DIR__ . '/../routes/oauth.php');
    }

    public function register(): void
    {
        
    }

    protected function registerLivewireComponents(): void
    {
        $baseDir = __DIR__ . '/Http/Livewire';
        $baseNamespace = 'Platform\\Comms\\ChannelWhatsApp\\Http\\Livewire';
        $prefix = 'comms-channel-whatsapp';

        if (!is_dir($baseDir)) {
            return;
        }

        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($rii as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $alias = $prefix . '.' . Str::kebab(str_replace(DIRECTORY_SEPARATOR, '-', pathinfo($relativePath, PATHINFO_DIRNAME) . '/' . $file->getBasename('.php')));
            $alias = rtrim($alias, '-');

            Livewire::component($alias, $class);
        }
    }
}

