<?php
/**
 * Features that are not built yet ("Coming soon").
 *
 * The sidebar, the header search and public/placeholder.php all read this
 * list, so a feature is marked "Coming soon" everywhere from one place.
 * When a feature is finished: give it its own page, point its sidebar link
 * there and remove it from this list.
 *
 * The key is the English name (also the ?module= value in the URL). Texts
 * are English and translated with t() (see lang/*.php).
 */
final class ComingSoon
{
    public const PAGE = '/virtual-time-clock/public/placeholder.php';

    /** English name => [icon (SVG path), what the feature will do]. */
    public static function features(): array
    {
        return [
            'Quality Knowledge' => [
                'icon'  => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                'about' => 'A shared library of guides, procedures and best practices for your team.',
            ],
            'Quality Grid' => [
                'icon'  => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
                'about' => "Score work against your quality checklist and follow each agent's progress.",
            ],
            'Communication' => [
                'icon'  => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
                'about' => 'Team announcements and messages, right inside ClockWise.',
            ],
            'Back Office Tools' => [
                'icon'  => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                'about' => 'Everyday tools for the back office team, gathered in one place.',
            ],
            'Production Monitoring' => [
                'icon'  => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                'about' => 'A live view of who is working, on break or offline, and how the day is going.',
            ],
            'Help' => [
                'icon'  => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                'about' => 'Step-by-step guides and answers to common questions about the time clock.',
            ],
            'Privacy & Compliance' => [
                'icon'  => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                'about' => 'How ClockWise stores and protects your time records, and your rights over them.',
            ],
        ];
    }

    /** The feature's details, or null if it is not on the list. */
    public static function get(?string $name): ?array
    {
        return self::features()[(string) $name] ?? null;
    }

    /** Link to the "Coming soon" page of a feature. */
    public static function url(string $name): string
    {
        return self::PAGE . '?module=' . rawurlencode($name);
    }

    /** Small "Soon" badge for menus and lists (works in light and dark mode). */
    public static function badge(string $extraClass = ''): string
    {
        return '<span class="shrink-0 inline-flex items-center gap-1 text-[10px] font-semibold leading-none px-1.5 py-1 rounded-full bg-amber-950 text-amber-300 border border-amber-800 ' . $extraClass . '" title="' . t('Coming soon') . '">'
            . '<span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>'
            . t('Soon') . '</span>';
    }
}
