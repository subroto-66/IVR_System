<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IvrCallLog extends Model
{
    use HasFactory;

    protected $table = 'ivr_call_logs';

    protected $fillable = [
        'call_sid',
        'from_number',
        'to_number',
        'direction',
        'status',
        'selected_option',
        'duration',
        'started_at',
        'ended_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration' => 'integer',
    ];

    /**
     * Record an event in the call's metadata audit trail.
     */
    public function recordEvent(string $event, array $data = []): void
    {
        $metadata = $this->metadata ?? [];
        $events = $metadata['events'] ?? [];

        $events[] = [
            'event' => $event,
            'time' => Carbon::now()->toIso8601String(),
            'data' => $data,
        ];

        $metadata['events'] = $events;
        $this->metadata = $metadata;
        $this->save();
    }

    /**
     * Get a human-readable duration string.
     */
    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration === null) {
            return '-';
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        if ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $seconds);
        }

        return sprintf('%ds', $seconds);
    }
}
