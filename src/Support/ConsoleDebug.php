<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Support;

use Illuminate\Support\Str;

/**
 * Lightweight Ray-like debug utility.
 *
 * Captures values and writes them as NDJSON to storage/logs/console-debug.log.
 * The entry is flushed in __destruct(), so both fire-and-forget and fluent
 * chaining work:
 *
 *   console('quick test');
 *   console($user)->label('User')->green();
 *   console(['a' => 1])->table();
 */
class ConsoleDebug
{
    private const LOG_FILE = 'console-debug.log';

    private const MAX_FILE_SIZE = 512_000; // 500 KB

    private const PRUNE_KEEP = 200;

    /** @var list<array{type: string, value: mixed}> */
    private array $values = [];

    private ?string $label = null;

    private string $color = 'gray';

    private string $type = 'dump';

    private array $caller = [];

    private bool $flushed = false;

    public function __construct(mixed ...$values)
    {
        // Capture caller info before anything else
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        // Index 0 = this constructor, 1 = console() helper, 2 = actual caller
        $frame = $trace[2] ?? $trace[1] ?? $trace[0];

        $this->caller = [
            'file' => isset($frame['file']) ? str_replace(base_path() . '/', '', str_replace('\\', '/', $frame['file'])) : 'unknown',
            'line' => $frame['line'] ?? 0,
            'function' => $frame['function'] ?? null,
            'class' => $frame['class'] ?? null,
        ];

        foreach ($values as $value) {
            $this->values[] = $this->serializeValue($value);
        }
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function color(string $color): static
    {
        $allowed = ['green', 'blue', 'orange', 'red', 'purple', 'gray'];
        $this->color = in_array($color, $allowed, true) ? $color : 'gray';

        return $this;
    }

    public function green(): static
    {
        return $this->color('green');
    }

    public function blue(): static
    {
        return $this->color('blue');
    }

    public function red(): static
    {
        return $this->color('red');
    }

    public function orange(): static
    {
        return $this->color('orange');
    }

    public function purple(): static
    {
        return $this->color('purple');
    }

    public function table(): static
    {
        $this->type = 'table';

        return $this;
    }

    public function __destruct()
    {
        if (! $this->flushed) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        $this->flushed = true;

        $entry = [
            'id' => Str::uuid()->toString(),
            'ts' => now()->format('Y-m-d H:i:s.u'),
            'type' => $this->type,
            'values' => $this->values,
            'label' => $this->label,
            'color' => $this->color,
            'caller' => $this->caller,
        ];

        $logPath = $this->logPath();
        $logDir = dirname($logPath);

        // Ensure directory exists
        if (! is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        // Auto-prune if file is too large
        if (file_exists($logPath) && filesize($logPath) > self::MAX_FILE_SIZE) {
            $this->prune($logPath);
        }

        @file_put_contents(
            $logPath,
            json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX,
        );
    }

    private function prune(string $logPath): void
    {
        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        $kept = array_slice($lines, -self::PRUNE_KEEP);
        @file_put_contents($logPath, implode("\n", $kept) . "\n", LOCK_EX);
    }

    private function logPath(): string
    {
        return storage_path('logs/' . self::LOG_FILE);
    }

    /** @return array{type: string, value: mixed} */
    private function serializeValue(mixed $value): array
    {
        return match (true) {
            is_null($value) => ['type' => 'null', 'value' => null],
            is_bool($value) => ['type' => 'boolean', 'value' => $value],
            is_int($value) => ['type' => 'integer', 'value' => $value],
            is_float($value) => ['type' => 'float', 'value' => $value],
            is_string($value) => ['type' => 'string', 'value' => Str::limit($value, 10_000)],
            is_array($value) => ['type' => 'array', 'value' => $this->truncateDeep($value, 5)],
            is_object($value) => [
                'type' => 'object',
                'value' => [
                    'class' => $value::class,
                    'data' => $this->truncateDeep(
                        method_exists($value, 'toArray') ? $value->toArray() : (array) $value,
                        5,
                    ),
                ],
            ],
            default => ['type' => 'unknown', 'value' => (string) $value],
        };
    }

    /**
     * Recursively truncate deep/large structures to keep entries manageable.
     */
    private function truncateDeep(array $data, int $maxDepth, int $depth = 0): array
    {
        if ($depth >= $maxDepth) {
            return ['…truncated…'];
        }

        $result = [];
        $count = 0;

        foreach ($data as $key => $value) {
            if ($count >= 50) {
                $result['…'] = '(' . count($data) . ' total items)';
                break;
            }

            if (is_array($value)) {
                $result[$key] = $this->truncateDeep($value, $maxDepth, $depth + 1);
            } elseif (is_string($value) && strlen($value) > 1000) {
                $result[$key] = substr($value, 0, 1000) . '…';
            } else {
                $result[$key] = $value;
            }

            $count++;
        }

        return $result;
    }
}
