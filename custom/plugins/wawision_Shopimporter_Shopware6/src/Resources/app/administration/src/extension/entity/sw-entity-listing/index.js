import template from './sw-entity-listing.html.twig';

const { Component } = Shopware;

Component.override('sw-entity-listing', {
    template,

    inject: {
        /** @var {WawisionApiService} wawisionService */
        wawisionService: 'wawisionService'
    },

    data() {
        return {
            showBulkSyncModal: false,
        };
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');
        },

        showBulkSyncButton() {
            return this.$route.name == "sw.product.index"
        },

        syncItems() {
            this.isBulkLoading = true;
            const promises = [];

            Object.values(this.selection).forEach((selectedProxy) => {
                promises.push(this.wawisionService.connectapi(
                    'articletoxentral',
                    selectedProxy.id
                ));
            });

            return Promise.all(promises).then(() => {
                return this.syncItemsFinish();
            }).catch(() => {
                return this.syncItemsFinish();
            });
        },

        syncItemsFinish() {
            this.resetSelection();
            this.isBulkLoading = false;
            this.showBulkSyncModal = false;

            return this.doSearch();
        }
    }
});
