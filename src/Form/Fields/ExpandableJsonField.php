<?php

namespace Sunnysideup\ArrayToUl\Form\Fields;

use SilverStripe\Forms\LiteralField;
use Sunnysideup\ArrayToUl\View\ExpandableArrayList;

/**
 * Form field that displays an array as an expandable HTML list.
 *
 * Wraps an {@see ExpandableArrayList} so it can be dropped into any
 * FieldList — getCMSFields(), a regular Form, or a readonly summary
 * screen — and rendered like any other field.
 *
 * Example:
 *
 *     $fields->addFieldToTab(
 *         'Root.Debug',
 *         ExpandableJsonField::create('RawData', 'Header', $this->getRawDataAsJson())
 *             ->setCollapseAfter(10)
 *             ->setTitle('Raw payload')
 *     );
 */
class ExpandableJsonField extends ExpandableArrayListField
{
    public function __construct(
        string $name,
        string $title = null,
        string $value = '{}',
        int $collapseAfter = 25,
        bool $startExpanded = false,
        string $emptyLabel = '(empty)'
    ) {
        parent::__construct(
            $name,
            $title,
            json_decode($value, true) ?? [],
            $collapseAfter,
            $startExpanded,
            $emptyLabel
        );
    }
}
