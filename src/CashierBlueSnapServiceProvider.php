<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap;

use Andriichuk\BlueSnap\BlueSnapClient;
use Andriichuk\BlueSnap\Configuration;
use Andriichuk\BlueSnap\Environment;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class CashierBlueSnapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cashier-bluesnap.php', 'cashier-bluesnap');

        $this->app->singleton(BlueSnapClient::class, function (Application $app): BlueSnapClient {
            $config = $app->make('config')->get('cashier-bluesnap');

            if (! is_array($config)) {
                throw new InvalidArgumentException('The cashier-bluesnap configuration is invalid.');
            }

            $environmentName = is_string($config['environment'] ?? null)
                ? $config['environment']
                : 'sandbox';
            $environment = match ($environmentName) {
                'sandbox' => Environment::Sandbox,
                'production' => Environment::Production,
                default => throw new InvalidArgumentException('BLUESNAP_ENVIRONMENT must be sandbox or production.'),
            };

            $factory = new HttpFactory();

            return new BlueSnapClient(
                new Configuration(
                    username: is_string($config['username'] ?? null) ? $config['username'] : '',
                    password: is_string($config['password'] ?? null) ? $config['password'] : '',
                    environment: $environment,
                    apiVersion: is_string($config['api_version'] ?? null) ? $config['api_version'] : '3.0',
                    userAgent: 'andriichuk/cashier-bluesnap',
                ),
                new Client(),
                $factory,
                $factory,
            );
        });

        /** @var array<string, mixed> $models */
        $models = config('cashier-bluesnap.models', []);

        if (is_string($models['customer'] ?? null) && is_a($models['customer'], Customer::class, true)) {
            Cashier::useCustomerModel($models['customer']);
        }

        if (is_string($models['subscription'] ?? null) && is_a($models['subscription'], Subscription::class, true)) {
            Cashier::useSubscriptionModel($models['subscription']);
        }

        if (is_string($models['transaction'] ?? null) && is_a($models['transaction'], Transaction::class, true)) {
            Cashier::useTransactionModel($models['transaction']);
        }
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/cashier-bluesnap.php' => $this->app->configPath('cashier-bluesnap.php'),
        ], 'cashier-bluesnap-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'cashier-bluesnap-migrations');
    }
}
