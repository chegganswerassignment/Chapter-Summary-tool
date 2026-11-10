import { useTranslation } from 'react-i18next';

import { ProcessingResultData } from '@/types';

import MarkdownContent from './MarkdownContent';
import ResultState from './ResultState';

interface Props {
    result: ProcessingResultData | undefined;
    originalWordCount: number | null;
}

export default function SummaryTab({ result, originalWordCount }: Props) {
    const { t } = useTranslation();
    const text = (result?.content?.text as string) ?? '';
    const summaryWordCount = text ? text.trim().split(/\s+/).length : 0;

    return (
        <ResultState result={result}>
            {originalWordCount !== null && summaryWordCount > 0 && (
                <div className="mb-4 inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">
                    {t('document.wordReduction', {
                        original: originalWordCount.toLocaleString(),
                        summarized: summaryWordCount.toLocaleString(),
                    })}
                </div>
            )}
            <MarkdownContent>{text}</MarkdownContent>
        </ResultState>
    );
}
