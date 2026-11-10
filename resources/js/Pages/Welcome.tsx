import AiBotBadge from '@/Components/landing/AiBotBadge';
import FeatureCard from '@/Components/landing/FeatureCard';
import HeroMockup from '@/Components/landing/HeroMockup';
import NeuralBackground from '@/Components/landing/NeuralBackground';
import StepCard from '@/Components/landing/StepCard';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import ThemeToggle from '@/Components/ThemeToggle';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    BrainCircuit,
    ClipboardCheck,
    Cpu,
    Languages,
    Sparkles,
    Volume2,
    Zap,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

const FEATURE_ICONS: Record<string, JSX.Element> = {
    summary: <BrainCircuit className="h-5 w-5" strokeWidth={1.75} />,
    translation: <Languages className="h-5 w-5" strokeWidth={1.75} />,
    quiz: <ClipboardCheck className="h-5 w-5" strokeWidth={1.75} />,
    pronunciation: <Volume2 className="h-5 w-5" strokeWidth={1.75} />,
    realtime: <Zap className="h-5 w-5" strokeWidth={1.75} />,
    routing: <Cpu className="h-5 w-5" strokeWidth={1.75} />,
};

const FEATURE_KEYS = ['summary', 'translation', 'quiz', 'pronunciation', 'realtime', 'routing'] as const;
const STEP_KEYS = ['upload', 'process', 'study'] as const;

export default function Welcome({ auth }: PageProps) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('app.name')} />
            <div className="min-h-screen bg-white text-gray-900 dark:bg-slate-950 dark:text-gray-100">
                <header className="sticky top-0 z-40 border-b border-gray-100 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white shadow-[0_0_16px_-2px_rgba(16,185,129,0.7)]">
                                C
                            </span>
                            <span className="text-base font-semibold">{t('app.name')}</span>
                        </div>

                        <nav className="hidden items-center gap-8 text-sm font-medium text-gray-600 dark:text-gray-300 md:flex">
                            <a href="#features" className="hover:text-brand-600 dark:hover:text-brand-400">
                                {t('landing.nav.features')}
                            </a>
                            <a href="#how-it-works" className="hover:text-brand-600 dark:hover:text-brand-400">
                                {t('landing.nav.howItWorks')}
                            </a>
                        </nav>

                        <div className="flex items-center gap-2">
                            <LanguageSwitcher />
                            <ThemeToggle />
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-700"
                                >
                                    {t('nav.dashboard')}
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="hidden rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 sm:inline-flex dark:text-gray-300 dark:hover:bg-slate-800"
                                    >
                                        {t('landing.nav.login')}
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-700"
                                    >
                                        {t('landing.nav.register')}
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </header>

                <main>
                    <section className="relative overflow-hidden">
                        <div className="pointer-events-none absolute -top-24 start-1/2 h-[32rem] w-[32rem] -translate-x-1/2 rounded-full bg-brand-400/20 blur-3xl dark:bg-brand-500/10" />

                        <div className="relative mx-auto grid max-w-6xl items-center gap-12 px-6 py-16 sm:py-24 lg:grid-cols-2">
                            <div>
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">
                                    <Sparkles className="h-3.5 w-3.5" strokeWidth={2} />
                                    {t('landing.hero.eyebrow')}
                                </span>
                                <h1 className="mt-4 text-4xl font-bold tracking-tight sm:text-5xl">
                                    {t('landing.hero.titlePrefix')}{' '}
                                    <span className="bg-gradient-to-r from-brand-500 via-emerald-400 to-teal-400 bg-clip-text text-transparent">
                                        {t('landing.hero.titleHighlight')}
                                    </span>{' '}
                                    {t('landing.hero.titleSuffix')}
                                </h1>
                                <p className="mt-5 text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                                    {t('landing.hero.subtitle')}
                                </p>
                                <div className="mt-8 flex flex-wrap items-center gap-4">
                                    <div className="flex flex-wrap items-center gap-3">
                                        <Link
                                            href={route(auth.user ? 'documents.index' : 'register')}
                                            className="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-6 py-3 text-sm font-semibold text-white shadow-[0_0_30px_-6px_rgba(16,185,129,0.7)] transition hover:-translate-y-0.5 hover:bg-brand-700 hover:shadow-[0_0_40px_-4px_rgba(16,185,129,0.85)]"
                                        >
                                            <BrainCircuit className="h-4 w-4" strokeWidth={2} />
                                            {t('landing.hero.cta')}
                                        </Link>
                                        <a
                                            href="#how-it-works"
                                            className="inline-flex items-center rounded-lg border border-gray-300 px-6 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:text-gray-200 dark:hover:bg-slate-800"
                                        >
                                            {t('landing.hero.ctaSecondary')}
                                        </a>
                                    </div>
                                    <AiBotBadge />
                                </div>
                            </div>

                            <div className="relative flex justify-center lg:justify-end">
                                <div className="pointer-events-none absolute -inset-16 -z-10">
                                    <NeuralBackground />
                                </div>
                                <HeroMockup />
                            </div>
                        </div>
                    </section>

                    <section className="border-y border-gray-100 bg-gray-50 py-10 dark:border-slate-800 dark:bg-slate-900/60">
                        <div className="mx-auto grid max-w-4xl grid-cols-3 gap-6 px-6 text-center">
                            <TrustStat value="10K+" label={t('landing.trust.summaries')} />
                            <TrustStat value="2" label={t('landing.trust.languages')} />
                            <TrustStat value="PDF · DOCX" label={t('landing.trust.formats')} />
                        </div>
                    </section>

                    <section id="features" className="mx-auto max-w-6xl px-6 py-20">
                        <div className="mx-auto max-w-2xl text-center">
                            <span className="text-sm font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">
                                {t('landing.features.eyebrow')}
                            </span>
                            <h2 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                                {t('landing.features.title')}
                            </h2>
                            <p className="mt-4 text-gray-600 dark:text-gray-400">
                                {t('landing.features.subtitle')}
                            </p>
                        </div>

                        <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {FEATURE_KEYS.map((key) => (
                                <FeatureCard
                                    key={key}
                                    icon={FEATURE_ICONS[key]}
                                    title={t(`landing.features.items.${key}.title`)}
                                    description={t(`landing.features.items.${key}.description`)}
                                />
                            ))}
                        </div>
                    </section>

                    <section
                        id="how-it-works"
                        className="border-y border-gray-100 bg-gray-50 py-20 dark:border-slate-800 dark:bg-slate-900/60"
                    >
                        <div className="mx-auto max-w-6xl px-6">
                            <div className="mx-auto max-w-2xl text-center">
                                <span className="text-sm font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">
                                    {t('landing.steps.eyebrow')}
                                </span>
                                <h2 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                                    {t('landing.steps.title')}
                                </h2>
                            </div>

                            <div className="mt-12 grid grid-cols-1 gap-10 sm:grid-cols-3">
                                {STEP_KEYS.map((key, index) => (
                                    <StepCard
                                        key={key}
                                        number={index + 1}
                                        title={t(`landing.steps.items.${key}.title`)}
                                        description={t(`landing.steps.items.${key}.description`)}
                                    />
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="relative mx-auto max-w-4xl px-6 py-20 text-center">
                        <div className="pointer-events-none absolute inset-0 -z-10 flex justify-center">
                            <div className="h-64 w-64 rounded-full bg-brand-400/20 blur-3xl dark:bg-brand-500/15" />
                        </div>

                        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-[0_0_35px_-6px_rgba(16,185,129,0.8)]">
                            <BrainCircuit className="h-7 w-7" strokeWidth={1.75} />
                        </div>
                        <h2 className="mx-auto mt-4 max-w-xl text-3xl font-bold tracking-tight">
                            {t('landing.cta.title')}
                        </h2>
                        <p className="mx-auto mt-3 max-w-md text-gray-600 dark:text-gray-400">
                            {t('landing.cta.subtitle')}
                        </p>
                        <Link
                            href={route(auth.user ? 'documents.index' : 'register')}
                            className="mt-8 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-8 py-3 text-sm font-semibold text-white shadow-[0_0_30px_-6px_rgba(16,185,129,0.7)] transition hover:-translate-y-0.5 hover:bg-brand-700 hover:shadow-[0_0_40px_-4px_rgba(16,185,129,0.85)]"
                        >
                            <Sparkles className="h-4 w-4" strokeWidth={2} />
                            {t('landing.cta.button')}
                        </Link>
                    </section>
                </main>

                <footer className="border-t border-gray-100 py-10 dark:border-slate-800">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 text-sm text-gray-500 sm:flex-row dark:text-gray-400">
                        <div className="flex items-center gap-2">
                            <span className="flex h-6 w-6 items-center justify-center rounded-md bg-brand-600 text-xs font-bold text-white">
                                C
                            </span>
                            <span className="font-medium text-gray-700 dark:text-gray-200">
                                {t('app.name')}
                            </span>
                        </div>
                        <p>{t('landing.footer.tagline')}</p>
                        <p>
                            &copy; {new Date().getFullYear()} {t('app.name')} — {t('landing.footer.rights')}
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}

function TrustStat({ value, label }: { value: string; label: string }) {
    return (
        <div>
            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{value}</p>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{label}</p>
        </div>
    );
}
