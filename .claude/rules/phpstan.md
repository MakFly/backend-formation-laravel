# PHPStan Rules

## Memory Limit
**ALWAYS** use `--memory-limit=-1` when running PHPStan.

```bash
# Correct
./vendor/bin/phpstan analyse --memory-limit=-1

# Wrong
./vendor/bin/phpstan analyse --memory-limit=2G
```

## Configuration
PHPStan is configured via `phpstan.neon`:
- Uses **Larastan** extension for Laravel support
- Level **5** for strict type checking
- Baseline file for legacy errors (`phpstan-baseline.neon`)

## Baseline
The baseline contains 276 legacy errors that are ignored.
To reduce technical debt, fix errors and regenerate:

```bash
./vendor/bin/phpstan analyse --memory-limit=-1 --generate-baseline
```
