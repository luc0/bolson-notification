<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Harvesting\Data\ListingMatch;
use App\Domains\Harvesting\Enums\MatchMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Finds the stored listing a scraped item belongs to, so a listing whose URL changed isn't stored (and notified) as new:
 * by URL first, then by the agency's own id within the same source.
 */
class ListingMatcher
{
    /**
     * Only listings still published or unpublished recently (harvesting.relisting_after_days) are matched:
     * one that comes back later is a new availability, stored and notified as a new listing.
     *
     * @param  class-string<Model>  $modelClass  Rental, Land or House (same `source`, `site_path`, `external_id`,
     *                                           `active` and `deactivated_at` columns).
     */
    public function find(string $modelClass, string $link, ?string $externalId, string $siteUrl): ?ListingMatch
    {
        $byLink = $this->recentlyPublished($modelClass)->where('source', $link)->first();
        if ($byLink !== null) {
            return new ListingMatch($byLink, MatchMethod::Url);
        }

        if ($externalId === null) {
            return null;
        }

        $byExternalId = $this->recentlyPublished($modelClass)
            ->where('site_path', $siteUrl)
            ->where('external_id', $externalId)
            ->first();
        if ($byExternalId === null) {
            return null;
        }

        return new ListingMatch($byExternalId, MatchMethod::ExternalId);
    }

    /**
     * Active first, then the newest: a listing may be stored more than once (relisted, or before external_id existed).
     *
     * @param  class-string<Model>  $modelClass
     */
    private function recentlyPublished(string $modelClass): Builder
    {
        $relistingCutoff = now()->subDays((int) config('harvesting.relisting_after_days'));

        return $modelClass::query()
            ->where(fn (Builder $query) => $query
                ->where('active', true)
                ->orWhere('deactivated_at', '>=', $relistingCutoff))
            ->orderByDesc('active')
            ->orderByDesc('id');
    }
}
