import template from "./sw-product-list.html.twig"
import './sw-product-list.scss';

const { Component, Mixin } = Shopware;

Component.override('sw-product-list', {
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
            isLoading: true
        };
    },

    computed: {
        productColumns() {
            const columns = this.$super('productColumns');

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

        openProductLink(item) {
            const productId = item.id;

            this.wawisionService.buildurl(
                'onlineshops',
                'itemlink',
                productId
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

        onSyncStorage(item) {
            const productId = item.id;

            this.wawisionService.connectapi(
                'syncstorage',
                productId
            ).then((res) => {
                console.log(res);
            });
        },

        onArticleToXentral(item) {
            const productId = item.id;

            this.wawisionService.connectapi(
                'articletoxentral',
                productId
            ).then((res) => {
                console.log(res);
                this.onRefresh();
            });
        },

        onArticleToShop(item) {
            const productId = item.id;

            this.wawisionService.connectapi(
                'articletoshop',
                productId
            ).then((res) => {
                console.log(res);
                this.onRefresh();
            });
        },
    }
});
