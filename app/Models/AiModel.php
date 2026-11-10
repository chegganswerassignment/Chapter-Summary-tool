<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AiModel extends Model
{
    use HasFactory;

    public const TASK_ALL = 'all';

    public const TASK_SUMMARY = 'summary';

    public const TASK_TRANSLATION = 'translation';

    public const TASK_QUIZ = 'quiz';

    protected $fillable = [
        'provider_id',
        'display_name',
        'api_model_code',
        'context_window',
        'task_specialty',
        'is_default',
    ];

    protected static function booted(): void
    {
        static::saving(function (AiModel $model) {
            if ($model->isActiveForAllTasks() && static::otherActiveModelExists($model->exists ? $model->id : null)) {
                throw new DomainException('Only one model can be the active default for all tasks.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'context_window' => 'integer',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function isActiveForAllTasks(): bool
    {
        return $this->is_default && $this->task_specialty === self::TASK_ALL;
    }

    public static function otherActiveModelExists(?int $exceptId = null): bool
    {
        return static::query()
            ->where('is_default', true)
            ->where('task_specialty', self::TASK_ALL)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    public static function activeForAllTasks(): ?self
    {
        return static::query()
            ->where('is_default', true)
            ->where('task_specialty', self::TASK_ALL)
            ->first();
    }

    /**
     * Make this the single model used for every task, atomically clearing
     * every other default (including any legacy per-task defaults).
     */
    public function activate(): void
    {
        DB::transaction(function () {
            static::query()->where('is_default', true)->update(['is_default' => false]);

            $this->forceFill([
                'task_specialty' => self::TASK_ALL,
                'is_default' => true,
            ])->save();
        });
    }
}
