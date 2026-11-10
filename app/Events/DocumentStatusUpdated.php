<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Document $document) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("documents.{$this->document->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'document.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'document_id' => $this->document->id,
            'status' => $this->document->status,
            'failure_reason' => $this->document->failure_reason,
        ];
    }
}
