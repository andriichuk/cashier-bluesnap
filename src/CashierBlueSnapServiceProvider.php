<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap;

use Andriichuk\BlueSnap\BlueSnapClient;
use Andriichuk\BlueSnap\Configuration;
use Andriichuk\BlueSnap\Environment;
use Andriichuk\CashierBlueSnap\Console\ConfigureWebhookCommand;
use Andriichuk\CashierBlueSnap\Contracts\WebhookSignatureVerifier;
use Andriichuk\CashierBlueSnap\Webhooks\HmacWebhookSignatureVerifier;
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

        $this->app->singleton(WebhookSignatureVerifier::class, HmacWebhookSignatureVerifier::class);

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

        if (is_string($models['webhook_event'] ?? null) && is_a($models['webhook_event'], WebhookEvent::class, true)) {
            Cashier::useWebhookEventModel($models['webhook_event']);
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([ConfigureWebhookCommand::class]);

        $this->publishes([
            __DIR__.'/../config/cashier-bluesnap.php' => $this->app->configPath('cashier-bluesnap.php'),
        ], 'cashier-bluesnap-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'cashier-bluesnap-migrations');
    }
}
