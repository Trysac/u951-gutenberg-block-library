<?php

declare(strict_types=1);

namespace Trysac\GutenbergBlockLibrary;

/**
 * Server-side renderer for registered Gutenberg dynamic blocks.
 */
final class Renderer
{
    /**
     * Per-instance counter for deterministic ARIA element IDs ('trysac-<block>-<n>').
     *
     * @var array<string, int>
     */
    private array $counters = [];

    /**
     * Parses and renders all blocks in a serialized block content string.
     *
     * @throws BlockException when parsing, validation, or rendering fails
     */
    public function renderAll(string $content): string
    {
        $blocks = BlockParser::parse($content);
        $html = '';
        foreach ($blocks as $block) {
            $html .= $this->render($block);
        }

        return $html;
    }

    /**
     * Renders a single ParsedBlock into accessible HTML.
     *
     * @throws BlockException when the block name is unknown or attributes violate schema/security constraints
     */
    public function render(ParsedBlock $block): string
    {
        if ('' === $block->name) {
            return $block->innerHtml;
        }

        if (str_starts_with($block->name, 'core/')) {
            return $this->renderInnerContent($block);
        }

        $attrs = Registry::validateAttributes($block->name, $block->attributes);

        return match ($block->name) {
            'trysac/notice' => $this->renderNotice($block, $attrs),
            'trysac/accordion' => $this->renderAccordion($block, $attrs),
            'trysac/cta-button' => $this->renderCtaButton($attrs),
            'trysac/figure' => $this->renderFigure($attrs),
            'trysac/tabs' => $this->renderTabs($block),
            'trysac/tab' => $this->renderStandaloneTab($block, $attrs),
            default => throw new BlockException('unknown_block', sprintf('Unknown block "%s".', $block->name)),
        };
    }

    /**
     * Renders a trysac/notice block.
     *
     * @param array<string, string|bool|int> $attrs
     */
    private function renderNotice(ParsedBlock $block, array $attrs): string
    {
        $type = isset($attrs['type']) ? (string) $attrs['type'] : 'info';
        $role = ('warning' === $type || 'error' === $type) ? 'alert' : 'status';
        $body = $this->renderInnerContent($block);

        return sprintf(
            '<div class="wp-block-trysac-notice is-type-%s" role="%s">%s</div>',
            Html::attr($type),
            Html::attr($role),
            $body,
        );
    }

    /**
     * Renders a trysac/accordion block following the WAI-ARIA Authoring Practices accordion pattern.
     *
     * @param array<string, string|bool|int> $attrs
     */
    private function renderAccordion(ParsedBlock $block, array $attrs): string
    {
        $panelId = $this->nextId('accordion');
        $buttonId = $panelId . '-button';
        $title = isset($attrs['title']) ? (string) $attrs['title'] : '';
        $open = isset($attrs['open']) && (bool) $attrs['open'];
        $headingLevel = isset($attrs['headingLevel']) ? (int) $attrs['headingLevel'] : 3;
        $headingTag = 'h' . (string) $headingLevel;
        $expandedAttr = $open ? 'true' : 'false';
        $hiddenAttr = $open ? '' : ' hidden';
        $body = $this->renderInnerContent($block);

        return sprintf(
            '<div class="wp-block-trysac-accordion"><%1$s class="wp-block-trysac-accordion__heading"><button type="button" id="%2$s" class="wp-block-trysac-accordion__trigger" aria-expanded="%3$s" aria-controls="%4$s">%5$s</button></%1$s><div id="%4$s" class="wp-block-trysac-accordion__panel" role="region" aria-labelledby="%2$s"%6$s>%7$s</div></div>',
            $headingTag,
            Html::attr($buttonId),
            $expandedAttr,
            Html::attr($panelId),
            Html::text($title),
            $hiddenAttr,
            $body,
        );
    }

    /**
     * Renders a trysac/cta-button block.
     *
     * @param array<string, string|bool|int> $attrs
     */
    private function renderCtaButton(array $attrs): string
    {
        $text = isset($attrs['text']) ? (string) $attrs['text'] : '';
        $url = Html::validateUrl(isset($attrs['url']) ? (string) $attrs['url'] : '');

        return sprintf(
            '<a class="wp-block-trysac-cta-button" href="%s">%s</a>',
            Html::attr($url),
            Html::text($text),
        );
    }

    /**
     * Renders a trysac/figure block enforcing alt text unless decorative is true.
     *
     * @param array<string, string|bool|int> $attrs
     */
    private function renderFigure(array $attrs): string
    {
        $url = Html::validateUrl(isset($attrs['url']) ? (string) $attrs['url'] : '');
        $alt = isset($attrs['alt']) ? (string) $attrs['alt'] : '';
        $caption = isset($attrs['caption']) ? (string) $attrs['caption'] : '';
        $decorative = isset($attrs['decorative']) && (bool) $attrs['decorative'];

        if (!$decorative && '' === trim($alt)) {
            throw new BlockException('missing_alt', 'Non-decorative figure requires a non-empty alt attribute.');
        }

        $effectiveAlt = $decorative ? '' : $alt;
        $captionHtml = '' !== trim($caption)
            ? sprintf('<figcaption>%s</figcaption>', Html::text($caption))
            : '';

        return sprintf(
            '<figure class="wp-block-trysac-figure"><img src="%s" alt="%s">%s</figure>',
            Html::attr($url),
            Html::attr($effectiveAlt),
            $captionHtml,
        );
    }

    /**
     * Renders a trysac/tabs container and its child trysac/tab inner blocks.
     */
    private function renderTabs(ParsedBlock $block): string
    {
        /** @var list<array{id: string, label: string, body: string}> $tabs */
        $tabs = [];
        foreach ($block->innerBlocks as $child) {
            if ('trysac/tab' !== $child->name) {
                throw new BlockException(
                    'invalid_attribute',
                    sprintf('Block "trysac/tabs" only accepts "trysac/tab" inner blocks, got "%s".', $child->name),
                );
            }
            $childAttrs = Registry::validateAttributes('trysac/tab', $child->attributes);
            $tabs[] = [
                'id' => $this->nextId('tab'),
                'label' => isset($childAttrs['label']) ? (string) $childAttrs['label'] : '',
                'body' => $this->renderInnerContent($child),
            ];
        }

        if ([] === $tabs) {
            throw new BlockException('invalid_attribute', 'Block "trysac/tabs" requires at least one "trysac/tab" inner block.');
        }

        $buttonsHtml = '';
        $panelsHtml = '';

        foreach ($tabs as $index => $tab) {
            $isSelected = 0 === $index;
            $tabButtonId = $tab['id'] . '-tab';
            $panelId = $tab['id'] . '-panel';
            $selectedAttr = $isSelected ? 'true' : 'false';
            $tabIndexAttr = $isSelected ? '0' : '-1';
            $hiddenAttr = $isSelected ? '' : ' hidden';

            $buttonsHtml .= sprintf(
                '<button type="button" role="tab" id="%s" aria-selected="%s" aria-controls="%s" tabindex="%s">%s</button>',
                Html::attr($tabButtonId),
                $selectedAttr,
                Html::attr($panelId),
                $tabIndexAttr,
                Html::text($tab['label']),
            );

            $panelsHtml .= sprintf(
                '<div role="tabpanel" id="%s" aria-labelledby="%s"%s>%s</div>',
                Html::attr($panelId),
                Html::attr($tabButtonId),
                $hiddenAttr,
                $tab['body'],
            );
        }

        return sprintf(
            '<div class="wp-block-trysac-tabs"><div role="tablist">%s</div>%s</div>',
            $buttonsHtml,
            $panelsHtml,
        );
    }

    /**
     * Renders a standalone trysac/tab block if called directly.
     *
     * @param array<string, string|bool|int> $attrs
     */
    private function renderStandaloneTab(ParsedBlock $block, array $attrs): string
    {
        $tabId = $this->nextId('tab');
        $label = isset($attrs['label']) ? (string) $attrs['label'] : '';
        $body = $this->renderInnerContent($block);

        return sprintf(
            '<div role="tabpanel" id="%s-panel" aria-label="%s">%s</div>',
            Html::attr($tabId),
            Html::attr($label),
            $body,
        );
    }

    /**
     * Renders interleaved innerContent (HTML chunks and nested innerBlocks).
     */
    private function renderInnerContent(ParsedBlock $block): string
    {
        if ([] === $block->innerBlocks) {
            return $block->innerHtml;
        }

        $out = '';
        $childIndex = 0;
        foreach ($block->innerContent as $chunk) {
            if (null === $chunk) {
                if (isset($block->innerBlocks[$childIndex])) {
                    $out .= $this->render($block->innerBlocks[$childIndex]);
                    ++$childIndex;
                }
            } else {
                $out .= $chunk;
            }
        }

        return $out;
    }

    /**
     * Generates the next sequential ID for a given block slug on this Renderer instance.
     */
    private function nextId(string $slug): string
    {
        $next = ($this->counters[$slug] ?? 0) + 1;
        $this->counters[$slug] = $next;

        return sprintf('trysac-%s-%d', $slug, $next);
    }
}
