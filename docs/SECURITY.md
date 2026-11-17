# Security and Threat Model

## Defended Threats

- **Insecure Attributes**: All block attributes are validated against `Registry` schemas. Unexpected keys, incorrect types, or out-of-range integers cause `BlockException` ('invalid_attribute').
- **XSS**: All dynamic output rendered into HTML text or attribute contexts is escaped via `Html::text()` or `Html::attr()` using `htmlspecialchars` with `ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5`.
- **Unsafe URLs**: URLs in `trysac/cta-button` and `trysac/figure` are validated. We strip ASCII control characters and reject non-whitelisted schemes (`http`, `https`, `mailto`, relative paths, fragment identifiers). Violations throw `BlockException` ('unsafe_url').
- **Resource Exhaustion**: `BlockParser::parse` defaults to 1 MiB (`input_too_large`) and 32 levels of nesting (`too_deep`).
- **Information Exposure**: `BlockException` messages are truncated to 200 characters to prevent log injection or heap exhaustion from massive inputs.

## Not Defended

- **Inner HTML**: The library treats `innerHtml` as trusted post content. Malicious markup inside rich-text fields (within allowed blocks) is preserved as stored.