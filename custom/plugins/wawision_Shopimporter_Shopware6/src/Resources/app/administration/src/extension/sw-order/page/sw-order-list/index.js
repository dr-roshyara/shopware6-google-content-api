import template from "./sw-order-list.html.twig"
import './sw-order-list.scss';

const { Component, Mixin } = Shopware;

Component.override('sw-order-list', {
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
        orderColumns() {
            const columns = this.$super('orderColumns');

            columns.push({
                property: 'customFields.wawision_shopimporter_syncstate',
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
        hasSynced(item) {
            if (item.customFields != null && item.customFields.wawision_shopimporter_syncstate != undefined) {
                if (item.customFields.wawision_shopimporter_syncstate == 1) {
                    return true;
                }
            }
            return false;
        },

        openOrderLink(item) {
            const orderId = item.id;

            this.wawisionService.buildurl(
                'onlineshops',
                'orderlink',
                orderId
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
        },

        onOrderToXentral(item) {
            const orderId = item.id;

            this.wawisionService.connectapi(
                'ordertowawision',
                orderId
            ).then((res) => {
                console.log(res);
                this.onRefresh();
            });
        },
    }
});
