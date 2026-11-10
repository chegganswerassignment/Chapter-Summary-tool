import { ProcessingResultData } from '@/types';

import MarkdownContent from './MarkdownContent';
import ResultState from './ResultState';

export default function TranslationTab({ result }: { result: ProcessingResultData | undefined }) {
    const text = (result?.content?.text as string) ?? '';

    return (
        <ResultState result={result}>
            <MarkdownContent preserveLineBreaks>{text}</MarkdownContent>
        </ResultState>
    );
}
