export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role: 'admin' | 'student';
    locale: 'en' | 'ar';
}

export interface FlashMessages {
    success?: string | null;
    error?: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    locale: string;
    flash: FlashMessages;
};

export type DocumentStatus =
    | 'uploaded'
    | 'extracting'
    | 'queued'
    | 'processing'
    | 'completed'
    | 'failed';

export interface DocumentSummary {
    id: string;
    original_name: string;
    status: DocumentStatus;
    file_size: number;
    created_at: string;
}

export type ResultType = 'summary' | 'translation' | 'quiz';

export type ResultStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface ProcessingResultModelInfo {
    display_name: string;
    provider: string;
}

export interface ProcessingResultData {
    id: string;
    result_type: ResultType;
    status: ResultStatus;
    content: Record<string, unknown> | null;
    error_message: string | null;
    model: ProcessingResultModelInfo | null;
}

export interface DocumentDetail extends DocumentSummary {
    failure_reason: string | null;
    original_word_count: number | null;
    results: ProcessingResultData[];
}

export interface AdminAiModel {
    id: number;
    display_name: string;
    api_model_code: string;
    context_window: number;
    is_active: boolean;
}

export interface AdminAiProvider {
    id: number;
    name: string;
    slug: string;
    base_url: string | null;
    is_active: boolean;
    has_api_key: boolean;
    models: AdminAiModel[];
}
