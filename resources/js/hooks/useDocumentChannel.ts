import { useEffect } from 'react';

import { getEcho } from '@/lib/echo';

interface DocumentStatusPayload {
    document_id: string;
    status: string;
    failure_reason: string | null;
}

/** Identifiers and status only: the result content is fetched over HTTP (it can exceed Reverb's message limit). */
interface DocumentResultPayload {
    result_id: string;
    document_id: string;
    result_type: string;
    status: string;
}

interface UseDocumentChannelOptions {
    onStatusUpdated?: (payload: DocumentStatusPayload) => void;
    onResultReady?: (payload: DocumentResultPayload) => void;
}

export function useDocumentChannel(documentId: string, options: UseDocumentChannelOptions): void {
    const { onStatusUpdated, onResultReady } = options;

    useEffect(() => {
        const echo = getEcho();
        const channel = echo.private(`documents.${documentId}`);

        if (onStatusUpdated) {
            channel.listen('.document.status.updated', onStatusUpdated);
        }

        if (onResultReady) {
            channel.listen('.document.result.ready', onResultReady);
        }

        return () => {
            echo.leave(`documents.${documentId}`);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [documentId]);
}
