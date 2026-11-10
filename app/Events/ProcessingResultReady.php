<?php

namespace App\Events;

use App\Models\ProcessingResult;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProcessingResultReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly ProcessingResult $result) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("documents.{$this->result->document_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'document.result.ready';
    }

    /**
     * Identifiers and status only. The result content (summary, translation, quiz) can far exceed
     * Reverb's max_message_size, so clients re-fetch it over HTTP when they receive this event.
     */
    public function broadcastWith(): array
    {
        return [
            'result_id' => $this->result->id,
            'document_id' => $this->result->document_id,
            'result_type' => $this->result->result_type,
            'status' => $this->result->status,
        ];
    }
}
