<?php

namespace App\Domains\Sources\Data;

use App\Domains\Sources\Enums\SourceType;
use InvalidArgumentException;

/**
 * A site we get rentals or lands from (see config/sources.php).
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
     * Rental sources.
     *
     * @return self[]
     */
    public static function rentals(): array
    {
        return self::fromConfigList(config('sources.rental_sites', []));
    }

    /**
     * Land sources (lands for sale).
     *
     * @return self[]
     */
    public static function lands(): array
    {
        return self::fromConfigList(config('sources.land_sites', []));
    }

    public static function findRental(string $key): self
    {
        $site = config("sources.rental_sites.{$key}");
        if ($site === null) {
            throw new InvalidArgumentException("Unknown rental source [{$key}].");
        }

        return self::fromConfig($key, $site);
    }

    public static function findRentalByListingUrl(string $listingUrl): ?self
    {
        return collect(self::rentals())->first(fn (self $source) => $source->listingUrl === $listingUrl);
    }

    /**
     * @return self[]
     */
    private static function fromConfigList(array $sites): array
    {
        return collect($sites)
            ->map(fn (array $site, string $key) => self::fromConfig($key, $site))
            ->values()
            ->all();
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
