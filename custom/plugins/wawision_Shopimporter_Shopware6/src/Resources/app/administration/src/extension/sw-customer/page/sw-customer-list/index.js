import template from "./sw-customer-list.html.twig"
import './sw-customer-list.scss';

const { Component, Mixin } = Shopware;

Component.override('sw-customer-list', {
    template,

    inject: {
        /** @var {WawisionApiService} wawisionService */
        wawisionService: 'wawisionService'
    },

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: true,
        };
    },

    computed: {
        customerColumns() {
            const columns = this.$super('customerColumns');

            columns.push({
                property: 'customFields.wawision_shopimporter_datetime',
                label: 'Xentral', //'wawision-shopimporter.extension.syncWithXentral',
                allowResize: false,
                width: '75px',
                align: 'center',
                visible: true
            });

            return columns;
        }
    },

    methods: {
        hasDateTime(item) {
            if (item.customFields != null && item.customFields.wawision_shopimporter_datetime != undefined) {
                return true;
            }
            return false;
        },

        openCustomerLink(item) {
            const number = item.customerNumber;

            this.wawisionService.buildurl(
                'onlineshops',
                'orderlink',
                number
            ).then((res) => {
                if (res.valid === true) {
                    window.open(res.url, '_blank');
                } else {
                    this.createNotificationError({
                        title: this.$root.$tc('global.default.error'),
                        message: this.$tc('wawision-shopimporter.extension.errorURL')
                    });
                }
            });
        }
    }
});
