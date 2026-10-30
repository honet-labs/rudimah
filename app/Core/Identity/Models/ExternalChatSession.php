<?php

namespace App\Core\Identity\Models;

use App\Core\AI\Models\AiConversation;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalChatSession extends Model
{
    protected $connection = 'platform';
    protected $table = 'external_chat_sessions';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'channel',
        'channel_user_id',
        'user_id',
        'tenant_id',
        'conversation_id',
        'metadata',
        'last_interaction_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_interaction_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
