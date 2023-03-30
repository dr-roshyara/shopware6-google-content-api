import template from './wawision-shopimporter-main.html.twig';
import './wawision-shopimporter-main.scss';

const { Component, Mixin } = Shopware;

Component.register('wawision-shopimporter-main', {
    template,

    inject: {
        systemConfigApiService: 'systemConfigApiService'
    },

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    data() {
        return {
            what: null,
            settings: false,
            dashboard: false,
            wawisionSettings: null,
        }
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        demoDescription() {
            return this.$tc('wawision-shopimporter.settings.demoDescription');
        },

        liveDescription() {
            return this.$tc('wawision-shopimporter.settings.liveDescription');
        },

        dashboardLinks() {
            return {
                "batchpicking": {
                    "title_de": "Batch Kommissionierung",
                    "title_en": "Batch Picking",
                    "png_de": "wawision_shopimporter_shopware6/static/img/de/Batch_Kommissionierung@1x.png",
                    "png_en": "wawision_shopimporter_shopware6/static/img/en/Batch_picking@1x.png",
                    "link_alt": "https://xentral.com/helpdesk/kurzanleitung-batches-picklisten"
                },
                "buchhaltungsexport": {
                    "title_de": "Datev Export",
                    "title_en": "Data export",
                    "png_de": "wawision_shopimporter_shopware6/static/img/de/Datev_Export@1x.png",
                    "png_en": "wawision_shopimporter_shopware6/static/img/en/Data_export@1x.png",
                    "link_alt": "https://xentral.com/helpdesk/kurzanleitung-export-fuer-die-buchhaltung"
                },
                "dropshipping": {
                    "title_de": "Dropshipping",
                    "title_en": "Dropshipping",
                    "png_de": "wawision_shopimporter_shopware6/static/img/de/Dropshipping@1x.png",
                    "png_en": "wawision_shopimporter_shopware6/static/img/en/Dropshipping@1x.png",
                    "link_alt": "https://xentral.com/helpdesk/dropshipping"
                },
                "kommissionierlauf": {
                    "title_de": "Einfache Pickliste",
                    "title_en": "Simple pick list",
                    "png_de": "wawision_shopimporter_shopware6/static/img/de/Einfache_Pickliste@1x.png",
                    "png_en": "wawision_shopimporter_shopware6/static/img/en/Simple_pick_list@1x.png",
                    "link_alt": "https://xentral.com/helpdesk/kurzanleitung-logistikprozesse"
                },
                "mahnwesen": {
                    "title_de": "Mahnwesen",
                    "title_en": "Dunning",
                    "png_de": "wawision_shopimporter_shopware6/static/img/de/Mahnwesen@1x.png",
                    "png_en": "wawision_shopimporter_shopware6/static/img/en/Dunning@1x.png",
                    "link_alt": "https://xentral.com/helpdesk/kurzanleitung-mahnwesen"
                },
                "versandarten": {
                    "title_de": "Paketmarken",
                    "title_en": "Parcel stamps",
                    "png_de": "wawision_shopimporter_shopware6/static/img/de/Paketmarken@1x.png",
                    "png_en": "wawision_shopimporter_shopware6/static/img/en/Parcel_stamps@1x.png",
                    "link_alt": "https://xentral.com/helpdesk/kurzanleitung-versandarten"
                },
                "pos": {
                    "title_de": "POS Anbinden",
                    "title_en": "Connect POS",
                    "png_de": "wawision_shopimporter_shopware6/static/img/de/POS_Anbinden@1x.png",
                    "png_en": "wawision_shopimporter_shopware6/static/img/en/Connect_POS@1x.png",
                    "link_alt": "https://xentral.com/helpdesk/kurzanleitung-arbeiten-mit-der-pos-kasse"
                },
                "retoure": {
                    "title_de": "Retouren",
                    "title_en": "Returns",
                    "png_de": "wawision_shopimporter_shopware6/static/img/de/Retouren@1x.png",
                    "png_en": "wawision_shopimporter_shopware6/static/img/en/Returns@1x.png",
                    "link_alt": "https://xentral.com/helpdesk/kurzanleitung-rma"
                },
                "wareneingang": {
                    "title_de": "Wareneingang",
                    "title_en": "Goods Receipt",
                    "png_de": "wawision_shopimporter_shopware6/static/img/de/Wareneingang@1x.png",
                    "png_en": "wawision_shopimporter_shopware6/static/img/en/Goods_Receipt@1x.png",
                    "link_alt": "https://xentral.com/helpdesk/kurzanleitung-zentraler-wareneingang"
                }
            }
        },

        locale() {
            return Shopware.State.getters.adminLocaleLanguage;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.loadwawisionSettings();
        },

        async loadwawisionSettings() {
            this.wawisionSettings = await this.systemConfigApiService.getValues('WawisionShopimporter.config');

            if (!('WawisionShopimporter.config.shopid' in this.wawisionSettings)) {
                this.settings = true;
            } else {
                this.dashboard = true;
            }
        },
    }
});
