<?php

namespace App\Models;

use App\Modules\SupportChat\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category',
        'subject',
        'description',
        'status',
        'related_id',
        'related_type',
        'closed_at',

        'last_user_message_read',
        'last_admin_message_read',
        'message_thread_id'
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [TicketStatus::OPEN->value, TicketStatus::IN_PROGRESS->value]);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', TicketStatus::CLOSED->value);
    }

    public function isOpen(): bool
    {
        return $this->status === TicketStatus::OPEN->value || $this->status === TicketStatus::IN_PROGRESS->value;
    }

    public function isClosed(): bool
    {
        return $this->status === TicketStatus::CLOSED->value;
    }
}
