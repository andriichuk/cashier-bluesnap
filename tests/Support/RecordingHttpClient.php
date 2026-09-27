<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Tests\Support;

use GuzzleHttp\Psr7\Response;
use LogicException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RecordingHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface> */
    private array $responses = [];

    /** @param array<string, mixed> $body */
    public function queueJson(array $body, int $status = 200, array $headers = []): void
    {
        $this->responses[] = new Response(
            $status,
            ['Content-Type' => 'application/json', ...$headers],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return array_shift($this->responses)
            ?? throw new LogicException('No fake BlueSnap response was queued.');
    }

    /** @return array<mixed> */
    public function requestJson(int $index = -1): array
    {
        $request = $index === -1 ? end($this->requests) : ($this->requests[$index] ?? false);

        if (! $request instanceof RequestInterface) {
            throw new LogicException('The requested BlueSnap request was not recorded.');
        }

        $decoded = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
