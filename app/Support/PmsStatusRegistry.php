<?php

namespace App\Support;

/** Stable identifiers for PMS lifecycle states. Codes are scoped to a domain. */
final class PmsStatusRegistry
{
    public const TABLE_DOMAINS = [
        'pm_projects' => 'project', 'pm_work_items' => 'ticket', 'pm_releases' => 'release',
        'pm_cycles' => 'cycle', 'pm_global_cycles' => 'organization_cycle',
        'pm_ticket_reviews' => 'ticket_review', 'pms_profiles' => 'membership',
        'project_management_users' => 'membership', 'pms_registrations' => 'registration',
        'pm_wiki_pages' => 'wiki_page', 'pm_wiki_revisions' => 'wiki_page',
    ];

    public const DEFINITIONS = [
        'project' => [1 => 'Cancelled', 2 => 'Planned', 3 => 'Active', 4 => 'Approved', 5 => 'On Hold', 6 => 'Completed'],
        'ticket' => [
            1 => 'Cancelled', 2 => 'Planned', 3 => 'Development In Progress', 4 => 'Open',
            5 => 'Deployment', 6 => 'QAT In Progress', 7 => 'QAT Dependency', 8 => 'Reopen',
            9 => 'QAT Done', 10 => 'Production Done', 11 => 'In Progress', 12 => 'Blocked',
            13 => 'Ready for Internal Testing', 14 => 'Internal Testing', 15 => 'QAT Testing',
            16 => 'Completed', 17 => 'Reopened', 18 => 'Code Review', 19 => 'Ready for QAT',
            20 => 'New', 21 => 'Triaged', 22 => 'Assigned', 23 => 'Fixed',
            24 => 'Ready for Retest', 25 => 'Retesting', 26 => 'Verified', 27 => 'Closed',
            28 => 'Duplicate', 29 => 'Rejected', 30 => 'Resolved', 31 => 'Done', 32 => 'Released',
            33 => 'On Hold',
        ],
        'release' => [
            1 => 'Cancelled', 2 => 'Planned', 3 => 'Development', 4 => 'Internal Testing',
            5 => 'QAT Testing', 6 => 'Stabilization', 7 => 'Ready for Release',
            8 => 'On Hold', 9 => 'Approved', 10 => 'Released', 11 => 'Completed',
        ],
        'cycle' => [1 => 'Cancelled', 2 => 'Planned', 3 => 'Active', 4 => 'Closed',
            5 => 'Draft', 6 => 'Internal Testing', 7 => 'QAT', 8 => 'On Hold'],
        'organization_cycle' => [1 => 'Cancelled', 2 => 'Upcoming', 3 => 'Active', 4 => 'Completed', 5 => 'Delayed'],
        'ticket_review' => [1 => 'pending', 2 => 'approved', 3 => 'changes_requested', 4 => 'rejected'],
        'membership' => [1 => 'pending', 2 => 'active', 3 => 'inactive', 4 => 'rejected', 5 => 'suspended'],
        'registration' => [1 => 'pending', 2 => 'approved', 3 => 'inactive', 4 => 'rejected'],
        'wiki_page' => [1 => 'draft', 2 => 'published'],
        'blocker' => [1 => 'Open', 2 => 'In Progress', 3 => 'Resolved', 4 => 'Cancelled'],
    ];

    public static function definitions(string $domain): array
    {
        return self::DEFINITIONS[$domain] ?? [];
    }

    public static function code(string $domain, string|int|null $status): ?int
    {
        if (is_int($status) || (is_string($status) && ctype_digit($status))) {
            $code = (int) $status;
            return isset(self::DEFINITIONS[$domain][$code]) ? $code : null;
        }
        $value = mb_strtolower(trim((string) $status));
        foreach (self::definitions($domain) as $code => $key) {
            if (mb_strtolower($key) === $value) return $code;
        }

        return null;
    }

    public static function key(string $domain, int $code): ?string
    {
        return self::DEFINITIONS[$domain][$code] ?? null;
    }

    public static function label(string $domain, int $code): ?string
    {
        return self::key($domain, $code);
    }

    public static function isValid(string $domain, int $code): bool
    {
        return isset(self::DEFINITIONS[$domain][$code]);
    }

    public static function allowsTransition(string $domain, ?int $current, ?int $target, array $transitions): bool
    {
        if ($current === null || $target === null) return false;
        $from = self::key($domain, $current);
        if ($from === null) return false;
        foreach ($transitions as $source => $destinations) {
            if (self::code($domain, $source) !== $current) continue;
            foreach ($destinations as $destination) {
                if (self::code($domain, $destination) === $target) return true;
            }
        }
        return false;
    }
}
