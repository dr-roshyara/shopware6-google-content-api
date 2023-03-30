import template from './wawision-shopimporter-dashboard.html.twig'
import './wawision-shopimporter-dashboard.scss';

const { Component, Mixin } = Shopware;

Component.register('wawision-shopimporter-dashboard', {
    template,

    inject: {
        /** @var {WawisionApiService} wawisionService */
        wawisionService: 'wawisionService'
    },

    mixins: [
        Mixin.getByName('notification'),
    ],

    data () {
        return {
            init: false,
            isLinksLoading: false,
            isLoadingStats: false,
            polling: null,
            status: false,
            ready: false,
            progress: 0,
            total: 2536,
            connected: false,
            ordersData: {},
            tableData: [],
            dashboardLinks: {}
        }
    },

    computed: {
        columns() {
            return [{
                property: 'id',
                label: '',
                width: '20%',
                allowResize: false,
                sortable: false
            }, {
                property: 'pakete',
                label: this.$tc('wawision-shopimporter.dashboard.packagesColumn'),
                width: '20%',
                allowResize: false,
                sortable: false
            }, {
                property: 'umsatz',
                label: this.$tc('wawision-shopimporter.dashboard.turnoverColumn'),
                width: '20%',
                allowResize: false,
                sortable: false
            }, {
                property: 'DB1',
                label: 'DB in €',
                width: '20%',
                allowResize: false,
                sortable: false
            }, {
                property: 'DB2',
                label: 'DB in %',
                width: '20%',
                allowResize: false,
                sortable: false
            }];
        },

        wawisionSettings() {
            return this.$attrs.wawisionSettings;
        },

        locale() {
            return Shopware.State.getters.adminLocaleLanguage;
        },
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy () {
        clearInterval(this.polling)
    },

    methods: {
        async createdComponent() {
            await this.getStatus();
            if (this.connected === true) {
                await this.loadData();
                //this.pollData();
            }
        },

        async getStatus() {
            await this.wawisionService.connectapi(
                'status'
            ).then((res) => {
                if (res.valid === true && res.result.success === true) {
                    this.connected = res.result.connected;
                } else {
                    this.showMessage(res.result);
                }
            });

            this.init = true;
        },

        async loadData() {
            this.isLinksLoading = true;
            this.isLoadingStats = true;
            await this.getTableData();
            await this.getDashboardLinks();
        },

        async getTableData() {
            await this.wawisionService.connectapi(
                'statistics'
            ).then((res) => {
                if (res.valid === true && res.result.success === true) {
                    let stats = res.result.stats;

                    this.tableData = [
                        {
                            id: this.$tc('wawision-shopimporter.dashboard.todayRow'),
                            pakete: stats.packages_today,
                            umsatz: Number(stats.order_income_today).toFixed(2) + ' €',
                            DB1: Number(stats.contribution_margin_today).toFixed(2) + ' €',
                            DB2: Number(stats.contribution_margin_perc_today).toFixed(2) + ' %',
                        },
                        {
                            id: this.$tc('wawision-shopimporter.dashboard.yesterdayRow'),
                            pakete: stats.packages_yesterday,
                            umsatz: Number(stats.order_income_yesterday).toFixed(2) + ' €',
                            DB1: Number(stats.contribution_margin_yesterday).toFixed(2) + ' €',
                            DB2: Number(stats.contribution_margin_perc_yesterday).toFixed(2) + ' %',
                        },
                    ];

                    this.ordersData = {
                        shipment: stats.orders_in_shipment,
                        open: stats.orders_open
                    }

                    this.isLoadingStats = false;
                }
            });
        },

        async getDashboardLinks() {
            await this.wawisionService.usecases().then((res) => {
                if (res.valid === true) {
                    this.dashboardLinks = res.result;
                }

                this.isLinksLoading = false;
            });
        },

        pollData () {
            this.polling = setInterval(() => {
                this.wawisionService.connectapi(
                    'articlesyncstate'
                ).then((res) => {
                    if (res.valid === true) {
                        this.status = true;
                        console.log('intervall', res);

                        this.progress += 16;

                        if (this.progress >= this.total) {
                            clearInterval(this.polling);
                            this.ready = true;
                        }
                    }
                });
            }, 2000)
        },

        onCloseStatus() {
            return this.status = false;
        },

        onwawisionSettings() {
            this.wawisionService.buildurl(
                'onlineshops',
                'edit',
                this.wawisionSettings['WawisionShopimporter.config.shopid']
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

        onDisconnect() {
            this.wawisionService.connectapi(
                'disconnect'
            ).then((res) => {
                if (res.valid === true && res.result.success === true) {
                    this.connected = false;
                } else {
                    this.showMessage(res.result);
                }
            });
        },

        onReconnect() {
            this.wawisionService.connectapi(
                'reconnect'
            ).then((res) => {
                if (res.valid === true && res.result.success === true) {
                    this.connected = true;
                    this.loadData();
                } else {
                    this.showMessage(res.result);
                }
            });
        },

        showMessage(result) {
            this.createNotificationError({
                title: this.$root.$tc('global.default.error'),
                message: result.error.message
            });
        }
    },
});
