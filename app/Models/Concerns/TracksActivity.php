<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Shared activity-logging behaviour for auditable models.
 */
trait TracksActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logUnguarded()
            ->logExcept(['password', 'remember_token', 'updated_at', 'created_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName(strtolower(class_basename($this)));
    }
}
