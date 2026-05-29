<?php

namespace Sunnysideup\ArrayToUl\View;

use DateTimeInterface;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBCurrency;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBDecimal;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBFloat;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBHTMLVarchar;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBPercentage;
use SilverStripe\ORM\FieldType\DBTime;
use SilverStripe\View\ArrayData;
use SilverStripe\View\ViewableData;

/**
 * Renders a PHP array (associative, indexed, or nested) as an expandable
 * HTML list. Detects PHP scalar types AND SilverStripe DBField objects and
 * tags each value with a CSS class so you can style it yourself.
 *
 * This class emits NO styling of its own. Every element carries a class
 * (see the reference below) for you to target in your own stylesheet.
 *
 * Two independent collapse mechanisms, both driven by the HTML `hidden`
 * attribute (hidden by the browser's own user-agent stylesheet, so no author
 * CSS is required) and small inline `onclick` handlers (which keep working
 * when the markup is injected via AJAX / innerHTML):
 *
 *   1. ROW OVERFLOW — a list longer than `collapseAfter` (default 25) shows
 *      the first N rows and a "Show X more" button (.eal-toggle).
 *   2. NESTING — nested lists collapse behind a disclosure button
 *      (.eal-disclosure) once they reach `collapseFromDepth` (default 2), so
 *      the top level and the first nested level start open and deeper levels
 *      start collapsed. Set collapseFromDepth to 1 to collapse all nesting, or
 *      higher to open more levels by default.
 *
 * Class reference (all you need to style by hand):
 *   .eal                  root wrapper (root instance only)
 *   .eal-section          wrapper around a single list + its controls
 *   .eal-section.eal-nested    a nested (collapsible) list section
 *   .eal-disclosure       button that expands a nested list
 *   .eal-disclosure[aria-expanded="true"]   ← style the open state from this
 *   .eal-disclosure-icon  decorative chevron inside the disclosure button
 *   .eal-disclosure-label the summary text (e.g. "{…} 5 keys")
 *   .eal-list             the <dl> (assoc) or <ul> (sequential)
 *   .eal-list--map        modifier on the <dl> for associative lists
 *   .eal-list--seq        modifier on the <ul> for sequential lists
 *   .eal-row              one item; carries .eal-type-{type} too (a <div>
 *                         grouping dt/dd in a dl, or an <li> in a ul)
 *   .eal-collapsible      rows beyond the overflow threshold
 *   .eal-key              the key (<dt>, associative lists only)
 *   .eal-value            the value wrapper (<dd>, or a <div> inside the <li>)
 *   .eal-type-{type}      on .eal-row; num|bool|date|html|string|null|obj|array|other
 *   .eal-num .eal-bool .eal-bool-true .eal-bool-false .eal-null .eal-date .eal-obj
 *   .eal-html             the <pre> wrapping raw HTML source
 *   .eal-trunc            a click-to-expand truncated string
 *   .eal-trunc-ellipsis   the trailing ellipsis on a truncated string
 *   .eal-empty            an empty-string value ("")
 *   .eal-empty-list       shown in place of an empty list
 *   .eal-toggle           the "Show more / less" overflow button
 *   .eal-toggle[aria-expanded="true"]   ← style the open state from this
 *   .eal-toggle-icon      decorative chevron inside the toggle button
 *   .eal-toggle-label     the text span inside the toggle button
 *
 * Template:  templates/Sunnysideup/ArrayToUl/View/ExpandableArrayList.ss
 */
class ExpandableArrayList extends ViewableData
{
    private static $casting = [
        'EmptyLabel'    => 'Varchar',
        'SummaryLabel'  => 'Varchar',
        'HiddenCount'   => 'Int',
    ];

    private array $data;
    private int $collapseAfter;
    private bool $startExpanded;
    private string $emptyLabel;
    private int $textTruncateAt;
    private int $collapseFromDepth;
    private int $depth;
    private bool $allowHtmlAsIs = false;

    public function __construct(
        array $data = [],
        int $collapseAfter = 25,
        bool $startExpanded = false,
        string $emptyLabel = '(empty)',
        int $textTruncateAt = 200,
        int $collapseFromDepth = 2,
        int $depth = 0
    ) {
        parent::__construct();
        $this->data              = $data;
        $this->collapseAfter     = max(0, $collapseAfter);
        $this->startExpanded     = $startExpanded;
        $this->emptyLabel        = $emptyLabel;
        $this->textTruncateAt    = max(0, $textTruncateAt);
        $this->collapseFromDepth = max(0, $collapseFromDepth);
        $this->depth             = max(0, $depth);
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function setCollapseAfter(int $n): self
    {
        $this->collapseAfter = max(0, $n);
        return $this;
    }

    /**
     * Nesting depth at which lists start collapsed. 0 collapses the root too,
     * 1 collapses every nested list, 2 (default) keeps the top level and the
     * first nested level open and collapses from the second level down.
     */
    public function setCollapseFromDepth(int $n): self
    {
        $this->collapseFromDepth = max(0, $n);
        return $this;
    }

    public function setStartExpanded(bool $value): self
    {
        $this->startExpanded = $value;
        return $this;
    }

    public function setEmptyLabel(string $label): self
    {
        $this->emptyLabel = $label;
        return $this;
    }

    /**
     * Maximum string length before a value gets truncated with a
     * click-to-expand toggle. Pass 0 to disable truncation entirely.
     */
    public function setTextTruncateAt(int $n): self
    {
        $this->textTruncateAt = max(0, $n);
        return $this;
    }

    public function setAllowHtmlAsIs(bool $allow): self
    {
        $this->allowHtmlAsIs = $allow;
        return $this;
    }

    public function forTemplate()
    {
        return $this->renderWith(self::class);
    }

    public function __toString(): string
    {
        return (string)$this->forTemplate();
    }

    // ---------------------------------------------------------------------
    // Template accessors
    // ---------------------------------------------------------------------

    public function getIsRoot(): bool
    {
        return $this->depth === 0;
    }

    public function getIsNested(): bool
    {
        return $this->depth > 0;
    }

    /**
     * Should this list begin collapsed? True once we reach `collapseFromDepth`,
     * unless the caller asked for everything to start open.
     */
    public function getStartCollapsed(): bool
    {
        return $this->depth >= $this->collapseFromDepth && !$this->startExpanded;
    }

    public function getStartExpanded(): bool
    {
        return $this->startExpanded;
    }

    public function getIsEmpty(): bool
    {
        return $this->data === [];
    }

    public function getEmptyLabel(): string
    {
        return $this->emptyLabel;
    }

    public function getIsAssoc(): bool
    {
        return $this->isAssocInner($this->data);
    }

    public function getNeedsCollapse(): bool
    {
        return $this->collapseAfter > 0 && count($this->data) > $this->collapseAfter;
    }

    public function getHiddenCount(): int
    {
        return max(0, count($this->data) - $this->collapseAfter);
    }

    /**
     * Short summary shown on the disclosure button for a collapsed nested list.
     */
    public function getSummaryLabel(): string
    {
        $n = count($this->data);
        if ($this->getIsAssoc()) {
            return '{…} ' . $n . ' ' . ($n === 1 ? 'key' : 'keys');
        }
        return '[…] ' . $n . ' ' . ($n === 1 ? 'item' : 'items');
    }

    public function getItems(): ArrayList
    {
        $items         = ArrayList::create();
        $needsCollapse = $this->getNeedsCollapse();
        $i             = 0;

        foreach ($this->data as $key => $value) {
            $collapsible = $needsCollapse && $i >= $this->collapseAfter;
            $hidden      = $collapsible && !$this->startExpanded;

            $items->push(ArrayData::create([
                'Key'           => (string)$key,
                'Value'         => $this->renderValue($value),
                'IsCollapsible' => $collapsible,
                'IsHidden'      => $hidden,
                'TypeClass'     => $this->typeClass($value),
            ]));
            $i++;
        }

        return $items;
    }

    // ---------------------------------------------------------------------
    // Value rendering
    // ---------------------------------------------------------------------

    /**
     * Determine the intrinsic logical type of the value to route it correctly.
     */
    private function determineType($value): string
    {
        if (is_array($value)) {
            return 'array';
        }
        if ($value instanceof DBField) {
            if ($value instanceof DBBoolean) {
                return 'bool';
            }
            if ($value instanceof DBInt
                || $value instanceof DBFloat
                || $value instanceof DBDecimal
                || $value instanceof DBCurrency
                || $value instanceof DBPercentage) {
                return 'num';
            }
            if ($value instanceof DBDate
                || $value instanceof DBDatetime
                || $value instanceof DBTime) {
                return 'date';
            }
            if ($value instanceof DBHTMLText
                || $value instanceof DBHTMLVarchar) {
                return 'html';
            }
            return 'string';
        }
        if ($value instanceof DateTimeInterface) {
            return 'date';
        }
        if (is_bool($value)) {
            return 'bool';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_int($value) || is_float($value)) {
            return 'num';
        }
        if (is_string($value)) {
            return $this->looksLikeHtml($value) ? 'html' : 'string';
        }
        if (is_object($value)) {
            return 'obj';
        }

        return 'other';
    }

    /**
     * CSS class describing the value, e.g. "eal-type-num". Goes on .eal-row.
     */
    private function typeClass($value): string
    {
        return 'eal-type-' . $this->determineType($value);
    }

    /**
     * Format a single value for the template.
     */
    private function renderValue($value): DBField
    {
        $type = $this->determineType($value);

        if ($type === 'array') {
            $nested = ExpandableArrayList::create(
                $value,
                $this->collapseAfter,
                $this->startExpanded,
                $this->emptyLabel,
                $this->textTruncateAt,
                $this->collapseFromDepth,
                $this->depth + 1 // one level deeper
            );
            return DBField::create_field('HTMLFragment', (string)$nested->forTemplate());
        }

        if ($value instanceof DBField) {
            return $this->renderDBField($value);
        }

        if ($type === 'date') {
            return $this->wrapHtml('eal-date', $value->format('Y-m-d H:i:s'));
        }

        if ($type === 'bool') {
            $label = $value ? 'TRUE' : 'FALSE';
            $class = $value ? 'eal-bool eal-bool-true' : 'eal-bool eal-bool-false';
            return $this->wrapHtml($class, $label);
        }

        if ($type === 'null') {
            return $this->wrapHtml('eal-null', 'NULL');
        }

        if ($type === 'num') {
            return $this->wrapHtml('eal-num', (string)$value);
        }

        if ($type === 'obj') {
            $str = method_exists($value, '__toString') ? (string)$value : get_class($value);
            return $this->wrapHtml('eal-obj', $str);
        }

        if ($type === 'html') {
            return $this->renderHtmlSource((string)$value);
        }

        // Fallback for 'string' and 'other' types
        $strValue = (string)$value;
        if ($strValue === '') {
            return DBField::create_field('HTMLFragment', '<span class="eal-empty">""</span>');
        }
        if ($this->textTruncateAt > 0 && mb_strlen($strValue) > $this->textTruncateAt) {
            return $this->renderTruncatedText($strValue);
        }

        // Let the template auto-escape plain strings.
        return DBField::create_field('Varchar', $strValue);
    }

    /**
     * Dispatch a SilverStripe DBField to the right native-type renderer.
     */
    private function renderDBField(DBField $field): DBField
    {
        $raw  = $field->getValue();
        $type = $this->determineType($field);

        if ($type === 'bool') {
            return $this->renderValue((bool)$raw);
        }

        if ($type === 'num') {
            if ($raw === null) {
                return $this->renderValue(null);
            }
            return $this->renderValue($field instanceof DBInt ? (int)$raw : (float)$raw);
        }

        if ($type === 'date') {
            if ($raw === null || $raw === '') {
                return $this->renderValue(null);
            }
            return $this->wrapHtml('eal-date', (string)$raw);
        }

        if ($type === 'html') {
            return $raw === null || $raw === ''
                ? $this->renderValue(null)
                : $this->renderHtmlSource((string)$raw);
        }

        // Generic DBField — treat as string.
        return $raw === null ? $this->renderValue(null) : $this->renderValue((string)$raw);
    }

    private function looksLikeHtml(string $str): bool
    {
        // Cheap heuristic: contains at least one tag-shaped token.
        return (bool)preg_match('/<[a-z][a-z0-9]*(\s[^<>]*)?>/i', $str);
    }

    private function renderHtmlSource(string $html): DBField
    {
        if ($this->allowHtmlAsIs) {
            return DBField::create_field('HTMLFragment', $html);
        }
        return DBField::create_field(
            'HTMLFragment',
            '<pre class="eal-html"><code>'
            . htmlspecialchars($html, ENT_QUOTES, 'UTF-8')
            . '</code></pre>'
        );
    }

    /**
     * Render a long string truncated with a click-to-expand affordance.
     * The full text is stashed in a data attribute and swapped in via the
     * inline handler (textContent assignment auto-escapes, so XSS-safe).
     */
    private function renderTruncatedText(string $text): DBField
    {
        $truncated = mb_substr($text, 0, $this->textTruncateAt);
        $fullEsc   = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $shortEsc  = htmlspecialchars($truncated, ENT_QUOTES, 'UTF-8');
        $js        = "this.textContent=this.dataset.full;"
                   . "this.classList.remove('eal-trunc');"
                   . "this.removeAttribute('title');"
                   . "this.removeAttribute('onclick');";

        return DBField::create_field(
            'HTMLFragment',
            '<span class="eal-trunc" title="Click to expand"'
            . ' data-full="' . $fullEsc . '"'
            . ' onclick="' . htmlspecialchars($js, ENT_QUOTES, 'UTF-8') . '">'
            . $shortEsc
            . '<span class="eal-trunc-ellipsis">…</span>'
            . '</span>'
        );
    }

    private function wrapHtml(string $cssClass, string $text): DBField
    {
        return DBField::create_field(
            'HTMLFragment',
            '<span class="' . $cssClass . '">'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            . '</span>'
        );
    }

    private function isAssocInner(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
