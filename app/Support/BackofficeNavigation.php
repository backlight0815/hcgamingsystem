<?php

namespace App\Support;

class BackofficeNavigation
{
    private const DEALERSHIP_GROUPS = [
        'Overview' => ['Dashboard'],
        'Inventory & Dealership' => ['Product Centre', 'My Stock Centre', 'Place an Order', 'Recruitment Centre'],
        'Sales & Commerce' => ['Order Centre', 'Dealer Order Centre', 'My Orders', 'Financial Centre', 'Commission Centre', 'Sales Performances', 'Event Management'],
        'Storefront & Content' => ['E-Store Page', 'Storefront Content'],
        'Administration' => ['Account Management', 'Company Setting'],
    ];

    private const TRADING_GROUPS = [
        'Overview' => ['Dashboard'],
        'Signal Operations' => ['Signal Provider Desk', 'Trading Signals', 'Trading Certificates', 'My Certificates'],
        'Research & Education' => ['Trader Centre', 'Market Analyst', 'Knowledge Centre', 'Trading News', 'Trading Exams'],
        'Community & Distribution' => ['Marketing Resources', 'Community', 'Appointments', 'Notifications', 'Support Tickets', 'AI Assistant', 'Provider Accounts', 'Leaderboard'],
        'Administration' => ['Account Management', 'Company Setting'],
    ];

    public static function organize(array $menus, string $mode): array
    {
        $groups = match ($mode) {
            BackofficeExperience::DEALERSHIP => self::DEALERSHIP_GROUPS,
            BackofficeExperience::TRADING => self::TRADING_GROUPS,
            default => [],
        };
        if ($groups === []) {
            return $menus;
        }

        $items = array_values(array_filter($menus, fn (array $menu): bool => isset($menu['label'])));
        $organized = [];
        foreach ($groups as $heading => $labels) {
            $matches = [];
            foreach ($items as $index => $item) {
                if (in_array($item['label'], $labels, true)) {
                    $matches[] = $item;
                    unset($items[$index]);
                }
            }
            if ($matches !== []) {
                $organized[] = ['heading' => $heading];
                array_push($organized, ...$matches);
            }
        }
        if ($items !== []) {
            $organized[] = ['heading' => 'More tools'];
            array_push($organized, ...array_values($items));
        }

        return $organized;
    }
}
