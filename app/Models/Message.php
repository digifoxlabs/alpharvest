<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'direction',
        'type',
        'whatsapp_message_id',
        'body',
        'payload',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    protected $appends = [
        'status_label',
        'status_tone',
        'status_detail',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->direction === 'inbound') {
            return 'Received';
        }

        if ($this->read_at) {
            return 'Read';
        }

        if ($this->delivered_at) {
            return 'Delivered';
        }

        if (data_get($this->payload, 'status_update.status') === 'failed' || data_get($this->payload, 'dispatched') === false) {
            return 'Failed';
        }

        if ($this->sent_at || data_get($this->payload, 'status_update.status') === 'sent') {
            return 'Sent';
        }

        return 'Queued';
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status_label) {
            'Read', 'Delivered', 'Received' => 'success',
            'Failed' => 'danger',
            default => 'warning',
        };
    }

    public function getStatusDetailAttribute(): ?string
    {
        return data_get($this->payload, 'status_update.errors.0.title')
            ?: data_get($this->payload, 'status_update.errors.0.message')
            ?: data_get($this->payload, 'response.error.message')
            ?: data_get($this->payload, 'error_message')
            ?: data_get($this->payload, 'response_body')
            ?: data_get($this->payload, 'reason');
    }
}
