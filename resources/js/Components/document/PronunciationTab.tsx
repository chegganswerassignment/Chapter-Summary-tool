import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

const STOPWORDS = new Set([
    'The', 'This', 'That', 'These', 'Those', 'A', 'An', 'In', 'On', 'At', 'For', 'With', 'It',
    'Chapter', 'Summary', 'However', 'Therefore', 'Also', 'Because',
]);

function extractTerms(text: string): string[] {
    const matches = text.match(/\b[A-Z][a-zA-Z-]{3,}\b/g) ?? [];
    const unique = Array.from(new Set(matches)).filter((word) => !STOPWORDS.has(word));

    return unique.slice(0, 30);
}

function speak(text: string): void {
    if (!('speechSynthesis' in window)) return;

    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'en-US';
    window.speechSynthesis.speak(utterance);
}

export default function PronunciationTab({ summaryText }: { summaryText: string }) {
    const { t } = useTranslation();
    const terms = useMemo(() => extractTerms(summaryText), [summaryText]);
    const supported = typeof window !== 'undefined' && 'speechSynthesis' in window;
    const [playing, setPlaying] = useState<string | null>(null);

    const handlePlay = (term: string) => {
        speak(term);
        setPlaying(term);
        window.setTimeout(() => setPlaying((current) => (current === term ? null : current)), 800);
    };

    if (!supported) {
        return (
            <p className="rounded-xl bg-amber-50 p-4 text-sm text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                {t('document.pronunciation.noVoice')}
            </p>
        );
    }

    return (
        <div>
            <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">
                {t('document.pronunciation.instructions')}
            </p>

            {terms.length === 0 ? (
                <p className="text-sm text-gray-400">{t('document.pronunciation.extractedTermsHint')}</p>
            ) : (
                <div className="flex flex-wrap gap-2">
                    {terms.map((term) => {
                        const isPlaying = playing === term;

                        return (
                            <button
                                key={term}
                                type="button"
                                onClick={() => handlePlay(term)}
                                className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition ${
                                    isPlaying
                                        ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300'
                                        : 'border-gray-300 text-gray-700 hover:border-brand-300 hover:bg-brand-50/50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700/50'
                                }`}
                            >
                                <svg className="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 3.75a.75.75 0 00-1.264-.546L4.703 7H3.167C2.522 7 2 7.522 2 8.167v3.666C2 12.478 2.522 13 3.167 13h1.536l4.033 3.796A.75.75 0 0010 16.25V3.75z" />
                                    <path d="M13.5 7.5a3.5 3.5 0 010 5" stroke="currentColor" strokeWidth="1.5" fill="none" strokeLinecap="round" />
                                </svg>
                                {term}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
