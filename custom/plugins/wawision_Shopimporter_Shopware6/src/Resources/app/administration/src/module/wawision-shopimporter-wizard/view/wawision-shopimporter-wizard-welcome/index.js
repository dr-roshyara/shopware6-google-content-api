import template from './wawision-shopimporter-wizard-welcome.html.twig';
import './wawision-shopimporter-wizard-welcome.scss';

const { Component } = Shopware;

Component.register('wawision-shopimporter-wizard-welcome', {
    template,

    created() {
        this.createdComponent();
    },

    computed: {
        what() {
            return this.$route.params.what;
        },

        welcomeHeadline() {
            if (this.what == 'live') {
                return this.$tc('wawision-shopimporter.modal.liveHeadlineWelcome');
            }
            return this.$tc('wawision-shopimporter.modal.demoHeadlineWelcome');
        },

        welcomeDescription() {
            if (this.what == 'live') {
                return this.$tc('wawision-shopimporter.modal.liveDescription');
            }
            return this.$tc('wawision-shopimporter.modal.demoDescription');
        },
    },

    methods: {
        createdComponent() {
            this.updateButtons();
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'next',
                    label: this.$tc('wawision-shopimporter.modal.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: 'wawision.shopimporter.wizard.index.settings',
                    disabled: false
                }
            ];

            this.$emit('buttons-update', buttonConfig);
        },

        onRequestDemo() {
            document.open('https://wawision.com/demo', '_blank');
        },
    }

});
