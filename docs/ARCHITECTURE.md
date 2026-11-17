# Architecture

## Overview

This library implements a server-side core for Gutenberg dynamic blocks, providing a parser and serializer for the WordPress block grammar.

## Components

1. **`BlockParser` and `ParsedBlock`**: Tokenizes and parses WordPress block comments into an immutable tree structure. Enforces limits on input size and nesting depth.
2. **`BlockSerializer`**: Reconstructs serialized WordPress block comment markup from `ParsedBlock` trees.
3. **`Registry`**: Manages block metadata and `block.json` (API version 3) schemas, enforcing strict attribute validation.
4. **`Renderer`**: Generates deterministic HTML, handling per-instance ARIA IDs (`trysac-<block>-<n>`) and rendering compliant HTML based on validated attributes.
5. **`Html`**: Provides secure escaping for text and attributes.
6. **`Cli`**: Enables rendering serialized block files from the command line.