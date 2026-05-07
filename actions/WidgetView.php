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


namespace Modules\GaugeMAX\Actions;

use API,
    CArrayHelper,
    CMacrosResolverHelper,
    CNumberParser,
    CControllerDashboardWidgetView,
    CControllerResponseData,
    CSettingsHelper,
    CUrl,
    Manager;

use Modules\GaugeMAX\Widget;
use Modules\GaugeMAX\Includes\CSvgGraphHelper;

class WidgetView extends CControllerDashboardWidgetView {

    protected function init(): void {
        parent::init();

        $this->addValidationRules([
            'with_config' => 'in 1',
            'contents_width' => 'int32|ge 1|le 65535',
            'contents_height' => 'int32|ge 1|le 65535',
        ]);
    }

    protected function doAction(): void {
        $data = [
            'name' => $this->getInput('name', $this->widget->getDefaultName()),
            'user' => [
                'debug_mode' => $this->getDebugMode()
            ]
        ];

        $item = $this->getItem();

        if ($item === null) {
            $this->setResponse(new CControllerResponseData($data + [
                'error' => _('No permissions to referred object or it does not exist!')
            ]));

            return;
        }

        if ($this->fields_values['override_hostid']) {
            $errors = $this->checkConfigForOverriddenItem($item);

            if ($errors) {
                foreach ($errors as $error) {
                    error($error);
                }

                $this->setResponse(
                    (new CControllerResponseData(['main_block' => json_encode([
                        'error' => [
                            'messages' => array_column(get_and_clear_messages(), 'message')
                        ]
                    ], JSON_THROW_ON_ERROR)]))->disableView()
                );

                return;
            }
        }

        if ($this->getInput('name', '') === '') {
            $data['name'] = $this->isTemplateDashboard()
                ? $item['name']
                : $item['hosts'][0]['name'].NAME_DELIMITER.$item['name'];
        }

        $data['vars'] = $this->getValueData($item);

        $data['vars']['url'] = $this->getHistoryUrl($item);

        if ($this->hasInput('with_config')) {
            $data['vars']['config'] = $this->getConfig($item);
        }

        $data['vars']['graphmax'] = (string) $this->getGraphMax($item, $this->getInput('contents_width', 1),
            $this->getInput('contents_height', 1)
        );

        $this->setResponse(new CControllerResponseData($data));
    }

    private function getGraphMax(array $item, $width, $height) {
        $options = [
            'displaying' => [
                'show_simple_triggers' => false,
                'show_working_time' => false,
                'show_percentile_left' => false,
                'percentile_left_value' => 0,
                'show_percentile_right' => false,
                'percentile_right_value' => 0
            ],
            'time_period' => [
                'time_from' => time()-(60*5),
                'time_to' => time()
            ],
            'axes' => [
                'show_left_y_axis' => false,
                'left_y_min' => null,
                'left_y_max' => null,
                'left_y_units' => null,
                'show_right_y_axis' => false,
                'right_y_min' => null,
                'right_y_max' => null,
                'right_y_units' => null,
                'show_x_axis' => false
            ],
            'data_source' => 0,
            'legend' => [
                'show_legend' => false,
                'show_aggregation' => false
            ],
            'problems' => [
                'show_problems' => false
            ],
            'overrides' => [],
            'fix_time_period' => true,
            'templateid' => '',
            'override_hostid' => $this->fields_values['override_hostid'] ? reset($this->fields_values['override_hostid']) : '',
            'data_sets' => []
        ];

        if ($this->fields_values['graphtimeperiod']) {
            $options['time_period']['time_from'] = $this->fields_values['graphtimeperiod']['from_ts'];
            $options['time_period']['time_to'] = $this->fields_values['graphtimeperiod']['to_ts'];
        }

        if (is_numeric($this->fields_values['graph']['ymin'])) {
            $options['axes']['left_y_min'] = $this->fields_values['graph']['ymin'];
        }

        if (is_numeric($this->fields_values['graph']['ymax'])) {
            $options['axes']['left_y_max'] = $this->fields_values['graph']['ymax'];
        }

        $options['data_sets'][] = $item + [
            'source' => SVG_GRAPH_DATA_SOURCE_AUTO,
            'color' => [$this->fields_values['graph']['color']??'FF465C'],
            'fill' => $this->fields_values['graph']['fill'],
            'width' => $this->fields_values['graph']['width'],
            'type' => $this->fields_values['graph']['type'],
            'stacked' => 0,
            'pointsize' => 3,
            'transparency' => $this->fields_values['graph']['transparency']??5,
            'axisy' => 0,
            'timeshift' => 0,
            'missingdatafunc' => $this->fields_values['graph']['missingdatafunc'],
            'aggregate_function' => $this->fields_values['graph']['aggregate_function'],
            'aggregate_interval' => $this->fields_values['graph']['aggregate_interval'],
            'aggregate_grouping' => 0,
            'approximation' => 2,
            'hosts' => [],
            'items' => [],
            'itemids' => [$item['itemid']],
            'time_period' => $options['time_period'],
            'dataset_type' => 0,
            'data_set' => 0 // CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM
        ];

        $data = CSvgGraphHelper::get($options, $width, $height);

        return $data['svg'];
    }

    private function getItem(): ?array {
        $resolve_macros = !$this->isTemplateDashboard() || $this->fields_values['override_hostid'];

        $item_options = [
            'output' => ['itemid', 'hostid', $resolve_macros ? 'name_resolved' : 'name', 'value_type', 'units'],
            'selectHosts' => !$this->isTemplateDashboard() ? ['name'] : null,
            'selectValueMap' => ['mappings'],
            'filter' => [
                'value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]
            ],
            'webitems' => true
        ];

        if ($this->fields_values['override_hostid']) {
            $src_items = API::Item()->get([
                'output' => ['key_'],
                'itemids' => $this->fields_values['itemid'],
                'webitems' => true
            ]);

            if (!$src_items) {
                return null;
            }

            $item_options['hostids'] = $this->fields_values['override_hostid'];
            $item_options['filter']['key_'] = $src_items[0]['key_'];
        }
        else {
            $item_options['itemids'] = $this->fields_values['itemid'];
        }

        $items = API::Item()->get($item_options);

        if (!$items) {
            return null;
        }

        return $resolve_macros ? CArrayHelper::renameKeys($items[0], ['name_resolved' => 'name']) : $items[0];
    }

    private function checkConfigForOverriddenItem(array $item): array {
        $form = $this->widget->getForm(['itemid' => $item['itemid']] + $this->getInput('fields', []),
            $this->hasInput('templateid') ? $this->getInput('templateid') : null
        );

        return $form->validate();
    }

    private function getConfig(array $item): array {
        $config = [
            'angle' => $this->fields_values['angle'],
            'empty_color' => $this->fields_values['empty_color'],
            'bg_color' => $this->fields_values['bg_color']
        ];

        $item_units = $this->fields_values['units_show'] == 1 && $this->fields_values['units'] !== ''
            ? $this->fields_values['units']
            : $item['units'];

        $number_parser = new CNumberParser([
            'with_size_suffix' => true,
            'with_time_suffix' => true,
            'is_binary_size' => isBinaryUnits($item_units)
        ]);

        $number_parser->parse($this->fields_values['min']);
        $config['min'] = $number_parser->calcValue();

        $number_parser->parse($this->fields_values['max']);
        $config['max'] = $number_parser->calcValue();

        $show = array_flip($this->fields_values['show']);

        if (array_key_exists(Widget::SHOW_SCALE, $show)) {
            $config['scale'] = [
                'show' => true,
                'size' => $this->fields_values['scale_size']
            ];

            if ($this->fields_values['units_show'] == 1 && $this->fields_values['scale_show_units'] == 1) {
                $scale_units = $this->fields_values['units'] !== '' ? $this->fields_values['units'] : $item['units'];
            }
            else {
                $scale_units = '';
            }

            $scale_decimal_places = $this->fields_values['scale_decimal_places'];

            $labels = self::makeValueLabels(['units' => $scale_units] + $item, $config['min'], $scale_decimal_places);
            $config['scale']['min_text'] = $labels['value'].($labels['units'] !== '' ? ' '.$labels['units'] : '');

            $labels = self::makeValueLabels(['units' => $scale_units] + $item, $config['max'], $scale_decimal_places);
            $config['scale']['max_text'] = $labels['value'].($labels['units'] !== '' ? ' '.$labels['units'] : '');
        }
        else {
            $config['scale']['show'] = false;
        }

        if (array_key_exists(Widget::SHOW_DESCRIPTION, $show)) {
            $widget_description = $this->fields_values['description'];

            if (!$this->isTemplateDashboard() || $this->fields_values['override_hostid']) {
                $items = CMacrosResolverHelper::resolveItemBasedWidgetMacros(
                    [$item['itemid'] => $item + ['widget_description' => $widget_description]],
                    ['widget_description' => 'widget_description']
                );
                $widget_description = $items[$item['itemid']]['widget_description'];
            }

            $config['description'] = [
                'show' => true,
                'text' => $widget_description,
                'position' => $this->fields_values['desc_v_pos'],
                'size' => $this->fields_values['desc_size'],
                'is_bold' => $this->fields_values['desc_bold'] == 1,
                'color' => $this->fields_values['desc_color']
            ];
        }
        else {
            $config['description']['show'] = false;
        }

        if (array_key_exists(Widget::SHOW_VALUE, $show)) {
            $config['value'] = [
                'show' => true,
                'size' => $this->fields_values['value_size'],
                'is_bold' => $this->fields_values['value_bold'] == 1,
                'color' => $this->fields_values['value_color']
            ];

            $config['units'] = $this->fields_values['units_show'] == 1
                ? [
                    'show' => true,
                    'position' => $this->fields_values['units_pos'],
                    'size' => $this->fields_values['units_size'],
                    'is_bold' => $this->fields_values['units_bold'] == 1,
                    'color' => $this->fields_values['units_color']
                ]
                : [
                    'show' => false
                ];
        }
        else {
            $config['value']['show'] = false;
            $config['units']['show'] = false;
        }

        $config['value_arc'] = array_key_exists(Widget::SHOW_VALUE_ARC, $show)
            ? [
                'show' => true,
                'size' => $this->fields_values['value_arc_size'],
                'color' => $this->fields_values['value_arc_color']
            ]
            : [
                'show' => false
            ];

        $config['needle'] = array_key_exists(Widget::SHOW_NEEDLE, $show)
            ? [
                'show' => true,
                'color' => $this->fields_values['needle_color']
            ]
            : [
                'show' => false
            ];

        $config['thresholds'] = [
            'show_labels' => $this->fields_values['th_show_labels'] == 1,
            'arc' => $this->fields_values['th_show_arc'] == 1
                ? [
                    'show' => true,
                    'size' => $this->fields_values['th_arc_size']
                ]
                : [
                    'show' => false
                ],
            'data' => []
        ];

        foreach ($this->fields_values['thresholds'] as $threshold) {
            $number_parser->parse($threshold['threshold']);

            $threshold_value = $number_parser->calcValue();

            if (array_key_exists(Widget::SHOW_SCALE, $show)) {
                $labels = self::makeValueLabels(['units' => $scale_units] + $item, $threshold_value,
                    $scale_decimal_places
                );

                $threshold_text = $labels['value'].($labels['units'] !== '' ? ' '.$labels['units'] : '');
            }
            else {
                $threshold_text = '';
            }

            $config['thresholds']['data'][] = [
                'color' => $threshold['color'],
                'value' => $threshold_value,
                'text' => $threshold_text
            ];
        }

        return $config;
    }

    private function getValueData(array $item): array {
        $no_data = [
            'value' => null
        ];

        if ($this->isTemplateDashboard() && !$this->fields_values['override_hostid']) {
            return $no_data;
        }

        $history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
        $history = Manager::History()->getLastValues([$item], 1, $history_period);

        if (!$history) {
            return $no_data;
        }

        $value = $history[$item['itemid']][0]['value'];

        if (!in_array(Widget::SHOW_VALUE, $this->fields_values['show'])) {
            return [
                'value' => (float) $value
            ];
        }

        if ($this->fields_values['units_show'] == 1) {
            if ($this->fields_values['units'] !== '') {
                $item['units'] = $this->fields_values['units'];
            }
        }
        else {
            $item['units'] = '';
        }

        $labels = self::makeValueLabels($item, $value, $this->fields_values['decimal_places']);

        return [
            'value' => (float) $value,
            'value_text' => $labels['value'],
            'units_text' => $labels['units']
        ];
    }

    private function getHistoryUrl(array $item): string {
        return (new CUrl('history.php'))
            ->setArgument('action', HISTORY_GRAPH)
            ->setArgument('itemids[]', $item['itemid'])
            ->getUrl();
    }

    private static function makeValueLabels(array $item, $value, int $decimal_places): array {
        return formatHistoryValueRaw($value, $item, false, [
            'decimals' => $decimal_places,
            'decimals_exact' => true,
            'small_scientific' => false,
            'zero_as_zero' => false
        ]);
    }
}
