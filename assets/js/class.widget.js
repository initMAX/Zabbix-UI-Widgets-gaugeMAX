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


class CWidgetGaugeMAX extends CWidget {

    static ZBX_STYLE_DASHBOARD_WIDGET_PADDING_V = 8;
    static ZBX_STYLE_DASHBOARD_WIDGET_PADDING_H = 10;

    onInitialize() {
        this.gauge = null;
        this.gauge_link = document.createElement('a');
        this.graphmax = this.createGraphMaxElement();
    }

    createGraphMaxElement() {
        let graphmax = document.createElement('div');

        graphmax.style = `
            position: absolute;
            top: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        `;

        return graphmax;
    }

    #setGraphMaxSize({width, height}) {
        if (this.graphmax !== null) {
            this.graphmax.style.width = `${width}px`;
            this.graphmax.style.height = `${height}px`;
        }
    }

    onResize() {
        if (this._state === WIDGET_STATE_ACTIVE && this.gauge !== null) {
            this.gauge.setSize(super._getContentsSize());
        }

        if (this._state === WIDGET_STATE_ACTIVE && this.graphmax !== null) {
            this.#setGraphMaxSize(super._getContentsSize());
        }
    }

    promiseReady() {
        const readiness = [super.promiseReady()];

        if (this.gauge !== null) {
            readiness.push(this.gauge.promiseRendered());
        }

        return Promise.all(readiness);
    }

    getUpdateRequestData() {
        return {
            ...super.getUpdateRequestData(),
            with_config: (this.gauge === null || this.isFieldsReferredDataUpdated()) ? 1 : undefined
        };
    }

    setContents(response) {
        if (this.isFieldsReferredDataUpdated()) {
            this.clearContents();
        }

        if ('body' in response) {
            if (this.gauge !== null) {
                this.clearContents();
            }

            this._body.innerHTML = response.body;

            return;
        }

        if ('graphmax' in response) {
            this.graphmax.innerHTML = response.graphmax;
        }

        this.gauge_link.href = response.url;

        const value_data = {
            value: response.value,
            value_text: response.value_text || null,
            units_text: response.units_text || null
        };

        if (this.gauge !== null) {
            this.gauge.setValue(value_data);

            return;
        }

        this._body.innerHTML = '';
        this._body.appendChild(this.graphmax);
        this._body.appendChild(this.gauge_link);

        const padding = {
            vertical: CWidgetGaugeMAX.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_V,
            horizontal: CWidgetGaugeMAX.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_H
        };

        this.gauge = new CSVGGaugeMAX(this.gauge_link, padding, response.config);
        this.gauge.setSize(super._getContentsSize());
        this.gauge.setValue(value_data);
        this.#setGraphMaxSize(super._getContentsSize());
    }

    onClearContents() {
        if (this.gauge !== null) {
            this.gauge.destroy();
            this.gauge = null;
        }
    }

    getActionsContextMenu({can_copy_widget, can_paste_widget}) {
        const menu = super.getActionsContextMenu({can_copy_widget, can_paste_widget});

        if (this.isEditMode()) {
            return menu;
        }

        let menu_actions = null;

        for (const search_menu_actions of menu) {
            if ('label' in search_menu_actions && search_menu_actions.label === t('Actions')) {
                menu_actions = search_menu_actions;

                break;
            }
        }

        if (menu_actions === null) {
            menu_actions = {
                label: t('Actions'),
                items: []
            };

            menu.unshift(menu_actions);
        }

        menu_actions.items.push({
            label: t('Download image'),
            disabled: this.gauge === null,
            clickCallback: () => {
                downloadSvgImage(this.gauge.getSVGElement(), 'image.png');
            }
        });

        return menu;
    }

    hasPadding() {
        return false;
    }
}
