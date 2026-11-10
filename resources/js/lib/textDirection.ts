/**
 * Same rule as DocumentExportController::isRtl(): RTL when Arabic letters
 * are present and at least as common as Latin letters.
 */
export function isRtlText(text: string): boolean {
    const arabic = text.match(/\p{Script=Arabic}/gu)?.length ?? 0;
    const latin = text.match(/\p{Script=Latin}/gu)?.length ?? 0;

    return arabic > 0 && arabic >= latin;
}
