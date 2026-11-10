<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingResult extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'document_id',
        'model_id',
        'result_type',
        'status',
        'content_json',
        'prompt_tokens',
        'completion_tokens',
        'processing_time_ms',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'processing_time_ms' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id');
    }
}
