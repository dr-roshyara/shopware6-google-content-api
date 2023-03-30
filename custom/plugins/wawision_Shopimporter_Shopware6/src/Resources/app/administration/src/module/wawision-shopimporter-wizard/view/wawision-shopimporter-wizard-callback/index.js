import template from './wawision-shopimporter-wizard-callback.html.twig';
import './wawision-shopimporter-wizard-callback.scss';

const { Component } = Shopware;

Component.register('wawision-shopimporter-wizard-callback', {
    template,

    inject: {
        /** @var {WawisionApiService} wawisionService */
        wawisionService: 'wawisionService',
    },

    data() {
        return {
            token: null,
            error: false
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getToken();
            this.updateButtons();
        },

        getToken() {
            this.token = localStorage.getItem('xu-token');

            if (this.token == null) {
                this.$router.push({ name: 'wawision.shopimporter.wizard.index.settings'});
            }
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'back',
                    label: this.$tc('wawision-shopimporter.modal.buttonBack'),
                    position: 'left',
                    variant: null,
                    action: 'wawision.shopimporter.wizard.index.settings',
                    disabled: false
                },
                {
                    key: 'next',
                    label: this.$tc('wawision-shopimporter.modal.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: this.onNext.bind(this),
                    disabled: false
                }
            ];

            this.$emit('buttons-update', buttonConfig);
        },

        onNext() {
            this.isLoading = true;
            this.wawisionService.getcredentials(
                this.token
            ).then((res) => {
                this.isLoading = false;
                if (res.valid === true) {
                    localStorage.removeItem('xu-token');
                    this.$router.push({ name: 'wawision.shopimporter.wizard.index.finish' })
                } else {
                    this.error = true;
                };
            });
        },
    }

});
