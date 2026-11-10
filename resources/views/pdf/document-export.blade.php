@php
    $headings = [
        'summary' => ['ar' => 'الخلاصة المعرفية', 'en' => 'Knowledge Summary'],
        'translation' => ['ar' => 'التعريب الأكاديمي', 'en' => 'Academic Translation'],
        'quiz' => ['ar' => 'التقييم الذاتي', 'en' => 'Self-Assessment Quiz'],
    ];
    $labels = [
        'exported' => $rtl ? 'تاريخ التصدير' : 'Exported on',
        'answer' => $rtl ? 'الإجابة الصحيحة' : 'Correct answer',
        'explanation' => $rtl ? 'الشرح' : 'Explanation',
    ];
    $letters = ['A', 'B', 'C', 'D', 'E', 'F'];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $document->original_name }}</title>

    {{-- The app's compiled Tailwind build, inlined so rendering needs no network. --}}
    <style>{!! $css !!}</style>

    {{-- Bundled Arabic font (Noto Naskh Arabic), so Arabic never depends on OS fonts. --}}
    <style>{!! $fontCss !!}</style>

    <style>
        /* Defined here too: the compiled CSS reads var(--font-sans), which the app defines elsewhere. */
        html[dir] { --font-sans: 'Noto Naskh Arabic', 'Segoe UI', Tahoma, Arial, 'Liberation Sans', 'DejaVu Sans', sans-serif; }
        body { font-family: var(--font-sans); -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        .md > :first-child { margin-top: 0; }
        .md h1, .md h2, .md h3, .md h4 { font-weight: 700; line-height: 1.4; margin: 1.4em 0 .5em; break-after: avoid; }
        .md h1 { font-size: 1.35rem; } .md h2 { font-size: 1.2rem; } .md h3 { font-size: 1.08rem; } .md h4 { font-size: 1rem; }
        .md h1, .md h2, .md h3 { color: #047857; }
        .md p { margin: .6em 0; }
        .md ul, .md ol { margin: .6em 0; padding-inline-start: 1.6em; }
        .md ul { list-style: disc; } .md ol { list-style: decimal; }
        .md li { margin: .25em 0; }
        .md strong { font-weight: 700; color: #111827; }
        .md blockquote { margin: .8em 0; padding: .2em 1em; border-inline-start: 4px solid #6ee7b7; background: #ecfdf5; color: #374151; }
        .md code { background: #f3f4f6; padding: .1em .35em; border-radius: 4px; font-size: .9em; direction: ltr; unicode-bidi: embed; }
        .md pre { background: #f3f4f6; padding: .8em 1em; border-radius: 8px; direction: ltr; text-align: left; white-space: pre-wrap; }
        .md table { border-collapse: collapse; width: 100%; margin: .8em 0; }
        .md th, .md td { border: 1px solid #d1d5db; padding: .35em .6em; text-align: start; }
        .md th { background: #ecfdf5; }
        .md hr { border: 0; border-top: 1px solid #e5e7eb; margin: 1.2em 0; }
    </style>

    @if ($css === '')
        {{-- No production build found (run `npm run build`): keep the page readable without Tailwind. --}}
        <style>
            body { margin: 0; font-size: 13px; line-height: 1.8; color: #111827; }
            header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; margin-bottom: 24px; }
            h1 { font-size: 1.6rem; margin: 0 0 8px; } section > h2 { border-bottom: 2px solid #10b981; padding-bottom: 6px; color: #047857; }
        </style>
    @endif
</head>
<body class="bg-white text-[13px] leading-7 text-gray-900">
    <header class="mb-8 flex items-center justify-between border-b border-gray-200 pb-4">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-lg font-bold text-white">C</span>
            <span class="text-base font-semibold">{{ $appName }}</span>
        </div>
        <div class="text-xs text-gray-500">{{ $labels['exported'] }}: <bdi>{{ $exportedAt->format('Y-m-d') }}</bdi></div>
    </header>

    <h1 class="mb-8 text-2xl font-bold"><bdi>{{ pathinfo($document->original_name, PATHINFO_FILENAME) ?: $document->original_name }}</bdi></h1>

    @foreach ($sections as $section)
        @php
            $sectionRtl = $section['rtl'];
            $heading = $headings[$section['type']][$rtl ? 'ar' : 'en'];
        @endphp

        <section class="mb-10">
            <h2 class="mb-4 border-b-2 border-brand-500 pb-2 text-xl font-bold text-brand-700" style="break-after: avoid;">
                {{ $heading }}
            </h2>

            @if ($section['kind'] === 'text')
                <div class="md" dir="{{ $sectionRtl ? 'rtl' : 'ltr' }}" lang="{{ $sectionRtl ? 'ar' : 'en' }}">
                    {!! $section['html'] !!}
                </div>
            @else
                <ol class="space-y-4" dir="{{ $sectionRtl ? 'rtl' : 'ltr' }}" lang="{{ $sectionRtl ? 'ar' : 'en' }}">
                    @foreach ($section['questions'] as $index => $question)
                        @php $correct = (int) ($question['correct_index'] ?? -1); @endphp
                        <li class="rounded-xl border border-gray-200 p-4" style="break-inside: avoid;">
                            <p class="mb-3 font-semibold">{{ $index + 1 }}. {{ $question['question'] ?? '' }}</p>

                            <ul class="space-y-1.5">
                                @foreach (($question['options'] ?? []) as $optionIndex => $option)
                                    <li class="flex gap-2 rounded-lg px-3 py-1.5 {{ $optionIndex === $correct ? 'bg-brand-50 font-semibold text-brand-800' : 'bg-gray-50' }}">
                                        <span class="font-semibold">{{ $letters[$optionIndex] ?? $optionIndex + 1 }}.</span>
                                        <span>{{ $option }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            @if (isset($question['options'][$correct]))
                                <p class="mt-3 text-xs font-semibold text-brand-700">
                                    {{ $labels['answer'] }}: {{ $letters[$correct] ?? $correct + 1 }}
                                </p>
                            @endif

                            @if (! empty($question['explanation']))
                                <p class="mt-1 text-xs text-gray-600">
                                    <span class="font-semibold">{{ $labels['explanation'] }}:</span> {{ $question['explanation'] }}
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endforeach
</body>
</html>
