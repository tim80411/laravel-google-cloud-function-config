<?php

namespace Rverrips\LaravelGoogleCloudFunctionConfig;

use RuntimeException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class GoogleCloudFunctionConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $isRunningGoogleCloudFunction = (getenv('K_SERVICE') !== null);

        // Laravel Mix URL for assets stored on External Storage (like Google Cloud Storage or Amazon S3)
        $mixAssetUrl = $_SERVER['MIX_ASSET_URL'] ?? null;
        if ($mixAssetUrl) {
            Config::set('app.mix_url', $mixAssetUrl);
        }

        // The rest below is specific to the Google Cloud Function environment
        if (! $isRunningGoogleCloudFunction) {
            return;
        }

        // Set base Storage path to tmp (writable) directory in Google Cloud Function
        Config::set('storagePath', '/tmp/storage');

        // We change Laravel's default log destination to stderr
        $logDriver = Config::get('logging.default');
        if ($logDriver === 'stack') {
            Config::set('logging.default', 'stderr');
        }

        // Store compiled views in `/tmp` because they are generated at runtime
        // and `/tmp` is the only writable directory
        Config::set('view.compiled', '/tmp/storage/framework/views');

        // Sessions cannot be stored to files, so we use cookies by default instead
        $sessionDriver = Config::get('session.driver');
        if ($sessionDriver === 'file') {
            Config::set('session.driver', 'cookie');
        }

        // You really should set a random string as APP_KEY in the ENV.YML to load into Runtime Variables
        // If you don't we'll generate a new one with each runtime which is not desirable.
        $key = Config::get('app.key');
        if ($key === null) {
            Config::set('app.key', 'base64:'.base64_encode(
                Encrypter::generateKey(Config::get('config.app.cipher'))
            ));
        }


        // The native Laravel storage directory is read-only, we move the cache to /tmp
        // to avoid errors. If you want to actively use the cache, it will be best to use
        // another driver instead.
        Config::set('cache.stores.file.path', '/tmp/storage/framework/cache');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $compiledViewDirectory = Config::get('view.compiled', '');

        // Make sure the config is correctly declared. If not, Config::get will return an empty string
        if (empty($compiledViewDirectory)) {
            throw new RuntimeException('Configuration `view.compiled` is not declared');
        }

        // Make sure the declared view.compiled is a string
        if (! is_string($compiledViewDirectory)) {
            throw new RuntimeException('Configuration `view.compiled` must be a valid string');
        }

        // Make sure the directory for compiled views exist
        if (! is_dir($compiledViewDirectory)) {
            // The directory doesn't exist: let's create it, else Laravel will not create it automatically
            // and will fail with an error
            if (! mkdir($compiledViewDirectory, 0755, true) && ! is_dir($compiledViewDirectory)) {
                throw new RuntimeException(sprintf('Directory "%s" cannot be created', $compiledViewDirectory));
            }
        }

        $this->publishes([
            __DIR__ . '/../index.php' => $this->app->basePath('index.php'),
            __DIR__ . '/../.gcloudignore' => $this->app->basePath('.gcloudignore'),
        ], 'laravel-assets');
    }
}
