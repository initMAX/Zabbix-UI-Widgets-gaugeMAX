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

use CWidgetsData;
use Zabbix\Widgets\CWidgetField;
use Zabbix\Widgets\Fields\CWidgetFieldTimePeriod;

class WidgetFieldGraphMAX extends CWidgetField {

    public const DEFAULT_VIEW = WidgetFieldGraphMAXView::class;

    /** @var CWidgetFieldTimePeriod $timeperiod */
    protected $timeperiod = null;

    public function __construct(string $name, string $label = null) {
        parent::__construct($name, $label);

        $this
            ->setDefault(static::getDefaults())
            ->setValidationRules(['type' => API_OBJECT, 'fields' => [
                'color'                 => ['type' => API_COLOR, 'flags' => API_REQUIRED],
                'type'                  => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_STAIRCASE, SVG_GRAPH_TYPE_BAR])],
                'width'                 => ['type' => API_INT32, 'in' => implode(',', range(0, 10))],
                'transparency'          => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(0, 10))],
                'fill'                  => ['type' => API_INT32, 'in' => implode(',', range(0, 10))],
                'missingdatafunc'       => ['type' => API_INT32, 'in' => implode(',', [SVG_GRAPH_MISSING_DATA_NONE, SVG_GRAPH_MISSING_DATA_CONNECTED, SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERO, SVG_GRAPH_MISSING_DATA_LAST_KNOWN])],
                'aggregate_function'    => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [AGGREGATE_NONE, AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST])],
                'aggregate_interval'    => ['type' => API_MULTIPLE, 'rules' => [
                    ['if' => ['field' => 'aggregate_function', 'in' => implode(',', [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST])],
                        'type' => API_TIME_UNIT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_TIME_UNIT_WITH_YEAR, 'in' => implode(':', [1, ZBX_MAX_TIMESHIFT])],
                    ['else' => true, 'type' => API_STRING_UTF8, 'in' => GRAPH_AGGREGATE_DEFAULT_INTERVAL]
                ]],
                'ymin'                  => ['type' => API_NUMERIC],
                'ymax'                  => ['type' => API_NUMERIC]
            ]]);

        $this->timeperiod = (new CWidgetFieldTimePeriod($this->name.'timeperiod', _('Time period')))
            ->setDefaultPeriod(['from' => 'now-1h', 'to' => 'now'])
            ->setDefault([
                CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
                    CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
                )
            ])
            ->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
            ->acceptWidget(true)
            ->acceptDashboard(true);
    }

    public static function getDefaults(): array {
        return [
            'color' => '',
            'type' => SVG_GRAPH_TYPE_LINE,
            'width' => SVG_GRAPH_DEFAULT_WIDTH,
            'transparency' => SVG_GRAPH_DEFAULT_TRANSPARENCY,
            'fill' => SVG_GRAPH_DEFAULT_FILL,
            'missingdatafunc' => SVG_GRAPH_MISSING_DATA_NONE,
            'aggregate_function' => AGGREGATE_NONE,
            'aggregate_interval' => GRAPH_AGGREGATE_DEFAULT_INTERVAL,
            'ymin' => '',
            'ymax' => ''
        ];
    }

    public function getTimePeriodField(): CWidgetFieldTimePeriod {
        return $this->timeperiod;
    }

    public function setValue($value): self {
        parent::setValue((array) $value + static::getDefaults());

        return $this;
    }

    public function toApi(array &$widget_fields = []): void {
        $dataset_fields = [
            'color' => ZBX_WIDGET_FIELD_TYPE_STR,
            'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
            'width' => ZBX_WIDGET_FIELD_TYPE_INT32,
            'transparency' => ZBX_WIDGET_FIELD_TYPE_INT32,
            'fill' => ZBX_WIDGET_FIELD_TYPE_INT32,
            'missingdatafunc' => ZBX_WIDGET_FIELD_TYPE_INT32,
            'aggregate_function' => ZBX_WIDGET_FIELD_TYPE_INT32,
            'aggregate_interval' => ZBX_WIDGET_FIELD_TYPE_STR,
            'ymin' => ZBX_WIDGET_FIELD_TYPE_STR,
            'ymax' => ZBX_WIDGET_FIELD_TYPE_STR
        ];

        $prefix = $this->name.'.';
        $value = array_diff_assoc($this->getValue(), static::getDefaults());
        $dataset_fields = array_intersect_key($dataset_fields, $value);

        foreach ($dataset_fields as $name => $type) {
            $widget_fields[] = [
                'type' => $type,
                'name' => $prefix.$name,
                'value' => $value[$name]
            ];
        }
    }
}
