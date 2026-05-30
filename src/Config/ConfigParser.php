<?php

declare(strict_types=1);

namespace DocbookCS\Config;

final class ConfigParser
{
    private const string NAMESPACE_URI = 'https://php.github.io/docbook-cs/config';

    /**
     * @throws ConfigParserException if the file cannot be read or contains invalid XML.
     * @throws \InvalidArgumentException if a SniffEntry is constructed with an invalid class name.
     */
    public function parseFile(string $filePath): ConfigData
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw ConfigParserException::fileNotFound($filePath);
        }

        $basePath = dirname(realpath($filePath) ?: '');

        $previousUseErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string(file_get_contents($filePath) ?: '');
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        if ($xml === false) {
            $message = $errors !== []
                ? $errors[0]->message
                : 'Unknown parse error'; // @codeCoverageIgnore
            throw ConfigParserException::invalidXml($filePath, trim($message));
        }

        return $this->parse($xml, $basePath);
    }

    /**
     * @throws ConfigParserException if the XML is invalid.
     * @throws \InvalidArgumentException if a SniffEntry is constructed with an invalid class name.
     */
    public function parseString(string $xmlContent, string $basePath): ConfigData
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        if ($xml === false) {
            $message = $errors !== []
                ? $errors[0]->message
                : 'Unknown parse error'; // @codeCoverageIgnore
            throw ConfigParserException::invalidXml('(string)', trim($message));
        }

        return $this->parse($xml, $basePath);
    }

    /**
     * @throws ConfigParserException if the XML is invalid or required elements/attributes are missing.
     * @throws \InvalidArgumentException if a SniffEntry is constructed with an invalid class name.
     */
    private function parse(\SimpleXMLElement $root, string $basePath): ConfigData
    {
        // Register the namespace for xpath queries.
        $root->registerXPathNamespace('d', self::NAMESPACE_URI);

        return new ConfigData(
            projectRoots: $this->parseProjectRoots($root, $basePath),
            sniffs: $this->parseSniffs($root),
            includePaths: $this->parsePaths($root, $basePath),
            excludePatterns: $this->parseExcludePatterns($root),
            entityPaths: $this->parseEntityPaths($root, $basePath),
            basePath: $basePath,
        );
    }

    /**
     * @return array<string, string>
     * @throws ConfigParserException if the <project> element is missing or if
     * the base path is not within any of the specified project roots.
     */
    private function parseProjectRoots(\SimpleXMLElement $root, string $basePath): array
    {
        if (!isset($root->project)) {
            $name = basename($basePath);

            return [
                dirname($basePath) . '/' . $name => $name,
                $basePath => $name,
            ];
        }

        $directories = [];

        foreach ($root->project->directory ?: [] as $directory) {
            $directoryName = (string) $directory;
            $directories[dirname($basePath) . '/' . $directoryName] = $directoryName;

            if ($directory->attributes()->alias) {
                $alias = (string) $directory->attributes()->alias;
                $directories[dirname($basePath) . '/' . $alias] = $directoryName;
            }
        }

        return $directories;
    }

    /**
     * @return list<SniffEntry>
     * @throws ConfigParserException if required elements or attributes are missing.
     * @throws \InvalidArgumentException if a SniffEntry is constructed with an invalid class name.
     */
    private function parseSniffs(\SimpleXMLElement $root): array
    {
        $sniffsNode = $root->sniffs ?? null;

        if ($sniffsNode === null || count($sniffsNode->sniff) === 0) {
            throw ConfigParserException::missingElement('sniffs');
        }

        $entries = [];

        foreach ($sniffsNode->sniff as $sniffNode) {
            $class = (string)($sniffNode['class'] ?? '');

            if ($class === '') {
                throw ConfigParserException::missingAttribute('sniff', 'class');
            }

            $properties = [];
            foreach ($sniffNode->property as $prop) {
                $name = (string)($prop['name'] ?? '');
                $value = (string)($prop['value'] ?? '');

                if ($name === '') {
                    throw ConfigParserException::missingAttribute('property', 'name');
                }

                $properties[$name] = $value;
            }

            $entries[] = new SniffEntry($class, $properties);
        }

        return $entries;
    }

    /** @return list<string> */
    private function parsePaths(\SimpleXMLElement $root, string $basePath): array
    {
        $resolved = [];

        if (!isset($root->paths)) {
            return $resolved;
        }

        foreach ($root->paths->path as $pathNode) {
            $raw = trim((string)$pathNode);

            if ($raw === '') {
                continue;
            }

            $resolved[] = $this->resolvePath($raw, $basePath);
        }

        return $resolved;
    }

    /** @return list<string> */
    private function parseExcludePatterns(\SimpleXMLElement $root): array
    {
        $patterns = [];

        if (!isset($root->exclude)) {
            return $patterns;
        }

        foreach ($root->exclude->pattern as $patternNode) {
            $raw = trim((string)$patternNode);

            if ($raw !== '') {
                $patterns[] = $raw;
            }
        }

        return $patterns;
    }

    /** @return list<string> */
    private function parseEntityPaths(\SimpleXMLElement $root, string $basePath): array
    {
        $resolved = [];

        if (!isset($root->entities)) {
            return $resolved;
        }

        foreach ($root->entities->children() as $node) {
            $raw = trim((string)$node);
            if ($raw === '') {
                continue;
            }

            $resolved[] = $this->resolvePath($raw, $basePath);
        }

        return $resolved;
    }

    private function resolvePath(string $raw, string $basePath): string
    {
        if ($this->isAbsolute($raw)) {
            return $this->normalizePath($raw);
        }

        return $this->normalizePath($basePath . DIRECTORY_SEPARATOR . $raw);
    }

    private function isAbsolute(string $path): bool
    {
        // Unix absolute
        if (str_starts_with($path, '/')) {
            return true;
        }

        // Windows absolute (e.g. C:\)
        if (preg_match('#^[a-zA-Z]:[/\\\\]#', $path) === 1) {
            return true;
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        $prefix = '';
        if (preg_match('#^([a-zA-Z]:/|/)#', $path, $m)) {
            $prefix = $m[1];
            $path = substr($path, strlen($prefix));
        }

        $segments = explode('/', $path);
        $stack = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' && $stack !== [] && end($stack) !== '..') {
                array_pop($stack);
            } else {
                $stack[] = $segment;
            }
        }

        return $prefix . implode('/', $stack);
    }
}
