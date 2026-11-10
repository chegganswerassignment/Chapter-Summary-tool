<?php

use App\Models\Document;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('documents.{documentId}', function ($user, string $documentId) {
    $document = Document::find($documentId);

    return $document !== null && ($document->user_id === $user->id || $user->isAdmin());
});
