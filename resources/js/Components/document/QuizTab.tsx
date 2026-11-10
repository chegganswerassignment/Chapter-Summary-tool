import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/Components/ui/Button';
import { quizContentSchema } from '@/lib/schemas';
import { ProcessingResultData } from '@/types';

import ResultState from './ResultState';

const OPTION_LETTERS = ['A', 'B', 'C', 'D', 'E', 'F'];

export default function QuizTab({ result }: { result: ProcessingResultData | undefined }) {
    const { t } = useTranslation();
    const [answers, setAnswers] = useState<Record<number, number>>({});
    const [submitted, setSubmitted] = useState(false);

    const parsed = useMemo(() => {
        if (!result?.content) return null;
        const outcome = quizContentSchema.safeParse(result.content);
        return outcome.success ? outcome.data : null;
    }, [result]);

    if (result?.status === 'completed' && !parsed) {
        return (
            <div className="rounded-xl bg-red-50 p-6 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">
                {t('document.failedResult')}
            </div>
        );
    }

    const questions = parsed?.questions ?? [];
    const score = questions.reduce(
        (total, q, index) => total + (answers[index] === q.correct_index ? 1 : 0),
        0,
    );

    return (
        <ResultState result={result}>
            <div className="space-y-6">
                {questions.map((question, index) => (
                    <div
                        key={index}
                        className="rounded-xl border border-gray-200 p-5 dark:border-gray-700"
                    >
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">
                            {t('document.quiz.question', { number: index + 1, total: questions.length })}
                        </p>
                        <p className="mb-4 font-medium text-gray-900 dark:text-gray-100">
                            {question.question}
                        </p>
                        <div className="space-y-2">
                            {question.options.map((option, optionIndex) => {
                                const isSelected = answers[index] === optionIndex;
                                const isCorrect = optionIndex === question.correct_index;
                                let stateClasses =
                                    'border-gray-200 dark:border-gray-600 hover:border-brand-300 hover:bg-brand-50/50 dark:hover:bg-brand-900/10';
                                let letterClasses = 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300';

                                if (submitted) {
                                    if (isCorrect) {
                                        stateClasses =
                                            'border-brand-500 bg-brand-50 dark:bg-brand-900/30 dark:border-brand-500';
                                        letterClasses = 'bg-brand-600 text-white';
                                    } else if (isSelected && !isCorrect) {
                                        stateClasses =
                                            'border-red-400 bg-red-50 dark:bg-red-900/30 dark:border-red-600';
                                        letterClasses = 'bg-red-500 text-white';
                                    }
                                } else if (isSelected) {
                                    stateClasses = 'border-brand-500 bg-brand-50/50 dark:bg-brand-900/20';
                                    letterClasses = 'bg-brand-600 text-white';
                                }

                                return (
                                    <button
                                        key={optionIndex}
                                        type="button"
                                        disabled={submitted}
                                        onClick={() =>
                                            setAnswers((prev) => ({ ...prev, [index]: optionIndex }))
                                        }
                                        className={`flex w-full items-center gap-3 rounded-lg border px-4 py-2.5 text-start text-sm text-gray-800 transition dark:text-gray-100 ${stateClasses}`}
                                    >
                                        <span
                                            className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition ${letterClasses}`}
                                        >
                                            {OPTION_LETTERS[optionIndex] ?? optionIndex + 1}
                                        </span>
                                        {option}
                                    </button>
                                );
                            })}
                        </div>

                        {submitted && question.explanation && (
                            <p className="mt-3 rounded-lg bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-900/40 dark:text-gray-400">
                                <span className="font-semibold text-gray-700 dark:text-gray-300">
                                    {t('document.quiz.explanation')}:{' '}
                                </span>
                                {question.explanation}
                            </p>
                        )}
                    </div>
                ))}

                {questions.length > 0 && (
                    <div className="flex items-center gap-4">
                        {submitted ? (
                            <>
                                <p className="inline-flex items-center gap-2 rounded-full bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">
                                    {t('document.quiz.score', { score, total: questions.length })}
                                </p>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => {
                                        setSubmitted(false);
                                        setAnswers({});
                                    }}
                                >
                                    {t('document.quiz.retry')}
                                </Button>
                            </>
                        ) : (
                            <Button
                                type="button"
                                variant="primary"
                                onClick={() => setSubmitted(true)}
                                disabled={Object.keys(answers).length < questions.length}
                            >
                                {t('document.quiz.submit')}
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </ResultState>
    );
}
