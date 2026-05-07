<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 initMAX s.r.o.
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


namespace Modules\GaugeMAX\Includes;

use CDiv;
use CFormGrid;
use CFormField;
use CLabel;
use CColor;
use CNumericBox;
use CSelect;
use CTextBox;
use CRangeControl;
use CWidgetFieldView;
use CRadioButtonList;
use CScriptTag;
use CItemHelper;
use CWidgetFieldTimePeriodView;

/** @property WidgetFieldGraphMAX field */
class WidgetFieldGraphMAXView extends CWidgetFieldView {

    public CWidgetFieldTimePeriodView $time_period;

    public function __construct(WidgetFieldGraphMAX $field) {
        $this->field = $field;
        $this->time_period = (new CWidgetFieldTimePeriodView($this->field->getTimePeriodField()))
            ->setDateFormat(ZBX_FULL_DATE_TIME)
            ->setFromPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
            ->setToPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
        ;
    }

    public function getClass(): ?string {
        return CFormGrid::ZBX_STYLE_FIELDS_GROUP;
    }

    public function getView(): CDiv {
        $name = $this->field->getName();
        $value = $this->field->getValue();

        $draw = (new CRadioButtonList($name.'[type]', (int) $value['type']))
            ->addValue(_('Line'), SVG_GRAPH_TYPE_LINE)
            ->addValue(_('Staircase'), SVG_GRAPH_TYPE_STAIRCASE)
            ->addValue(_('Bar'), SVG_GRAPH_TYPE_BAR)
            ->setEnabled(false)
            ->setModern()
        ;

        $width = (new CRangeControl($name.'[width]', (int) $value['width']))
            ->setEnabled(!in_array($value['type'], [SVG_GRAPH_TYPE_BAR]))
            ->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
            ->setStep(1)
            ->setMin(0)
            ->setMax(10)
            ->setEnabled(false)
        ;

        $transparency = (new CRangeControl($name.'[transparency]', (int) $value['transparency']))
            ->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
            ->setStep(1)
            ->setMin(0)
            ->setMax(10)
            ->setEnabled(false)
        ;

        $fill = (new CRangeControl($name.'[fill]', (int) $value['fill']))
            ->setEnabled(!in_array($value['type'], [SVG_GRAPH_TYPE_BAR]))
            ->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
            ->setStep(1)
            ->setMin(0)
            ->setMax(10)
            ->setEnabled(false)
        ;

        $missing_data = (new CRadioButtonList($name.'[missingdatafunc]', (int) $value['missingdatafunc']))
            ->addValue(_('None'), SVG_GRAPH_MISSING_DATA_NONE)
            ->addValue(_x('Connected', 'missing data function'), SVG_GRAPH_MISSING_DATA_CONNECTED)
            ->addValue(_x('Treat as 0', 'missing data function'), SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERO)
            ->addValue(_x('Last known', 'missing data function'), SVG_GRAPH_MISSING_DATA_LAST_KNOWN)
            ->setEnabled(!in_array($value['type'], [SVG_GRAPH_TYPE_BAR]))
            ->setEnabled(false)
            ->setModern()
        ;

        $value['color'] = 'D2D2D2';

        $color = (new CColor($name.'[color]', $value['color']))
            ->appendColorPickerJs(false)
            ->enableUseDefault(false)
            //->setEnabled(false)
        ;
    
        $aggr_function = (new CSelect($name.'[aggregate_function]'))
            ->setId($name.'_aggregate_function')
            ->setFocusableElementId('label-'.$name.'_aggregate_function')
            ->setValue((int) $value['aggregate_function'])
            ->addOptions(CSelect::createOptionsFromArray([
                AGGREGATE_NONE => CItemHelper::getAggregateFunctionName(AGGREGATE_NONE),
                AGGREGATE_MIN => CItemHelper::getAggregateFunctionName(AGGREGATE_MIN),
                AGGREGATE_MAX => CItemHelper::getAggregateFunctionName(AGGREGATE_MAX),
                AGGREGATE_AVG => CItemHelper::getAggregateFunctionName(AGGREGATE_AVG),
                AGGREGATE_COUNT => CItemHelper::getAggregateFunctionName(AGGREGATE_COUNT),
                AGGREGATE_SUM => CItemHelper::getAggregateFunctionName(AGGREGATE_SUM),
                AGGREGATE_FIRST => CItemHelper::getAggregateFunctionName(AGGREGATE_FIRST),
                AGGREGATE_LAST => CItemHelper::getAggregateFunctionName(AGGREGATE_LAST)
            ]))
            ->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
            ->setReadonly(true)
        ;

        $aggr_interval = (new CTextBox($name.'[aggregate_interval]', $value['aggregate_interval']))
            ->setEnabled($value['aggregate_function'] != AGGREGATE_NONE)
            ->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
            ->setAttribute('placeholder', GRAPH_AGGREGATE_DEFAULT_INTERVAL)
            ->setEnabled(false)
        ;

        $ymin = (new CNumericBox($name.'[ymin]', $value['ymin'], 15, false, true))
            ->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
            ->setAttribute('placeholder', _('calculated'))
            ->setEnabled(false)
        ;

        $ymax = (new CNumericBox($name.'[ymax]', $value['ymax'], 15, false, true))
            ->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
            ->setAttribute('placeholder', _('calculated'))
            ->setEnabled(false)
        ;

        return (new CDiv([
            new CFormGrid([
                new CLabel(_('Draw'), $draw->getId()), new CFormField($draw),
                new CLabel(_('Width'), $width->getId()), new CFormField($width),
                new CLabel(_('Transparency'), $transparency->getId()), new CFormField($transparency),
                new CLabel(_('Fill'), $fill->getId()), new CFormField($fill),
                new CLabel(_('Missing data'), $missing_data->getId()), new CFormField($missing_data),
                array_map(
                    static fn ($el) => [$el['label'], (new CFormField($el['view']))->addClass("time-period {$el['class']}")],
                    $this->time_period->getViewCollection()
                ),
            ]),
            new CFormGrid([
                new CLabel(_('Color'), $name.'[color]'), new CFormField($color),
                new CLabel(_('Aggregation function'), $aggr_function->getFocusableElementId()), new CFormField($aggr_function),
                new CLabel(_('Aggregation interval'), $aggr_interval->getId()), new CFormField($aggr_interval),
                new CLabel(_('Min Y-axis'), $ymin->getId()), new CFormField($ymin),
                new CLabel(_('Max Y-axis'), $ymax->getId()), new CFormField($ymax)
            ]),
            new CScriptTag([
                $width->getPostJS(),
                $transparency->getPostJS(),
                $fill->getPostJS(),
<<<JAVASCRIPT
$('#{$draw->getId()}').on('change', e => {
    const is_type_bar = e.target.value == 3;

    $('#{$width->getId()},#{$fill->getId()}')
        .attr('disabled', is_type_bar)
        .rangeControl(is_type_bar ? 'disable' : 'enable');

    $('#{$missing_data->getId()} [type="radio"]').attr('disabled', is_type_bar);
});
$('#{$aggr_function->getId()}').on('change', e => $('#{$aggr_interval->getId()}').attr('disabled', e.target.value == 0));
JAVASCRIPT
            ])
        ]))->addClass('graphmax');
    }
}
