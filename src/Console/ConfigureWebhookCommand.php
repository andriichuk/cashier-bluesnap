<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Console;

use Andriichuk\BlueSnap\BlueSnapClient;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use JsonException;

final class ConfigureWebhookCommand extends Command
{
    protected $signature = 'bluesnap:webhook
        {url? : HTTPS URL that receives BlueSnap webhooks}
        {--show : Display the current BlueSnap webhook configuration}
        {--delete : Delete every BlueSnap webhook destination}';

    protected $description = 'Read or configure the BlueSnap webhook destination';

    public function __construct(
        private readonly BlueSnapClient $client,
        private readonly Repository $config,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('show') === true) {
            try {
                $this->line(json_encode(
                    $this->client->webhookConfigurations()->retrieve()->json(),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                ));
            } catch (JsonException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        if ($this->option('delete') === true) {
            if (! $this->confirm('Delete every BlueSnap webhook destination?', false)) {
                $this->components->warn('No webhook configuration was changed.');

                return self::SUCCESS;
            }

            $this->client->webhookConfigurations()->delete();
            $this->components->info('BlueSnap webhook destinations deleted.');

            return self::SUCCESS;
        }

        $url = $this->argument('url');

        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false || ! str_starts_with($url, 'https://')) {
            $this->components->error('Provide a valid HTTPS webhook URL.');

            return self::INVALID;
        }

        $configuredEvents = $this->config->get('cashier-bluesnap.webhooks.events', []);
        $events = [];

        if (is_array($configuredEvents)) {
            foreach ($configuredEvents as $name => $enabled) {
                if (is_string($name) && is_bool($enabled)) {
                    $events[$name] = $enabled;
                }
            }
        }

        $this->client->webhookConfigurations()->update([
            'ipnDestinations' => [[
                'ipnUrl' => $url,
                ...$events,
            ]],
            'ensureNotificationReceipt' => true,
            'receiveAffiliateNotifications' => false,
        ]);

        $this->components->info('BlueSnap webhook destination configured.');
        $this->components->warn('Enable Security Header in the BlueSnap portal and copy its key to BLUESNAP_WEBHOOK_SECRET.');

        return self::SUCCESS;
    }
}
