# Testing Strategy

## Unit & Acceptance

- `tests/BlockLibraryTest.php`: Verifies `BlockParser`, `BlockSerializer`, `Registry`, and `Renderer` behavior. Covers error codes like `input_too_large`, `too_deep`, `unclosed_block`, `invalid_attributes`, `unknown_block`, `invalid_attribute`, `unsafe_url`, and `missing_alt`. Tests URL scheme enforcement and attribute validation logic.

## Conformance

- `tests/AxeConformanceTest.php`: Renders fixtures into full HTML5 documents and executes `axe-core` via Node.js in CI, asserting zero accessibility violations.