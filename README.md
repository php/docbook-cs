# DocbookCS

A static-analysis linter for DocBook XML files. It scans XML documentation sources and reports style and convention violations.

**Full documentation:** [php.github.io/docbook-cs](https://php.github.io/docbook-cs)

---

## Contributing

### Requirements

- PHP 8.5+
- Extensions: `dom`, `libxml`, `simplexml`

### Setup

```bash
composer install
```

### Running checks

```bash
# Tests
vendor/bin/phpunit

# Static analysis
vendor/bin/phpstan

# Code style
vendor/bin/phpcs
```

### Writing a sniff

Implement `DocbookCS\Sniff\SniffInterface` (or extend `AbstractSniff`):

```php
namespace Acme\DocbookSniffs;

use DocbookCS\Sniff\AbstractSniff;

final class MySniff extends AbstractSniff
{
    public function getCode(): string
    {
        return 'Acme.MySniff';
    }

    public function process(\DOMDocument $document, string $content, string $filePath): array
    {
        $violations = [];
        // ... inspect $document, add violations via $this->createViolation(...)
        return $violations;
    }
}
```

Register it in your config:

```xml
<sniff class="Acme\DocbookSniffs\MySniff" />
```

## License

Apache 2.0
