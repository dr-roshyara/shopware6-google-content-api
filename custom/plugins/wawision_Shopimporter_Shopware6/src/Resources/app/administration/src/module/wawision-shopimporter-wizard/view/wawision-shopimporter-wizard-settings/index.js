import template from './wawision-shopimporter-wizard-settings.html.twig';
import './wawision-shopimporter-wizard-settings.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('wawision-shopimporter-wizard-settings', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    inject: {
        /** @var {WawisionApiService} wawisionService */
        wawisionService: 'wawisionService',
        repositoryFactory: 'repositoryFactory',
        systemConfigApiService: 'systemConfigApiService'
    },

    data() {
        return {
            isLoading: true,
            hasValidDomain: false,
            user: null,
            accessXentralString: null,
            wawisionSettings: {
                'WawisionShopimporter.config.host': null,
            }
        }
    },

    computed: {
        userRepository() {
            return this.repositoryFactory.create('user');
        },

        validateDomain() {
            if (this.wawisionSettings['WawisionShopimporter.config.host'] == null) {
                this.hasValidDomain = false;

                this.updateButtons();
                return this.hasValidDomain;
            }

            var re = new RegExp(/^(http[s]?\:\/\/)?((\w+)\.)?(([\w-]+)?)(\.[\w-]+){1,2}[\/]?$/);
            this.hasValidDomain = this.wawisionSettings['WawisionShopimporter.config.host'].match(re);

            this.updateButtons();
            return this.hasValidDomain;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.updateButtons();
            await this.loadPageContent();
            await this.buildAccessString();
        },

        async loadPageContent() {
            await this.loadwawisionSettings();
            await this.loadUser();
            await this.buildPassword();
            await this.saveUser();
        },

        async loadwawisionSettings() {
            this.isLoading = true;
            this.wawisionSettings = await this.systemConfigApiService.getValues('WawisionShopimporter.config');

            this.isLoading = false;
        },

        async loadUser() {
            const criteria = new Criteria(1);
            criteria.addAssociation('accessKeys');
            criteria.addFilter(Criteria.equals('username', 'xentral-admin'));

            return await this.userRepository.search(criteria, Shopware.Context.api).then((user) => {
                this.user = user[0];

                this.keyRepository = this.repositoryFactory.create(this.user.accessKeys.entity, this.user.accessKeys.source);
            });
        },

        async buildPassword() {
            let passwd;
            for(passwd = ''; passwd.length < 30;) {
                passwd += "0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz"[(Math.random() * 60) | 0];
            }

            this.user.password = passwd;
        },

        async saveUser() {
            return await this.wawisionService.saveuser(
                this.user
            ).then(() => {
            // return await this.userRepository.save(this.user, Shopware.Context.api).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((exception) => {
                this.isLoading = false;
                throw exception;
            });
        },

        buildAccessString() {
            const shopurl = document.location.origin;
            const backurl = document.location.pathname + (this.$router.resolve({ name: 'wawision.shopimporter.wizard.index.callback' }).href);
            const apipath = (document.location.pathname).replace(/\/admin$/, '/api');

            let string = {
                "token": this.generateTempToken(),
                "shoptype": "shopimporter_shopware6",
                "url": shopurl + backurl,
                "data": {
                    "shopwareUserName": this.user.username,
                    "shopwarePassword": this.user.password,
                    "shopwareUrl": shopurl + apipath
                }
            }

            this.accessXentralString = JSON.stringify(string);
        },

        generateTempToken() {
            for(var token = ''; token.length < 128;) {
                token += "0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz"[(Math.random() * 60) | 0];
            }

            localStorage.setItem('xu-token', token);
            return token;
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'back',
                    label: this.$tc('wawision-shopimporter.modal.buttonBack'),
                    position: 'left',
                    variant: null,
                    action: 'wawision.shopimporter.wizard.index.welcome',
                    disabled: false
                },
                {
                    key: 'next',
                    label: this.$tc('wawision-shopimporter.modal.buttonSaveAndJump'),
                    position: 'right',
                    variant: 'primary',
                    action: this.onNext.bind(this),
                    disabled: !this.hasValidDomain
                }
            ];

            this.$emit('buttons-update', buttonConfig);
        },

        onNext() {
            this.isLoading = true;
            this.wawisionSettings['WawisionShopimporter.config.host'] = this.wawisionSettings['WawisionShopimporter.config.host'].replace(/\/+$/,'');
            this.systemConfigApiService.saveValues(
                this.wawisionSettings
            ).then(() => {
                this.isLoading = false;
                this.onSaveFinish()
            });
        },

        onSaveFinish() {
            this.wawisionService.jumpwawision(
                this.accessXentralString
            ).then((res) => {
                    if (res.valid === true) {
                        window.open(res.url, '_self');
                    };
                });
        }
    }

});
