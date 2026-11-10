import ReactMarkdown, { Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';

import { isRtlText } from '@/lib/textDirection';

interface Props {
    children: string;
    /** Render single newlines inside a paragraph as line breaks (the PDF export does this for translations). */
    preserveLineBreaks?: boolean;
}

/*
 * Safety: react-markdown does NOT render raw HTML unless rehype-raw is added (it is not), and
 * skipHtml drops it entirely, like the backend's html_input=strip. Its default URL transform
 * also drops javascript:/data: links. This text comes from an AI
 * model reading an uploaded file, so images are also replaced by their alt text to stop
 * a crafted document from making the student's browser fetch remote URLs.
 */
const components: Components = {
    a: ({ node: _node, ...props }) => <a {...props} target="_blank" rel="noopener noreferrer nofollow" />,
    img: ({ alt }) => (alt ? <span>{alt}</span> : null),
    table: ({ node: _node, ...props }) => (
        <div className="overflow-x-auto">
            <table {...props} />
        </div>
    ),
};

export default function MarkdownContent({ children, preserveLineBreaks = false }: Props) {
    const rtl = isRtlText(children);

    return (
        <div
            dir={rtl ? 'rtl' : 'ltr'}
            lang={rtl ? 'ar' : undefined}
            className={[
                'prose prose-sm max-w-none text-gray-800 dark:prose-invert dark:text-gray-100',
                'prose-headings:font-bold prose-headings:text-brand-700 dark:prose-headings:text-brand-400',
                'prose-a:text-brand-600 dark:prose-a:text-brand-400',
                'prose-strong:text-gray-900 dark:prose-strong:text-white',
                'prose-blockquote:border-brand-400 prose-blockquote:bg-brand-50/60 prose-blockquote:py-0.5 dark:prose-blockquote:bg-brand-900/20',
                'prose-th:bg-brand-50 dark:prose-th:bg-brand-900/20',
                'prose-code:rounded prose-code:bg-gray-100 prose-code:px-1 prose-code:font-normal prose-code:before:content-none prose-code:after:content-none dark:prose-code:bg-gray-700',
                '[&_blockquote_p]:before:content-none [&_blockquote_p]:after:content-none',
                preserveLineBreaks ? '[&_p]:whitespace-pre-line' : '',
            ].join(' ')}
        >
            <ReactMarkdown remarkPlugins={[remarkGfm]} components={components} skipHtml>
                {children}
            </ReactMarkdown>
        </div>
    );
}
