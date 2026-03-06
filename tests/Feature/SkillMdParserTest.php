<?php

use App\Services\SkillMdParser;

beforeEach(function () {
    $this->parser = new SkillMdParser;
});

test('parses valid SKILL.md with all fields', function () {
    $content = <<<'MD'
---
name: pdf-processing
description: Processes PDF files for extraction and analysis.
license: MIT
compatibility:
  models:
    - claude-sonnet
metadata:
  author: Test Corp
allowed-tools:
  - bash
  - text_editor
---

# PDF Processing

Extract text from PDF files and analyze content.
MD;

    $result = $this->parser->parse($content);

    expect($result['valid'])->toBeTrue()
        ->and($result['name'])->toBe('pdf-processing')
        ->and($result['description'])->toBe('Processes PDF files for extraction and analysis.')
        ->and($result['license'])->toBe('MIT')
        ->and($result['compatibility'])->toBe(['models' => ['claude-sonnet']])
        ->and($result['metadata'])->toBe(['author' => 'Test Corp'])
        ->and($result['allowed_tools'])->toBe(['bash', 'text_editor'])
        ->and($result['body'])->toContain('# PDF Processing')
        ->and($result['errors'])->toBeEmpty();
});

test('parses minimal valid SKILL.md', function () {
    $content = <<<'MD'
---
name: my-skill
description: A simple skill.
---

Body text here.
MD;

    $result = $this->parser->parse($content);

    expect($result['valid'])->toBeTrue()
        ->and($result['name'])->toBe('my-skill')
        ->and($result['description'])->toBe('A simple skill.')
        ->and($result['body'])->toBe('Body text here.');
});

test('fails on missing name', function () {
    $content = <<<'MD'
---
description: Has description but no name.
---

Body.
MD;

    $result = $this->parser->parse($content);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('Missing required field: name.');
});

test('fails on missing description', function () {
    $content = <<<'MD'
---
name: valid-name
---

Body.
MD;

    $result = $this->parser->parse($content);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('Missing required field: description.');
});

test('fails on invalid name format', function () {
    $content = <<<'MD'
---
name: Invalid Name
description: Has spaces.
---
MD;

    $result = $this->parser->parse($content);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('Name must be lowercase alphanumeric with hyphens.');
});

test('fails on name exceeding 64 characters', function () {
    $longName = str_repeat('a', 65);
    $content = "---\nname: {$longName}\ndescription: Test.\n---\n";

    $result = $this->parser->parse($content);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('Name must be 64 characters or fewer.');
});

test('fails on description exceeding 1024 characters', function () {
    $longDesc = str_repeat('a', 1025);
    $content = "---\nname: valid-name\ndescription: \"{$longDesc}\"\n---\n";

    $result = $this->parser->parse($content);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('Description must be 1024 characters or fewer.');
});

test('handles missing frontmatter', function () {
    $content = "# Just a markdown file\n\nNo frontmatter here.";

    $result = $this->parser->parse($content);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toContain('No YAML frontmatter found.');
});

test('handles malformed YAML with unquoted colons', function () {
    $content = <<<'MD'
---
name: my-skill
description: A skill with colons: and more text
---

Body.
MD;

    $result = $this->parser->parse($content);

    expect($result['valid'])->toBeTrue()
        ->and($result['name'])->toBe('my-skill');
});

test('body is null when no content after frontmatter', function () {
    $content = <<<'MD'
---
name: empty-body
description: No body content.
---
MD;

    $result = $this->parser->parse($content);

    expect($result['valid'])->toBeTrue()
        ->and($result['body'])->toBeNull();
});
