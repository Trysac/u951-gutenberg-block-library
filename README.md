# gutenberg-block-library

Accessible server-rendered Gutenberg blocks with a WordPress block-grammar parser and serializer, attribute validation and markup that passes axe-core with zero violations.

## Problem

WordPress sites ship custom blocks whose markup misses ARIA roles, alt text or keyboard semantics, and editors see invalid-block warnings when saved markup drifts from the block code. This package provides a standalone implementation of the Gutenberg serialization grammar and a rendering layer that ensures accessible output, testable outside of a WordPress environment.

## Installation

```bash
composer require trysac/u951-gutenberg-block-library
```

## Usage

```php
use Trysac\GutenbergBlockLibrary\BlockParser;
use Trysac\GutenbergBlockLibrary\BlockSerializer;
use Trysac\GutenbergBlockLibrary\Renderer;

$raw = '<!-- wp:trysac/notice {"type":"warning"} -->Careful<!-- /wp:trysac/notice -->';
$blocks = BlockParser::parse($raw);
$serialized = BlockSerializer::serialize($blocks);
$html = (new Renderer())->renderAll($serialized);
```

## Public API Summary

- `BlockParser`: `parse(string $content, int $maxBytes, int $maxDepth)` parses serialization grammar. Throws `BlockException` ('input_too_large', 'too_deep', 'unclosed_block', 'invalid_attributes').
- `ParsedBlock`: Immutable representation of a block.
- `BlockSerializer`: `serialize(list<ParsedBlock>)` reconstructs the block comments.
- `Registry`: `metadata(string $name)` and `validateAttributes(string $name, array $attributes)` enforce schemas. Throws `BlockException` ('unknown_block', 'invalid_attribute').
- `Renderer`: `renderAll(string $content)` and `render(ParsedBlock $block)` generate HTML. Throws `BlockException` ('unsafe_url', 'missing_alt').
- `Html`: Escaping helpers using `htmlspecialchars` with `ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5`.

## Why not WordPress core blocks?

Core block libraries are tightly coupled to the WordPress environment and database. This library provides a decoupled, type-safe implementation that allows testing, parsing, and rendering of block markup in isolation.

See [docs/SECURITY.md](docs/SECURITY.md) for the threat model.