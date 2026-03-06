<?php

namespace App\Services;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class SkillMdParser
{
    /**
     * Parse a SKILL.md file content into structured data.
     *
     * @return array{name: ?string, description: ?string, license: ?string, compatibility: ?array, metadata: ?array, allowed_tools: ?array, body: ?string, valid: bool, errors: list<string>}
     */
    public function parse(string $content): array
    {
        $result = [
            'name' => null,
            'description' => null,
            'license' => null,
            'compatibility' => null,
            'metadata' => null,
            'allowed_tools' => null,
            'body' => null,
            'valid' => false,
            'errors' => [],
        ];

        $frontmatter = $this->extractFrontmatter($content);

        if ($frontmatter['yaml'] !== null) {
            $parsed = $this->parseYaml($frontmatter['yaml']);

            if ($parsed === null) {
                $result['errors'][] = 'Invalid YAML frontmatter.';
            } else {
                $result['name'] = $parsed['name'] ?? null;
                $result['description'] = $parsed['description'] ?? null;
                $result['license'] = $parsed['license'] ?? null;
                $result['compatibility'] = $parsed['compatibility'] ?? null;
                $result['metadata'] = $parsed['metadata'] ?? null;
                $result['allowed_tools'] = $parsed['allowed-tools'] ?? $parsed['allowed_tools'] ?? null;
            }
        } else {
            $result['errors'][] = 'No YAML frontmatter found.';
        }

        $body = trim($frontmatter['body'] ?? '');
        $result['body'] = $body !== '' ? $body : null;

        $this->validate($result);

        return $result;
    }

    /**
     * @return array{yaml: ?string, body: ?string}
     */
    protected function extractFrontmatter(string $content): array
    {
        $content = ltrim($content);

        if (! str_starts_with($content, '---')) {
            return ['yaml' => null, 'body' => $content];
        }

        $endPos = strpos($content, '---', 3);

        if ($endPos === false) {
            return ['yaml' => null, 'body' => $content];
        }

        $yaml = substr($content, 3, $endPos - 3);
        $body = substr($content, $endPos + 3);

        return ['yaml' => trim($yaml), 'body' => trim($body)];
    }

    protected function parseYaml(string $yaml): ?array
    {
        try {
            $parsed = Yaml::parse($yaml);

            return is_array($parsed) ? $parsed : null;
        } catch (ParseException) {
            // Fallback: try quoting unquoted colon values
            $fixed = preg_replace_callback('/^(\s*\w+):\s+(.+)$/m', function ($matches) {
                $value = $matches[2];
                if (str_contains($value, ':') && ! preg_match('/^["\']/', $value)) {
                    $value = '"'.addslashes($value).'"';
                }

                return $matches[1].': '.$value;
            }, $yaml);

            try {
                $parsed = Yaml::parse($fixed);

                return is_array($parsed) ? $parsed : null;
            } catch (ParseException) {
                return null;
            }
        }
    }

    /**
     * @param  array{name: ?string, description: ?string, valid: bool, errors: list<string>}  $result
     */
    protected function validate(array &$result): void
    {
        if ($result['name'] === null || $result['name'] === '') {
            $result['errors'][] = 'Missing required field: name.';
        } elseif (strlen($result['name']) > 64) {
            $result['errors'][] = 'Name must be 64 characters or fewer.';
        } elseif (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $result['name'])) {
            $result['errors'][] = 'Name must be lowercase alphanumeric with hyphens.';
        }

        if ($result['description'] === null || $result['description'] === '') {
            $result['errors'][] = 'Missing required field: description.';
        } elseif (strlen($result['description']) > 1024) {
            $result['errors'][] = 'Description must be 1024 characters or fewer.';
        }

        $result['valid'] = $result['errors'] === [];
    }
}
