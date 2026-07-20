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
use DocbookCS\Source\File;

final class MySniff extends AbstractSniff
{
    public static function getCode(): string
    {
        return 'Acme.MySniff';
    }

    public function process(\DOMDocument $document, File $file): array
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

## CLI Scope

By default, DocbookCS checks the current Git diff from its upstream branch point
through the working tree. Alternatively, a unified diff can be piped or file and
directory paths passed. The inspection scope is limited to the given diff or the
full contents of the given file paths.

XML references are expanded by default, but violations and fixes remain limited
to the given scope. With `--wide`, every file, inferred from paths or diff, as
a whole will be checked and referenced `SYSTEM` XML files recursively included
in violation reports and fixing.

| Input      | `--wide` | Full File(s) | References |
|------------|---------:|-------------:|-----------:|
| none       |       no |           no |         no |
| none       |      yes |          yes |        yes |
| path       |       no |          yes |         no |
| path       |      yes |          yes |        yes |
| piped diff |       no |           no |         no |
| piped diff |      yes |          yes |        yes |

## License

Apache 2.0
