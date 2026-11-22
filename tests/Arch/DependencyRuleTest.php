<?php

/*
| Fitness functions for the dependency rule in docs/architecture.md: if one of these fails,
| the change breaks the architecture (fix the dependency or record an ADR that changes the rule).
*/

const CATALOGS = ['App\Domains\Rentals', 'App\Domains\Lands', 'App\Domains\Houses'];

arch('Locations is a shared kernel that depends on no other domain')
    ->expect('App\Domains\Locations')
    ->not->toUse([
        'App\Domains\Sources', 'App\Domains\Harvesting', ...CATALOGS,
        'App\Domains\Market', 'App\Domains\Subscriptions', 'App\Domains\Members',
    ]);

arch('catalogs only depend on Locations')
    ->expect(CATALOGS)
    ->not->toUse([
        'App\Domains\Sources', 'App\Domains\Harvesting', 'App\Domains\Market',
        'App\Domains\Subscriptions', 'App\Domains\Members',
    ]);

arch('Rentals does not know Lands or Houses')
    ->expect('App\Domains\Rentals')
    ->not->toUse(['App\Domains\Lands', 'App\Domains\Houses']);

arch('Lands does not know Rentals or Houses')
    ->expect('App\Domains\Lands')
    ->not->toUse(['App\Domains\Rentals', 'App\Domains\Houses']);

arch('Houses does not know Rentals or Lands')
    ->expect('App\Domains\Houses')
    ->not->toUse(['App\Domains\Rentals', 'App\Domains\Lands']);

arch('Sources only describes sites')
    ->expect('App\Domains\Sources')
    ->not->toUse([
        'App\Domains\Harvesting', ...CATALOGS, 'App\Domains\Market',
        'App\Domains\Subscriptions', 'App\Domains\Members',
    ]);

arch('Harvesting writes the catalogs but knows nothing about notifications or analytics')
    ->expect('App\Domains\Harvesting')
    ->not->toUse(['App\Domains\Market', 'App\Domains\Subscriptions', 'App\Domains\Members']);

arch('Market only reads the catalogs')
    ->expect('App\Domains\Market')
    ->not->toUse(['App\Domains\Sources', 'App\Domains\Harvesting', 'App\Domains\Subscriptions', 'App\Domains\Members']);

arch('nothing depends on Market')
    ->expect([...CATALOGS, 'App\Domains\Locations', 'App\Domains\Sources', 'App\Domains\Harvesting', 'App\Domains\Subscriptions', 'App\Domains\Members'])
    ->not->toUse('App\Domains\Market');

arch('Subscriptions reads Rentals and Members only')
    ->expect('App\Domains\Subscriptions')
    ->not->toUse(['App\Domains\Sources', 'App\Domains\Harvesting', 'App\Domains\Lands', 'App\Domains\Houses', 'App\Domains\Market']);

arch('Members does not know the other domains')
    ->expect('App\Domains\Members')
    ->not->toUse(['App\Domains\Sources', 'App\Domains\Harvesting', ...CATALOGS, 'App\Domains\Market', 'App\Domains\Subscriptions']);

arch('domains do not depend on framework entry points')
    ->expect('App\Domains')
    ->not->toUse(['App\Http', 'App\Console']);

arch('integrations are shared clients that know no domain')
    ->expect('App\Integrations')
    ->not->toUse('App\Domains');
