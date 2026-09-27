<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Tests;

use Andriichuk\BlueSnap\BlueSnapClient;
use Andriichuk\BlueSnap\Configuration;
use Andriichuk\BlueSnap\Environment;
use Andriichuk\CashierBlueSnap\CashierBlueSnapServiceProvider;
use Andriichuk\CashierBlueSnap\Tests\Support\RecordingHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected RecordingHttpClient $http;

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [CashierBlueSnapServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cashier-bluesnap.username', 'merchant');
        $app['config']->set('cashier-bluesnap.password', 'secret');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new RecordingHttpClient();
        $factory = new HttpFactory();
        $client = new BlueSnapClient(
            new Configuration('merchant', 'secret', Environment::Sandbox),
            $this->http,
            $factory,
            $factory,
        );

        $this->app->instance(BlueSnapClient::class, $client);
    }
}
