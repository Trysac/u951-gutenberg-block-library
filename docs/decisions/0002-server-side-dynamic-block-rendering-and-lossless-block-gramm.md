# Server-Side Dynamic Block Rendering and Lossless Block Grammar Parser

Status: accepted (generated, pending human review)

## Context

Custom Gutenberg blocks often suffer from editor invalidation errors when static saved HTML drifts from updated component templates, and their frontend markup frequently fails WCAG/ARIA checks because it cannot be easily tested outside a full WordPress runtime.

## Decision

Implement dynamic blocks whose serialized representation uses standard WordPress block comments while rendering all frontend HTML in PHP via a strict schema Registry and Renderer. Preserve raw block names, raw attribute JSON strings, and interleaved innerContent chunks on ParsedBlock so BlockSerializer::serialize(BlockParser::parse($x)) reproduces committed fixtures byte-for-byte.

## Consequences

Block markup can be evolved on the server and verified in isolation with PHPUnit and axe-core in jsdom without invalidating existing posts or requiring a running WordPress database.
