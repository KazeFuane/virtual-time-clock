<?php
/**
 * ClockWise - Light / dark mode preference
 *
 * Each signed-in user's choice is stored in user_preferences
 * (pref_key = 'theme') and cached in the session. A cookie keeps the
 * choice on this browser too, so the sign-in page opens in the same mode.
 * Default is dark (the original look).
 */

class ThemeHelper
{
    public const THEMES  = ['dark', 'light'];
    public const DEFAULT = 'dark';
    public const COOKIE  = 'vtc_theme';

    /** The theme to render the current page with: 'dark' or 'light'. */
    public static function current(): string
    {
        if (!empty($_SESSION['user_id'])) {
            if (!isset($_SESSION['theme'])) {
                $_SESSION['theme'] = self::load((int) $_SESSION['user_id']) ?? self::fromCookie();
            }
            return self::valid($_SESSION['theme']) ? $_SESSION['theme'] : self::DEFAULT;
        }
        return self::fromCookie();
    }

    /** CSS class for the <html> tag. */
    public static function htmlClass(): string
    {
        return 'theme-' . self::current();
    }

    /** Save a user's choice (DB + session + cookie). */
    public static function save(int $userId, string $theme): void
    {
        if (!self::valid($theme)) {
            throw new InvalidArgumentException('Unknown theme.');
        }
        $db = Database::getInstance()->getConnection();
        self::ensureTable($db);
        $stmt = $db->prepare("
            INSERT INTO user_preferences (user_id, pref_key, pref_value)
            VALUES (?, 'theme', ?)
            ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value)
        ");
        $stmt->execute([$userId, $theme]);
        $_SESSION['theme'] = $theme;
        self::setCookie($theme);
    }

    public static function setCookie(string $theme): void
    {
        if (self::valid($theme) && !headers_sent()) {
            setcookie(self::COOKIE, $theme, [
                'expires'  => time() + 365 * 86400,
                'path'     => '/',
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function valid($theme): bool
    {
        return is_string($theme) && in_array($theme, self::THEMES, true);
    }

    private static function fromCookie(): string
    {
        $c = $_COOKIE[self::COOKIE] ?? '';
        return self::valid($c) ? $c : self::DEFAULT;
    }

    private static function load(int $userId): ?string
    {
        try {
            $db = Database::getInstance()->getConnection();
            self::ensureTable($db);
            $stmt = $db->prepare("SELECT pref_value FROM user_preferences WHERE user_id = ? AND pref_key = 'theme'");
            $stmt->execute([$userId]);
            $v = $stmt->fetchColumn();
            return self::valid($v) ? $v : null;
        } catch (Throwable $e) {
            return null; // never break a page over a theme lookup
        }
    }

    /** Shared with the language setting; same definition as the migration. */
    private static function ensureTable(PDO $db): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $db->exec("
            CREATE TABLE IF NOT EXISTS `user_preferences` (
                `user_id`    INT UNSIGNED NOT NULL,
                `pref_key`   VARCHAR(50)  NOT NULL,
                `pref_value` VARCHAR(255) NOT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`user_id`, `pref_key`),
                CONSTRAINT `fk_user_prefs_user`
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $done = true;
    }
}
