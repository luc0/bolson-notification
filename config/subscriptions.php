<?php

/*
 * Subscription rules (product decisions in docs/business/strategy.md).
 */
return [
    // Days a subscription lasts when a member registers or it's renewed.
    'duration_days' => (int) env('SUBSCRIPTION_DURATION_DAYS', 30),

    // Members without an active subscription only see rentals added this many days ago or more.
    'free_delay_days' => (int) env('FREE_PLAN_DELAY_DAYS', 3),

    'expiry_reminder' => [
        // Remind this many days before the subscription expires.
        'days_before' => (int) env('EXPIRY_REMINDER_DAYS_BEFORE', 3),

        // Only remind members that opened the app in the last this many days (still using it).
        'active_within_days' => (int) env('EXPIRY_REMINDER_ACTIVE_WITHIN_DAYS', 5),
    ],
];
