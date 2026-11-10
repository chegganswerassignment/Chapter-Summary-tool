<?php

namespace App\Rules;

use App\Models\AiModel;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class SingleActiveModel implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function __construct(private readonly ?int $ignoreId = null) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $task = $this->data['task_specialty'] ?? AiModel::TASK_ALL;

        if ($task === AiModel::TASK_ALL && AiModel::otherActiveModelExists($this->ignoreId)) {
            $fail('Another model is already the active model. Use the "Active model" selector to switch.');
        }
    }
}
