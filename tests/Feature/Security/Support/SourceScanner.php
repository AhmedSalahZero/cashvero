<?php

namespace Tests\Feature\Security\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A small, dependency-free code scanner used by the security tests.
 *
 * Why it exists
 * -------------
 * A penetration tester reads source code looking for a short list of
 * dangerous patterns (running OS commands, building SQL out of strings,
 * handing the whole request to a model, printing unescaped HTML, secrets
 * committed to the repository). These checks are the same ones, done by
 * machine, every time anyone runs `php artisan test`.
 *
 * How it works
 * ------------
 * PHP code is read as TOKENS, not as raw text. A word inside a comment or
 * a string ("do not call exec() here") is therefore never mistaken for a
 * real call. This keeps the false-alarm rate near zero, which matters: a
 * security test that cries wolf gets ignored.
 *
 * It needs no database and no Laravel, so it can run anywhere.
 *
 * Secrets: nothing in here ever returns or prints a secret VALUE. A
 * finding says "file, line, kind of secret" and nothing more.
 */
final class SourceScanner
{
    /** Functions that run operating-system commands or rebuild variables from data. */
    public const DANGEROUS_FUNCTIONS = [
        'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen',
        'pcntl_exec', 'unserialize', 'extract', 'create_function',
    ];

    /** Query-builder methods that accept a raw SQL fragment. */
    private const RAW_SQL_METHODS = [
        'whereraw', 'orwhereraw', 'selectraw', 'orderbyraw', 'havingraw',
        'orhavingraw', 'groupbyraw', 'fromraw',
    ];

    /** `DB::<name>(...)` calls whose first argument is raw SQL. */
    private const RAW_SQL_FACADE_CALLS = [
        'raw', 'select', 'statement', 'unprepared', 'insert', 'update', 'delete',
    ];

    /** Model-writing methods: handing them the entire request is mass assignment. */
    private const MODEL_WRITE_METHODS = [
        'create', 'update', 'fill', 'forcefill', 'forcecreate', 'firstorcreate',
        'updateorcreate', 'insert', 'updateorinsert',
    ];

    public function __construct(private readonly string $root)
    {
    }

    /* ── file discovery ───────────────────────────────────────────── */

    /**
     * @param  list<string>  $skip  relative path prefixes to ignore
     * @return list<string>  absolute paths, sorted
     */
    public function files(string $relativeDir, string $extension, array $skip = []): array
    {
        $dir = $this->root.'/'.trim($relativeDir, '/');

        if (! is_dir($dir)) {
            return [];
        }

        $found = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $name = $file->getFilename();

            // `.blade.php` is matched by the plain "php" extension too; callers ask for it explicitly.
            if (! str_ends_with($name, '.'.$extension)) {
                continue;
            }

            $relative = $this->relative($file->getPathname());

            foreach ($skip as $prefix) {
                if (str_starts_with($relative, $prefix)) {
                    continue 2;
                }
            }

            $found[] = $file->getPathname();
        }

        sort($found);

        return $found;
    }

    public function relative(string $absolute): string
    {
        $root = rtrim(str_replace('\\', '/', $this->root), '/').'/';
        $path = str_replace('\\', '/', $absolute);

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }

    /* ── PHP token scanners ───────────────────────────────────────── */

    /**
     * Calls that execute commands/code, or rebuild variables from data.
     *
     * `eval` and the backtick operator are language constructs, so they are
     * matched by token type; the rest are matched by name when they are a
     * genuine function call (not a method called `->exec()` or a function
     * being declared).
     *
     * @return list<array{line:int, call:string}>
     */
    public function dangerousCalls(string $file): array
    {
        $tokens = $this->tokens((string) file_get_contents($file));
        $hits = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            [$id, $text, $line] = $tokens[$i];

            if ($id === T_EVAL) {
                $hits[] = ['line' => $line, 'call' => 'eval'];

                continue;
            }

            // Only the real operator: a lone '`' token. A backtick INSIDE an SQL string
            // (quoting a column name) arrives as part of a string token and is harmless.
            if ($id === '`') {
                $hits[] = ['line' => $line, 'call' => 'backtick shell operator'];

                continue;
            }

            $name = $this->plainFunctionName($id, $text);

            if ($name === null || ! in_array(strtolower($name), self::DANGEROUS_FUNCTIONS, true)) {
                continue;
            }

            if (($tokens[$i + 1][1] ?? null) !== '(') {
                continue;
            }

            $previous = $tokens[$i - 1][0] ?? null;

            if (in_array($previous, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
                continue;
            }

            $hits[] = ['line' => $line, 'call' => strtolower($name)];
        }

        return $hits;
    }

    /**
     * Leftover debugging calls (dd, dump, var_dump, ray, phpinfo). In a
     * web request these print internals — variable contents, paths,
     * sometimes credentials — straight into the page.
     *
     * @return list<array{line:int, call:string}>
     */
    public function debugCalls(string $file): array
    {
        $tokens = $this->tokens((string) file_get_contents($file));
        $hits = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            [$id, $text, $line] = $tokens[$i];

            $name = $this->plainFunctionName($id, $text);

            if ($name === null || ! in_array(strtolower($name), ['dd', 'dump', 'var_dump', 'ray', 'phpinfo'], true)) {
                continue;
            }

            if (($tokens[$i + 1][1] ?? null) !== '(') {
                continue;
            }

            if (in_array($tokens[$i - 1][0] ?? null, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
                continue;
            }

            $hits[] = ['line' => $line, 'call' => strtolower($name)];
        }

        return $hits;
    }

    /**
     * Raw-SQL calls whose SQL text is assembled at run time.
     *
     * `tainted` is true when request input appears in the SQL text itself.
     * That is the SQL-injection shape and is never acceptable. A call that is
     * merely "dynamic" (built from a variable) is not necessarily unsafe — the
     * variable is often a table name from a class constant — so those are
     * tracked as a ratchet: the number may go down, never up, without a human
     * looking at the new one.
     *
     * Only the first argument is examined: that is the SQL. The second
     * argument holds bound parameters, which are safe by construction.
     *
     * @return list<array{line:int, call:string, tainted:bool, snippet:string}>
     */
    public function dynamicRawSql(string $file): array
    {
        $tokens = $this->tokens((string) file_get_contents($file));
        $hits = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            [$id, $text, $line] = $tokens[$i];

            $callName = null;

            if (($id === T_STRING) && in_array(strtolower($text), self::RAW_SQL_METHODS, true)
                && ($tokens[$i + 1][1] ?? null) === '('
                && in_array($tokens[$i - 1][0] ?? null, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                $callName = $text;
            }

            if ($id === T_STRING && in_array(strtolower($text), self::RAW_SQL_FACADE_CALLS, true)
                && ($tokens[$i + 1][1] ?? null) === '('
                && ($tokens[$i - 1][0] ?? null) === T_DOUBLE_COLON
                && $this->isDbFacade($tokens[$i - 2] ?? null)) {
                $callName = 'DB::'.$text;
            }

            if ($callName === null) {
                continue;
            }

            $first = $this->firstArgument($tokens, $i + 1);

            if (! $this->isDynamic($first)) {
                continue;
            }

            $hits[] = [
                'line' => $line,
                'call' => $callName,
                'tainted' => $this->mentionsRequestInput($first),
                'snippet' => $this->snippet($first),
            ];
        }

        return $hits;
    }

    /**
     * Places where a model is written with the whole request as its data:
     *   Model::create($request->all())
     *   $model->update($request->except('x'))
     *
     * That is "mass assignment": whatever fields the visitor chooses to send
     * are written to the database, including ones the form never offered.
     * `->only([...])` and `->validated()` are the safe forms and are not flagged.
     *
     * @return list<array{line:int, call:string, snippet:string}>
     */
    public function massAssignmentFromRequest(string $file): array
    {
        $tokens = $this->tokens((string) file_get_contents($file));
        $hits = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            [$id, $text, $line] = $tokens[$i];

            if ($id !== T_STRING
                || ! in_array(strtolower($text), self::MODEL_WRITE_METHODS, true)
                || ($tokens[$i + 1][1] ?? null) !== '('
                || ! in_array($tokens[$i - 1][0] ?? null, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                continue;
            }

            $span = $this->wholeArguments($tokens, $i + 1);

            if ($this->containsWholeRequest($span)) {
                $hits[] = ['line' => $line, 'call' => $text, 'snippet' => $this->snippet($span)];
            }
        }

        return $hits;
    }

    /** True when the class declares `$guarded = []` (every column assignable). */
    public function hasEmptyGuard(string $file): bool
    {
        $tokens = $this->tokens((string) file_get_contents($file));
        $count = count($tokens);

        for ($i = 0; $i < $count - 3; $i++) {
            if ($tokens[$i][0] === T_VARIABLE && $tokens[$i][1] === '$guarded'
                && $tokens[$i + 1][1] === '='
                && $tokens[$i + 2][1] === '['
                && $tokens[$i + 3][1] === ']') {
                return true;
            }
        }

        return false;
    }

    /* ── text scanners (Blade, Vue) ───────────────────────────────── */

    /**
     * Blade's unescaped output tag. Anything printed through it is sent to the
     * browser as raw HTML, so any user-controlled text inside it is stored XSS.
     *
     * @return list<array{line:int, expression:string}>
     */
    public function unescapedBladeOutput(string $file): array
    {
        $hits = [];

        foreach ($this->lines($file) as $number => $text) {
            if (preg_match_all('/\{!!\s*(.*?)\s*!!\}/', $text, $matches)) {
                foreach ($matches[1] as $expression) {
                    $hits[] = ['line' => $number, 'expression' => $expression];
                }
            }
        }

        return $hits;
    }

    /**
     * Browser-side sinks that turn a string into live HTML or code.
     *
     * @return list<array{line:int, kind:string, expression:string}>
     */
    public function htmlSinks(string $file): array
    {
        $hits = [];

        foreach ($this->lines($file) as $number => $text) {
            if (preg_match_all('/v-html\s*=\s*"([^"]*)"/', $text, $m)) {
                foreach ($m[1] as $expression) {
                    $hits[] = ['line' => $number, 'kind' => 'v-html', 'expression' => trim($expression)];
                }
            }

            foreach ([
                'innerHTML' => '/\.(?:inner|outer)HTML\s*=(?!=)/',
                'insertAdjacentHTML' => '/\.insertAdjacentHTML\s*\(/',
                'document.write' => '/document\.write(?:ln)?\s*\(/',
                'eval' => '/(?<![\w.$])eval\s*\(/',
                'new Function' => '/new\s+Function\s*\(/',
            ] as $kind => $pattern) {
                if (preg_match($pattern, $text)) {
                    $hits[] = ['kind' => $kind, 'line' => $number, 'expression' => trim($text)];
                }
            }
        }

        return $hits;
    }

    /**
     * Hard-coded credentials. Returns WHERE and WHAT KIND — never the value.
     *
     * @return list<array{line:int, kind:string}>
     */
    public function secrets(string $file): array
    {
        $hits = [];

        $patterns = [
            'private key' => '/-----BEGIN (?:RSA |EC |DSA |OPENSSH )?PRIVATE KEY-----/',
            'AWS access key id' => '/\bAKIA[0-9A-Z]{16}\b/',
            'Slack token' => '/\bxox[baprs]-[0-9A-Za-z-]{10,}/',
            'GitHub token' => '/\bgh[pousr]_[A-Za-z0-9]{36,}/',
            'Stripe live key' => '/\b[sr]k_live_[0-9A-Za-z]{16,}/',
            'Sentry DSN with key' => '#https://[0-9a-f]{32}@[^\s\'"]+#',
            // 'something_password' => 'literal-value' / 'api_token' => '...' etc.
            'hard-coded credential' => '/[\'"][\w.-]*(?:password|passwd|secret|api[_-]?key|token)[\w.-]*[\'"]\s*=>\s*[\'"](?!\s*[\'"])([^\'"\s|:]{6,})[\'"]/i',
        ];

        foreach ($this->lines($file) as $number => $text) {
            foreach ($patterns as $kind => $pattern) {
                if (! preg_match($pattern, $text, $m)) {
                    continue;
                }

                if ($kind === 'hard-coded credential' && $this->looksLikePlaceholder($m[1] ?? '')) {
                    continue;
                }

                $hits[] = ['line' => $number, 'kind' => $kind];
            }
        }

        return $hits;
    }

    /* ── internals ────────────────────────────────────────────────── */

    /**
     * Significant tokens as [id, text, line]. Single-character tokens are
     * returned with their text as the id, so `'('` compares to `'('`.
     *
     * @return list<array{0:int|string, 1:string, 2:int}>
     */
    private function tokens(string $code): array
    {
        $out = [];
        $line = 1;

        foreach (token_get_all($code) as $token) {
            if (is_array($token)) {
                $line = $token[2];

                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $out[] = [$token[0], $token[1], $token[2]];

                continue;
            }

            $out[] = [$token, $token, $line];
        }

        return $out;
    }

    /** The bare function name for a T_STRING / namespaced-name token, else null. */
    private function plainFunctionName(int|string $id, string $text): ?string
    {
        if ($id === T_STRING) {
            return $text;
        }

        if (defined('T_NAME_FULLY_QUALIFIED') && $id === T_NAME_FULLY_QUALIFIED && substr_count($text, '\\') === 1) {
            return ltrim($text, '\\');
        }

        return null;
    }

    /** Is this token the `DB` facade (possibly fully qualified)? */
    private function isDbFacade(?array $token): bool
    {
        if ($token === null) {
            return false;
        }

        $text = $token[1];
        $short = str_contains($text, '\\') ? substr($text, strrpos($text, '\\') + 1) : $text;

        return $short === 'DB';
    }

    /**
     * Tokens of the first argument of the call whose '(' is at $open.
     *
     * @return list<array{0:int|string, 1:string, 2:int}>
     */
    private function firstArgument(array $tokens, int $open): array
    {
        return $this->arguments($tokens, $open, true);
    }

    /**
     * Tokens of every argument of the call whose '(' is at $open.
     *
     * @return list<array{0:int|string, 1:string, 2:int}>
     */
    private function wholeArguments(array $tokens, int $open): array
    {
        return $this->arguments($tokens, $open, false);
    }

    private function arguments(array $tokens, int $open, bool $firstOnly): array
    {
        $depth = 0;
        $collected = [];
        $count = count($tokens);

        for ($i = $open; $i < $count; $i++) {
            $text = $tokens[$i][1];

            if (in_array($text, ['(', '[', '{', '${'], true)) {
                $depth++;

                if ($i === $open) {
                    continue;
                }
            } elseif (in_array($text, [')', ']', '}'], true)) {
                $depth--;

                if ($depth === 0) {
                    break;
                }
            } elseif ($firstOnly && $depth === 1 && $text === ',') {
                break;
            }

            $collected[] = $tokens[$i];
        }

        return $collected;
    }

    /** Is this SQL text built from something other than plain string literals? */
    private function isDynamic(array $argument): bool
    {
        foreach ($argument as [$id, $text]) {
            if ($id === T_VARIABLE || $id === T_OBJECT_OPERATOR || $id === T_NULLSAFE_OBJECT_OPERATOR) {
                return true;
            }

            // A function call inside the SQL text, e.g. sprintf(...).
            if ($text === '(') {
                return true;
            }
        }

        return false;
    }

    private function mentionsRequestInput(array $argument): bool
    {
        $count = count($argument);

        for ($i = 0; $i < $count; $i++) {
            [$id, $text] = $argument[$i];

            if ($id === T_VARIABLE && in_array($text, ['$request', '$req', '$_GET', '$_POST', '$_REQUEST'], true)) {
                return true;
            }

            if ($id === T_STRING && in_array(strtolower($text), ['request', 'input'], true)
                && in_array($argument[$i + 1][1] ?? '', ['(', '::'], true)) {
                return true;
            }
        }

        return false;
    }

    /** Does the argument list contain `$request->all()` / `->except(...)` / `request()->all()`? */
    private function containsWholeRequest(array $argument): bool
    {
        $count = count($argument);

        for ($i = 0; $i < $count; $i++) {
            $isRequestVar = $argument[$i][0] === T_VARIABLE && $argument[$i][1] === '$request';
            $isRequestHelper = $argument[$i][0] === T_STRING
                && strtolower($argument[$i][1]) === 'request'
                && ($argument[$i + 1][1] ?? '') === '('
                && ($argument[$i + 2][1] ?? '') === ')';

            if (! $isRequestVar && ! $isRequestHelper) {
                continue;
            }

            $arrow = $isRequestVar ? $i + 1 : $i + 3;

            if (($argument[$arrow][0] ?? null) !== T_OBJECT_OPERATOR) {
                continue;
            }

            $method = strtolower($argument[$arrow + 1][1] ?? '');

            if (in_array($method, ['all', 'except'], true)) {
                return true;
            }

            // ->input() / ->post() with no key returns the whole payload too.
            if (in_array($method, ['input', 'post'], true)
                && ($argument[$arrow + 2][1] ?? '') === '('
                && ($argument[$arrow + 3][1] ?? '') === ')') {
                return true;
            }
        }

        return false;
    }

    private function snippet(array $argument): string
    {
        $text = implode('', array_map(static fn (array $t): string => $t[1], $argument));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return strlen($text) > 90 ? substr($text, 0, 87).'...' : $text;
    }

    /** @return array<int, string> line number => text */
    private function lines(string $file): array
    {
        $out = [];

        // NOT '\R': that also splits on form-feed / vertical-tab / U+2028, which editors and
        // grep do not, and would make every reported line number wrong in such files.
        foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($file)) ?: [] as $index => $text) {
            $out[$index + 1] = $text;
        }

        return $out;
    }

    private function looksLikePlaceholder(string $value): bool
    {
        $lower = strtolower($value);

        return in_array($lower, [
            'required', 'nullable', 'confirmed', 'string', 'password', 'secret',
            'token', 'hidden', 'changeme', 'null', 'false', 'true', 'xxxxxxxx',
            'hashed', 'encrypted', 'encrypted:array', 'encrypted:json', 'datetime', 'immutable_datetime',
        ], true)
            || str_starts_with($lower, 'env(')
            || str_starts_with($lower, 'change-me')
            || preg_match('/^[x*]+$/', $lower) === 1;
    }
}
