import { z } from 'zod';

export const MAX_UPLOAD_MB = 20;
const MAX_UPLOAD_BYTES = MAX_UPLOAD_MB * 1024 * 1024;

const ALLOWED_MIME_TYPES = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/msword',
];

export const documentUploadSchema = z.object({
    file: z
        .instanceof(File, { message: 'documents.errors.fileRequired' })
        .refine((file) => ALLOWED_MIME_TYPES.includes(file.type), {
            message: 'documents.errors.fileType',
        })
        .refine((file) => file.size <= MAX_UPLOAD_BYTES, {
            message: 'documents.errors.fileSize',
        }),
});

export type DocumentUploadInput = z.infer<typeof documentUploadSchema>;

export const aiProviderSchema = z.object({
    name: z.string().min(1).max(255),
    slug: z
        .string()
        .min(1)
        .max(100)
        .regex(/^[a-z0-9_-]+$/i, 'Slug may only contain letters, numbers, dashes and underscores'),
    base_url: z.string().url().max(255).optional().or(z.literal('')),
    api_key: z.string().max(2048).optional().or(z.literal('')),
    is_active: z.boolean().default(true),
});

export type AiProviderInput = z.infer<typeof aiProviderSchema>;

export const aiModelSchema = z.object({
    provider_id: z.number().int().positive(),
    display_name: z.string().min(1).max(255),
    api_model_code: z.string().min(1).max(255),
    context_window: z.number().int().min(1024).max(5_000_000),
});

export type AiModelInput = z.infer<typeof aiModelSchema>;

const quizOptionSchema = z.array(z.string()).min(2);

export const quizQuestionSchema = z.object({
    question: z.string().min(1),
    options: quizOptionSchema,
    correct_index: z.number().int().min(0),
    explanation: z.string().optional().default(''),
});

export const quizContentSchema = z.object({
    questions: z.array(quizQuestionSchema).min(1),
});

export type QuizContent = z.infer<typeof quizContentSchema>;

export const summaryContentSchema = z.object({
    text: z.string().min(1),
});

export type SummaryContent = z.infer<typeof summaryContentSchema>;
