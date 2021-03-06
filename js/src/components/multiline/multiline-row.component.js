import multilineCell from './multiline-cell.component';

/**
 * A row component.
 * This will create a table td element using sui-table-cell.
 * The td element is created only if column as set isVisible = true;
 * The td element will add a multiline cell element.
 *  the multiline cell will set it's own template component depending on the fieldType.
 *  getValue
 *
 */
export default {
    name: 'atk-multiline-row',
    template: `
    <sui-table-row :verticalAlign="'middle'">
        <sui-table-cell width="one" textAlign="center"><input type="checkbox" @input="onToggleDelete" v-model="toDelete"></input></sui-table-cell>
        <sui-table-cell  @keydown.tab="onTab(idx)" v-for="(column, idx) in columns" :key="idx" :state="getErrorState(column)" :width="getColumnWidth(column)" :style="{overflow: 'visible'}" v-if="column.isVisible" :textAlign="getTextAlign(column)">
         <atk-multiline-cell 
           :componentName="getMapComponent(column)" 
           :cellData="column" 
           @update-value="onUpdateValue"
           @post-value="onPostRow"
           :fieldValue="getValue(column)"
           :componentProps="getComponentProps(column)"></atk-multiline-cell>
        </sui-table-cell>
    </sui-table-row>
  `,
    props: ['fields', 'rowId', 'isDeletable', 'values', 'error'],
    data: function () {
        return { columns: this.fields };
    },
    components: {
        'atk-multiline-cell': multilineCell,
    },
    inject: ['getRootData'],
    computed: {
    /**
     * toDelete is bind by v-model, thus we need a setter for
     * computed property to work.
     * When isDeletable is pass, will set checkbox according to it's value.
     */
        toDelete: {
            get: function () {
                return this.isDeletable;
            },
            set: function (v) {
                return v;
            },
        },
    },
    methods: {
        onTab: function (idx) {
            if (idx === this.columns.filter((column) => column.isEditable).length) {
                this.$emit('onTabLastColumn');
            }
        },
        getErrorState: function (column) {
            // console.log(column);
            if (this.error) {
                const error = this.error.filter((e) => column.field === e.field);
                if (error.length > 0) {
                    return 'error';
                }
            }
            return null;
        },
        getColumnWidth: function (column) {
            return column.fieldOptions ? column.fieldOptions.width : null;
        },
        onEdit: function () {
            this.isEditing = true;
        },
        onToggleDelete: function (e) {
            atk.eventBus.emit(this.$root.$el.id + '-toggle-delete', { rowId: this.rowId });
        },
        onUpdateValue: function (field, value) {
            atk.eventBus.emit(this.$root.$el.id + '-update-row', { rowId: this.rowId, field: field, value: value });
        },
        onPostRow: function (field) {
            atk.eventBus.emit(this.$root.$el.id + '-post-row', { rowId: this.rowId, field: field });
        },
        getReadOnlyValue: function (column) {
            if (!column.isEditable) {
                return this.getValue(column);
            }
            return null;
        },
        getValue: function (column) {
            let temp = column.default;
            this.values.forEach((field) => {
                if (column.field in field) {
                    temp = field[column.field];
                }
            });
            return temp;
        },
        /**
     * Return component specific props.
     * When dropdown is use for example.
     *
     * @param column
     */
        getComponentProps: function (column) {
            let props = {};
            const userOptions = column.fieldOptions || {};
            const flatpickrConfig = { ...this.getRootData().data.options.flatpickr || {} };

            switch (column.component) {
            case 'dropdown':
                props = {
                    floating: true,
                    closeOnBlur: true,
                    openOnFocus: false,
                    selection: true,
                    ...this.getRootData().data.options.suiDropdown || {},
                    ...userOptions,
                };
                props.options = this.getEnumValues(userOptions.values || null);
                break;
            case 'date':
            case 'datetime':
            case 'time':
                if (column.component === 'datetime' || column.component === 'time') {
                    flatpickrConfig.enableTime = true;
                    flatpickrConfig.time_24hr = true;
                }
                if (column.component === 'time') {
                    flatpickrConfig.noCalendar = true;
                }
                props = { config: { ...flatpickrConfig, ...userOptions } };
                break;
            default:
                props = Object.assign(props, userOptions);
            }

            return props;
        },
        /**
     * Map values for Sui Dropdown.
     * Values are possible value for dropdown.
     *
     * @param values
     * @returns {{text: *, value: string, key: string}[]}
     */
        getEnumValues: function (values) {
            if (values) {
                return Object.keys(values).map((key) => ({ key: key, value: key, text: values[key] }));
            }
        },
        /**
     * Return proper component name based on component set.
     *
     * @param column
     * @returns {string}
     */
        getMapComponent: function (column) {
            let component;
            if (!column.isEditable) {
                component = 'atk-multiline-readonly';
            } else {
                switch (column.component) {
                case 'input':
                case 'dropdown':
                case 'checkbox':
                    component = 'sui-' + column.component;
                    break;
                case 'textarea':
                    component = 'atk-multiline-textarea';
                    break;
                case 'date':
                case 'time':
                case 'datetime':
                    component = 'atk-date-picker';
                    break;
                default:
                    component = 'sui-input';
                }
            }
            return component;
        },
        /**
     * return text alignement for cell depending on field type.
     *
     * @param column
     * @returns {string}
     */
        getTextAlign: function (column) {
            let align;
            const type = column.fieldOptions ? column.fieldOptions.type : 'text';
            switch (type) {
            case 'money':
            case 'integer':
            case 'number':
                align = 'right';
                break;
            default:
                align = 'left';
                break;
            }
            return align;
        },
    },
    getDateFromString: function (dateString) {
        return dateString ? new Date(atk.exec().dateParse(dateString)) : new Date();
    },
};
