Cascade provides event listeners to monitor the resolution lifecycle. Use events for logging, metrics, debugging, and performance monitoring.

## Available Events

### SourceQueried

Fired when a source is queried during resolution.

```php
use Cline\Cascade\Event\SourceQueried;

$cascade->onSourceQueried(function(SourceQueried $event) {
    // $event->sourceName - Name of the source being queried
    // $event->key - The key being resolved
    // $event->context - Resolution context
    // $event->timestamp - When the query started
});
```

### ValueResolved

Fired when a value is successfully found.

```php
use Cline\Cascade\Event\ValueResolved;

$cascade->onResolved(function(ValueResolved $event) {
    // $event->key - The key that was resolved
    // $event->value - The resolved value
    // $event->sourceName - Source that provided the value
    // $event->durationMs - Time taken to resolve (milliseconds)
    // $event->attemptedSources - All sources attempted
    // $event->context - Resolution context
});
```

### ResolutionFailed

Fired when no source could provide a value.

```php
use Cline\Cascade\Event\ResolutionFailed;

$cascade->onFailed(function(ResolutionFailed $event) {
    // $event->key - The key that failed to resolve
    // $event->attemptedSources - All sources that were tried
    // $event->context - Resolution context
    // $event->timestamp - When resolution failed
});
```

## Basic Usage

### Logging

Log resolution activity:

```php
use Cline\Cascade\Cascade;
use Psr\Log\LoggerInterface;

class LoggedCascade
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function create(): Cascade
    {
        $cascade = Cascade::from()
            ->fallbackTo($this->customerSource)
            ->fallbackTo($this->platformSource);

        // Log source queries
        $cascade->onSourceQueried(function($event) {
            $this->logger->debug('Querying source', [
                'source' => $event->sourceName,
                'key' => $event->key,
                'context' => $event->context,
            ]);
        });

        // Log successful resolutions
        $cascade->onResolved(function($event) {
            $this->logger->info('Value resolved', [
                'key' => $event->key,
                'source' => $event->sourceName,
                'duration_ms' => $event->durationMs,
                'attempted' => count($event->attemptedSources),
            ]);
        });

        // Log failures
        $cascade->onFailed(function($event) {
            $this->logger->warning('Resolution failed', [
                'key' => $event->key,
                'attempted_sources' => $event->attemptedSources,
            ]);
        });

        return $cascade;
    }
}
```

### Metrics Collection

Track resolution metrics:

```php
use Cline\Cascade\Cascade;

class MetricsCascade
{
    public function __construct(
        private MetricsCollector $metrics,
    ) {}

    public function create(): Cascade
    {
        $cascade = Cascade::from()
            ->fallbackTo($this->dbSource)
            ->fallbackTo($this->cacheSource);

        $cascade->onResolved(function($event) {
            // Track resolution time
            $this->metrics->histogram('cascade.resolution.duration', $event->durationMs, [
                'source' => $event->sourceName,
            ]);

            // Count successes by source
            $this->metrics->increment('cascade.resolution.success', [
                'source' => $event->sourceName,
                'key' => $event->key,
            ]);

            // Track number of sources attempted
            $this->metrics->gauge('cascade.resolution.attempts',
                count($event->attemptedSources)
            );
        });

        $cascade->onFailed(function($event) {
            // Count failures
            $this->metrics->increment('cascade.resolution.failed', [
                'key' => $event->key,
            ]);
        });

        return $cascade;
    }
}
```

## Performance Monitoring

### Slow Query Detection

Alert on slow resolutions:

```php
$cascade->onResolved(function(ValueResolved $event) {
    if ($event->durationMs > 100) {
        $this->alerts->slow('Slow cascade resolution', [
            'key' => $event->key,
            'source' => $event->sourceName,
            'duration_ms' => $event->durationMs,
            'threshold_ms' => 100,
        ]);
    }
});
```

### Source Performance Tracking

Track which sources are slowest:

```php
class SourcePerformanceMonitor
{
    private array $stats = [];

    public function monitor(Cascade $cascade): void
    {
        $cascade->onResolved(function(ValueResolved $event) {
            $source = $event->sourceName;

            if (!isset($this->stats[$source])) {
                $this->stats[$source] = [
                    'count' => 0,
                    'total_ms' => 0,
                    'min_ms' => PHP_FLOAT_MAX,
                    'max_ms' => 0,
                ];
            }

            $this->stats[$source]['count']++;
            $this->stats[$source]['total_ms'] += $event->durationMs;
            $this->stats[$source]['min_ms'] = min(
                $this->stats[$source]['min_ms'],
                $event->durationMs
            );
            $this->stats[$source]['max_ms'] = max(
                $this->stats[$source]['max_ms'],
                $event->durationMs
            );
        });
    }

    public function getStats(): array
    {
        return array_map(function($stats) {
            return [
                'count' => $stats['count'],
                'avg_ms' => $stats['total_ms'] / $stats['count'],
                'min_ms' => $stats['min_ms'],
                'max_ms' => $stats['max_ms'],
            ];
        }, $this->stats);
    }
}
```

## Debugging

### Resolution Trace

Trace the resolution process:

```php
class ResolutionTracer
{
    private array $traces = [];

    public function trace(Cascade $cascade): void
    {
        $traceId = uniqid('trace_');

        $cascade->onSourceQueried(function($event) use ($traceId) {
            $this->traces[$traceId][] = [
                'type' => 'query',
                'source' => $event->sourceName,
                'key' => $event->key,
                'timestamp' => $event->timestamp,
            ];
        });

        $cascade->onResolved(function($event) use ($traceId) {
            $this->traces[$traceId][] = [
                'type' => 'resolved',
                'source' => $event->sourceName,
                'key' => $event->key,
                'duration_ms' => $event->durationMs,
            ];
        });

        $cascade->onFailed(function($event) use ($traceId) {
            $this->traces[$traceId][] = [
                'type' => 'failed',
                'key' => $event->key,
                'attempted' => $event->attemptedSources,
            ];
        });
    }

    public function getTrace(string $traceId): array
    {
        return $this->traces[$traceId] ?? [];
    }
}
```

### Debug Mode

Enable verbose debugging in development:

```php
if (app()->environment('local')) {
    $cascade->onSourceQueried(function($event) {
        dump([
            'action' => 'query',
            'source' => $event->sourceName,
            'key' => $event->key,
            'context' => $event->context,
        ]);
    });

    $cascade->onResolved(function($event) {
        dump([
            'action' => 'resolved',
            'key' => $event->key,
            'source' => $event->sourceName,
            'value' => $event->value,
            'duration_ms' => $event->durationMs,
        ]);
    });
}
```

## Audit Logging

### Security Auditing

Track access to sensitive values:

```php
class SecurityAuditor
{
    public function __construct(
        private AuditLogger $audit,
    ) {}

    public function monitor(Cascade $cascade): void
    {
        $cascade->onResolved(function(ValueResolved $event) {
            // Only audit sensitive keys
            if ($this->isSensitive($event->key)) {
                $this->audit->log([
                    'event' => 'credential_accessed',
                    'key' => $event->key,
                    'source' => $event->sourceName,
                    'context' => $event->context,
                    'user_id' => auth()->id(),
                    'ip_address' => request()->ip(),
                    'timestamp' => now(),
                ]);
            }
        });
    }

    private function isSensitive(string $key): bool
    {
        return str_contains($key, 'api-key')
            || str_contains($key, 'secret')
            || str_contains($key, 'password');
    }
}
```

### Compliance Logging

Track data access for compliance:

```php
$cascade->onResolved(function(ValueResolved $event) {
    if (isset($event->context['customer_id'])) {
        $this->compliance->log([
            'event_type' => 'data_access',
            'customer_id' => $event->context['customer_id'],
            'data_key' => $event->key,
            'accessed_by' => auth()->id(),
            'source' => $event->sourceName,
            'timestamp' => now(),
        ]);
    }
});
```

## Cache Statistics

### Cache Hit Rate

Track cache effectiveness:

```php
class CacheStatsCollector
{
    private int $hits = 0;
    private int $misses = 0;

    public function monitor(Cascade $cascade): void
    {
        $cascade->onResolved(function(ValueResolved $event) {
            if ($event->sourceName === 'cache') {
                $this->hits++;
            } else {
                $this->misses++;
            }
        });
    }

    public function getHitRate(): float
    {
        $total = $this->hits + $this->misses;
        return $total > 0 ? ($this->hits / $total) * 100 : 0;
    }

    public function getStats(): array
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit_rate' => $this->getHitRate(),
        ];
    }
}
```

## Error Alerting

### Failed Resolution Alerts

Alert when critical keys fail to resolve:

```php
$cascade->onFailed(function(ResolutionFailed $event) {
    // Alert on critical key failures
    if ($this->isCritical($event->key)) {
        $this->alerts->critical('Critical configuration missing', [
            'key' => $event->key,
            'attempted_sources' => $event->attemptedSources,
            'context' => $event->context,
        ]);
    }
});

private function isCritical(string $key): bool
{
    return in_array($key, [
        'database-password',
        'api-key',
        'encryption-key',
    ]);
}
```

### Source Failure Detection

Detect when sources consistently fail:

```php
class SourceHealthMonitor
{
    private array $failures = [];

    public function monitor(Cascade $cascade): void
    {
        $cascade->onSourceQueried(function($event) {
            $this->failures[$event->sourceName] = 0;
        });

        $cascade->onFailed(function($event) {
            foreach ($event->attemptedSources as $source) {
                $this->failures[$source] = ($this->failures[$source] ?? 0) + 1;

                // Alert if source fails 10 times in a row
                if ($this->failures[$source] >= 10) {
                    $this->alerts->warning("Source consistently failing", [
                        'source' => $source,
                        'consecutive_failures' => $this->failures[$source],
                    ]);
                }
            }
        });
    }
}
```

## Multiple Listeners

Register multiple listeners for the same event:

```php
$cascade = Cascade::from()
    ->fallbackTo($source)
    // First listener: logging
    ->onResolved(function($event) {
        $this->logger->info('Resolved', ['key' => $event->key]);
    })
    // Second listener: metrics
    ->onResolved(function($event) {
        $this->metrics->increment('resolutions');
    })
    // Third listener: audit
    ->onResolved(function($event) {
        $this->audit->log($event);
    });
```

## Event Data Reference

### SourceQueried Properties

```php
class SourceQueried
{
    public string $sourceName;      // Name of source being queried
    public string $key;             // Key being resolved
    public array $context;          // Resolution context
    public float $timestamp;        // Unix timestamp when query started
}
```

### ValueResolved Properties

```php
class ValueResolved
{
    public string $key;             // Key that was resolved
    public mixed $value;            // The resolved value
    public string $sourceName;      // Source that provided value
    public float $durationMs;       // Resolution time in milliseconds
    public array $attemptedSources; // All sources attempted
    public array $context;          // Resolution context
}
```

### ResolutionFailed Properties

```php
class ResolutionFailed
{
    public string $key;             // Key that failed to resolve
    public array $attemptedSources; // All sources attempted
    public array $context;          // Resolution context
    public float $timestamp;        // Unix timestamp of failure
}
```

## Best Practices

### 1. Keep Listeners Lightweight

```php
// Good: Quick logging
$cascade->onResolved(function($event) {
    $this->logger->info('Resolved', ['key' => $event->key]);
});

// Avoid: Heavy processing
$cascade->onResolved(function($event) {
    $this->sendEmail($event); // Too slow for sync processing
    $this->updateDatabase($event); // Use queued jobs instead
});
```

### 2. Use Events for Observability

```php
// Good: Non-intrusive monitoring
$cascade->onResolved(fn($e) => $this->metrics->increment('resolutions'));

// Good: Debugging aid
$cascade->onFailed(fn($e) => $this->logger->warning('Failed', ['key' => $e->key]));
```

### 3. Protect Against Exceptions

```php
$cascade->onResolved(function($event) {
    try {
        $this->metrics->record($event);
    } catch (\Throwable $e) {
        // Don't let listener errors break resolution
        $this->logger->error('Metrics failed', ['error' => $e->getMessage()]);
    }
});
```

## Next Steps

- Use events with [Bulk Resolution](./bulk-resolution.md) for batch monitoring
- Combine events with [Result Metadata](./result-metadata.md) for detailed tracking
- Explore [Advanced Usage](./advanced-usage.md) for complex event patterns
- See [Cookbook](./cookbook.md) for real-world event handling examples
