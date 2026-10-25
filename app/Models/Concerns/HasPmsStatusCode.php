<?php

namespace App\Models\Concerns;

use App\Support\PmsStatusRegistry;

trait HasPmsStatusCode
{
    protected static function bootHasPmsStatusCode(): void
    {
        static::saving(function ($model): void {
            $domain = $model->pmsStatusDomain();
            if (! $model->exists && $model->status === null) {
                $model->status = match ($domain) {
                    'organization_cycle' => 'Upcoming', 'membership', 'registration' => 'pending',
                    'wiki_page' => 'draft', default => 'Planned',
                };
            }
            if ($model->isDirty('status_code') && $model->status_code !== null) {
                $status = PmsStatusRegistry::key($domain, (int) $model->status_code);
                if ($status === null) throw new \InvalidArgumentException("Invalid {$domain} status code.");
                $model->status = $status;
            } elseif ($model->isDirty('status') || $model->status_code === null) {
                // Unknown historical values remain visible with a null code for audit.
                $model->status_code = PmsStatusRegistry::code($domain, $model->getAttributes()['status'] ?? null);
                if ($model->isDirty('status')) {
                    if ($model->status_code === null) {
                        throw new \InvalidArgumentException("Invalid {$domain} status.");
                    }
                }
            }
        });
    }

    abstract public function pmsStatusDomain(): string;
}
