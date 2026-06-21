<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ConsoleAudit
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $name,
        public readonly array $context = [],
    ) {}
}
