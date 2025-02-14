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


/**
 * Gauge widget view.
 *
 * @var CView $this
 * @var array $data
 */

$view = new CWidgetView($data);

if (array_key_exists('error', $data)) {
    $view->addItem((new CTableInfo())->setNoDataMessage($data['error']));
}
else {
    foreach ($data['vars'] as $name => $value) {
        $view->setVar($name, $value);
    }
}

$view->show();
