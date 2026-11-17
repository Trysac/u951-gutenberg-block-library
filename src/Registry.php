<?php

declare(strict_types=1);

namespace Trysac\GutenbergBlockLibrary;

/**
 * Registry of supported dynamic Gutenberg blocks and their block.json schemas.
 */
final class Registry
{
    /**
     * Returns the list of all registered block names.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * Returns the block.json metadata array for the given block name.
     *
     * @return array{
     *     apiVersion: int,
     *     name: string,
     *     title: string,
     *     category: string,
     *     description: string,
     *     parent?: list<string>,
     *     attributes: array<string, array{
     *         type: 'string'|'boolean'|'integer',
     *         enum?: list<string>,
     *         default?: string|bool|int,
     *         required?: bool,
     *         minimum?: int,
     *         maximum?: int,
     *         nonEmpty?: bool
     *     }>,
     *     supports: array<string, bool>
     * }
     *
     * @throws BlockException with errorCode 'unknown_block' if the block is not registered
     */
    public static function metadata(string $name): array
    {
        $defs = self::definitions();
        if (!isset($defs[$name])) {
            throw new BlockException('unknown_block', sprintf('Unknown block "%s".', $name));
        }

        return $defs[$name];
    }

    /**
     * Returns the formatted block.json string for the given block name.
     *
     * @throws BlockException with errorCode 'unknown_block' if the block is not registered
     */
    public static function blockJson(string $name): string
    {
        $meta = self::metadata($name);

        $cleanAttributes = [];
        foreach ($meta['attributes'] as $attrName => $spec) {
            $entry = ['type' => $spec['type']];
            if (isset($spec['enum'])) {
                $entry['enum'] = $spec['enum'];
            }
            if (array_key_exists('default', $spec)) {
                $entry['default'] = $spec['default'];
            }
            $cleanAttributes[$attrName] = $entry;
        }

        $payload = [
            '$schema' => 'https://schemas.wp.org/trunk/block.json',
            'apiVersion' => $meta['apiVersion'],
            'name' => $meta['name'],
            'title' => $meta['title'],
            'category' => $meta['category'],
            'description' => $meta['description'],
        ];

        if (isset($meta['parent'])) {
            $payload['parent'] = $meta['parent'];
        }

        $payload['attributes'] = (object) $cleanAttributes;
        $payload['supports'] = $meta['supports'];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Validates and resolves raw block attributes against the block's registered schema.
     *
     * @param string $name registered block name
     * @param array<string, mixed> $attributes raw attributes from the block comment
     *
     * @return array<string, string|bool|int>
     *
     * @throws BlockException when the block is unknown or an attribute is invalid
     */
    public static function validateAttributes(string $name, array $attributes): array
    {
        $meta = self::metadata($name);
        $schema = $meta['attributes'];

        foreach ($attributes as $key => $_) {
            if (!isset($schema[$key])) {
                throw new BlockException(
                    'invalid_attribute',
                    sprintf('Unknown attribute "%s" on block "%s".', $key, $name),
                );
            }
        }

        /** @var array<string, string|bool|int> $resolved */
        $resolved = [];

        foreach ($schema as $attrName => $spec) {
            if (!array_key_exists($attrName, $attributes)) {
                if (($spec['required'] ?? false) === true) {
                    throw new BlockException(
                        'invalid_attribute',
                        sprintf('Missing required attribute "%s" on block "%s".', $attrName, $name),
                    );
                }
                if (array_key_exists('default', $spec)) {
                    $resolved[$attrName] = $spec['default'];
                }
                continue;
            }

            $value = $attributes[$attrName];
            $expectedType = $spec['type'];

            if ('string' === $expectedType) {
                if (!is_string($value)) {
                    throw new BlockException(
                        'invalid_attribute',
                        sprintf('Attribute "%s" on block "%s" must be a string.', $attrName, $name),
                    );
                }
                if (($spec['nonEmpty'] ?? false) === true && '' === trim($value)) {
                    throw new BlockException(
                        'invalid_attribute',
                        sprintf('Attribute "%s" on block "%s" must not be empty.', $attrName, $name),
                    );
                }
                if (isset($spec['enum']) && !in_array($value, $spec['enum'], true)) {
                    throw new BlockException(
                        'invalid_attribute',
                        sprintf('Attribute "%s" on block "%s" is not an allowed value.', $attrName, $name),
                    );
                }
                $resolved[$attrName] = $value;
            } elseif ('boolean' === $expectedType) {
                if (!is_bool($value)) {
                    throw new BlockException(
                        'invalid_attribute',
                        sprintf('Attribute "%s" on block "%s" must be a boolean.', $attrName, $name),
                    );
                }
                $resolved[$attrName] = $value;
            } elseif ('integer' === $expectedType) {
                if (!is_int($value)) {
                    throw new BlockException(
                        'invalid_attribute',
                        sprintf('Attribute "%s" on block "%s" must be an integer.', $attrName, $name),
                    );
                }
                if (isset($spec['minimum']) && $value < $spec['minimum']) {
                    throw new BlockException(
                        'invalid_attribute',
                        sprintf('Attribute "%s" on block "%s" is below minimum %d.', $attrName, $name, $spec['minimum']),
                    );
                }
                if (isset($spec['maximum']) && $value > $spec['maximum']) {
                    throw new BlockException(
                        'invalid_attribute',
                        sprintf('Attribute "%s" on block "%s" exceeds maximum %d.', $attrName, $name, $spec['maximum']),
                    );
                }
                $resolved[$attrName] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Internal dictionary of block definitions.
     *
     * @return array<string, array{
     *     apiVersion: int,
     *     name: string,
     *     title: string,
     *     category: string,
     *     description: string,
     *     parent?: list<string>,
     *     attributes: array<string, array{
     *         type: 'string'|'boolean'|'integer',
     *         enum?: list<string>,
     *         default?: string|bool|int,
     *         required?: bool,
     *         minimum?: int,
     *         maximum?: int,
     *         nonEmpty?: bool
     *     }>,
     *     supports: array<string, bool>
     * }>
     */
    private static function definitions(): array
    {
        return [
            'trysac/notice' => [
                'apiVersion' => 3,
                'name' => 'trysac/notice',
                'title' => 'Notice',
                'category' => 'widgets',
                'description' => 'Accessible status or alert notice banner.',
                'attributes' => [
                    'type' => [
                        'type' => 'string',
                        'enum' => ['info', 'success', 'warning', 'error'],
                        'default' => 'info',
                    ],
                ],
                'supports' => [
                    'html' => false,
                ],
            ],
            'trysac/accordion' => [
                'apiVersion' => 3,
                'name' => 'trysac/accordion',
                'title' => 'Accordion',
                'category' => 'widgets',
                'description' => 'Accessible disclosure accordion following WAI-ARIA Authoring Practices.',
                'attributes' => [
                    'title' => [
                        'type' => 'string',
                        'required' => true,
                        'nonEmpty' => true,
                    ],
                    'open' => [
                        'type' => 'boolean',
                        'default' => false,
                    ],
                    'headingLevel' => [
                        'type' => 'integer',
                        'default' => 3,
                        'minimum' => 2,
                        'maximum' => 6,
                    ],
                ],
                'supports' => [
                    'html' => false,
                ],
            ],
            'trysac/cta-button' => [
                'apiVersion' => 3,
                'name' => 'trysac/cta-button',
                'title' => 'Call to Action Button',
                'category' => 'design',
                'description' => 'Accessible link button with strict URL scheme validation.',
                'attributes' => [
                    'text' => [
                        'type' => 'string',
                        'required' => true,
                        'nonEmpty' => true,
                    ],
                    'url' => [
                        'type' => 'string',
                        'required' => true,
                    ],
                ],
                'supports' => [
                    'html' => false,
                ],
            ],
            'trysac/figure' => [
                'apiVersion' => 3,
                'name' => 'trysac/figure',
                'title' => 'Figure',
                'category' => 'media',
                'description' => 'Accessible image figure enforcing alt text or explicit decorative state.',
                'attributes' => [
                    'url' => [
                        'type' => 'string',
                        'required' => true,
                    ],
                    'alt' => [
                        'type' => 'string',
                        'default' => '',
                    ],
                    'caption' => [
                        'type' => 'string',
                        'default' => '',
                    ],
                    'decorative' => [
                        'type' => 'boolean',
                        'default' => false,
                    ],
                ],
                'supports' => [
                    'html' => false,
                ],
            ],
            'trysac/tabs' => [
                'apiVersion' => 3,
                'name' => 'trysac/tabs',
                'title' => 'Tabs',
                'category' => 'widgets',
                'description' => 'Accessible tabbed interface container.',
                'attributes' => [],
                'supports' => [
                    'html' => false,
                ],
            ],
            'trysac/tab' => [
                'apiVersion' => 3,
                'name' => 'trysac/tab',
                'title' => 'Tab',
                'category' => 'widgets',
                'description' => 'Single tab panel inside a Tabs container.',
                'parent' => ['trysac/tabs'],
                'attributes' => [
                    'label' => [
                        'type' => 'string',
                        'required' => true,
                        'nonEmpty' => true,
                    ],
                ],
                'supports' => [
                    'html' => false,
                ],
            ],
        ];
    }
}
