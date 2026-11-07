<?php

namespace App\Domains\Sources\Data;

use App\Domains\Sources\Enums\SourceType;
use InvalidArgumentException;

/**
 * A site we get rentals from (see config/sources.php).
 */
final readonly class Source
{
    public function __construct(
        public string $key,
        public string $name,
        public SourceType $type,
        public string $listingUrl,
        public string $linkBaseUrl,
        public ?string $waitFor = null,
        public ?string $defaultCity = null,
    ) {}

    /**
     * @return self[]
     */
    public static function all(): array
    {
        return collect(config('sources.sites'))
            ->map(fn (array $site, string $key) => self::fromConfig($key, $site))
            ->values()
            ->all();
    }

    public static function find(string $key): self
    {
        $site = config("sources.sites.{$key}");
        if ($site === null) {
            throw new InvalidArgumentException("Unknown source [{$key}].");
        }

        return self::fromConfig($key, $site);
    }

    public static function findByListingUrl(string $listingUrl): ?self
    {
        return collect(self::all())->first(fn (self $source) => $source->listingUrl === $listingUrl);
    }

    private static function fromConfig(string $key, array $site): self
    {
        return new self(
            key: $key,
            name: $site['name'],
            type: SourceType::from($site['type'] ?? SourceType::Agency->value),
            listingUrl: $site['listing_url'],
            linkBaseUrl: $site['link_base_url'],
            waitFor: $site['wait_for'] ?? null,
            defaultCity: $site['default_city'] ?? null,
        );
    }
}
