<?php
/**
 * ClockWise - Language / translation helper
 *
 * Every piece of UI text is written in English and passed through t():
 *
 *     <?= t('Start Workday') ?>
 *     <?= t('Hello {name}', ['name' => $first]) ?>
 *
 * The English text is the key. Translations live in lang/<code>.php, one
 * file per language. If a text has no translation it is shown in English,
 * so a missing entry never breaks a page.
 *
 * Adding a language:
 *   1. Copy lang/fr.php to lang/<code>.php (for example lang/es.php).
 *   2. Change 'name' (shown in the picker) and 'dir' ('rtl' for Arabic,
 *      Hebrew...), then translate the right-hand side of each line.
 *   That's all: the language appears in the picker automatically.
 *
 * The chosen language is saved per user (user_preferences table,
 * pref_key = 'language'), kept in the session, and in a cookie so the
 * sign-in page also shows it before anyone is signed in.
 */

class Lang
{
    public const DEFAULT = 'en';
    private const COOKIE = 'vtc_lang';

    private static ?string $current = null;
    private static array $strings = [];
    private static ?array $available = null;

    /** All languages that have a file in lang/: code => ['name' => ..., 'dir' => ...] */
    public static function available(): array
    {
        if (self::$available !== null) {
            return self::$available;
        }
        $list = [self::DEFAULT => ['name' => 'English', 'dir' => 'ltr']];
        foreach (glob(ROOT_PATH . '/lang/*.php') ?: [] as $file) {
            $code = basename($file, '.php');
            if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $code)) {
                continue;
            }
            $data = include $file;
            if (is_array($data)) {
                self::$strings[$code] = is_array($data['strings'] ?? null) ? $data['strings'] : [];
                $list[$code] = [
                    'name' => $data['name'] ?? $code,
                    'dir'  => ($data['dir'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr',
                ];
            }
        }
        return self::$available = $list;
    }

    public static function isSupported(?string $code): bool
    {
        return is_string($code) && isset(self::available()[$code]);
    }

    /** The language for this request. */
    public static function current(): string
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $code = $_SESSION['language'] ?? null;

        // Signed in but not yet loaded this session: read the saved choice
        if (!self::isSupported($code) && !empty($_SESSION['user_id'])) {
            $code = self::loadForUser((int) $_SESSION['user_id']);
            if (self::isSupported($code)) {
                $_SESSION['language'] = $code;
            }
        }
        if (!self::isSupported($code)) {
            $code = $_COOKIE[self::COOKIE] ?? null;
        }
        if (!self::isSupported($code)) {
            $code = self::fromBrowser();
        }
        if (!self::isSupported($code)) {
            $code = self::DEFAULT;
        }
        return self::$current = $code;
    }

    /** 'ltr' or 'rtl' for the current language. */
    public static function dir(): string
    {
        return self::available()[self::current()]['dir'] ?? 'ltr';
    }

    /** Change the language for this visitor (and save it if signed in). */
    public static function set(string $code): bool
    {
        if (!self::isSupported($code)) {
            return false;
        }
        $_SESSION['language'] = $code;
        self::$current = $code;
        if (!headers_sent()) {
            setcookie(self::COOKIE, $code, [
                'expires'  => time() + 365 * 86400,
                'path'     => '/',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        if (!empty($_SESSION['user_id'])) {
            self::saveForUser((int) $_SESSION['user_id'], $code);
        }
        return true;
    }

    /** Translate an English text. {placeholders} are replaced from $params. */
    public static function t(string $text, array $params = []): string
    {
        $out = self::strings()[$text] ?? $text;
        if ($out === '') {
            $out = $text;
        }
        if ($params) {
            $repl = [];
            foreach ($params as $k => $v) {
                $repl['{' . $k . '}'] = (string) $v;
            }
            $out = strtr($out, $repl);
        }
        return $out;
    }

    /** Translations for the current language (English => translated). */
    public static function strings(): array
    {
        $code = self::current();
        if ($code === self::DEFAULT) {
            return [];
        }
        self::available(); // loads every language file once
        return self::$strings[$code] ?? [];
    }

    /**
     * <script> that gives page JavaScript the same t() function.
     * Put it in <head>, before any other script that shows text.
     */
    public static function jsScript(): string
    {
        $json = json_encode(self::strings(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $lang = json_encode(self::current());
        return "<script>window.APP_LANG={$lang};window.I18N={$json}||{};"
            . "window.t=function(s,p){var o=(window.I18N[s]||s);if(p){for(var k in p){o=o.split('{'+k+'}').join(String(p[k]));}}return o;};</script>";
    }

    // ---------------------------------------------------------------
    // Storage (user_preferences, shared with the theme setting)
    // ---------------------------------------------------------------

    private static function loadForUser(int $userId): ?string
    {
        try {
            $db = Database::getInstance()->getConnection();
            self::ensureTable($db);
            $stmt = $db->prepare("SELECT pref_value FROM user_preferences WHERE user_id = ? AND pref_key = 'language'");
            $stmt->execute([$userId]);
            $v = $stmt->fetchColumn();
            return $v === false ? null : (string) $v;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function saveForUser(int $userId, string $code): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            self::ensureTable($db);
            $stmt = $db->prepare("
                INSERT INTO user_preferences (user_id, pref_key, pref_value)
                VALUES (?, 'language', ?)
                ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value)
            ");
            $stmt->execute([$userId, $code]);
        } catch (Throwable $e) {
            // Session and cookie still hold the choice
        }
    }

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
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $done = true;
    }

    /** First supported language in the browser's Accept-Language header. */
    private static function fromBrowser(): ?string
    {
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        foreach (explode(',', $header) as $part) {
            $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
            if (self::isSupported($code)) {
                return $code;
            }
        }
        return null;
    }
}

/** Translate UI text. See Lang::t(). */
function t(string $text, array $params = []): string
{
    return Lang::t($text, $params);
}
