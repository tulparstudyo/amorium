<?php namespace AmoriumCms\Shop\Classes\Import;

use AmoriumCms\Shop\Classes\Import\Adapters\TrendyolAdapter;
use AmoriumCms\Shop\Classes\Import\Clients\TrendyolClient;

/**
 * SourceRegistry picks the client and adapter from the product link's domain.
 * A new shop is one row: the hosts it answers, and the pair that reads it.
 */
class SourceRegistry
{
    /**
     * @var array<int, array{hosts: string[], client: class-string, adapter: class-string}>
     */
    protected array $sources = [
        [
            'hosts' => ['trendyol.com'],
            'client' => TrendyolClient::class,
            'adapter' => TrendyolAdapter::class,
        ],
    ];

    /**
     * match
     */
    public function match(string $url): Source
    {
        $host = $this->host($url);

        foreach ($this->sources as $source) {
            foreach ($source['hosts'] as $candidate) {
                if ($host === $candidate || str_ends_with($host, '.' . $candidate)) {
                    return new Source(new $source['client'], new $source['adapter']);
                }
            }
        }

        throw new ImportException(__('No product importer is registered for :host.', ['host' => $host]));
    }

    /**
     * host
     */
    protected function host(string $url): string
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new ImportException(__('The product link is not a valid http address.'));
        }

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
