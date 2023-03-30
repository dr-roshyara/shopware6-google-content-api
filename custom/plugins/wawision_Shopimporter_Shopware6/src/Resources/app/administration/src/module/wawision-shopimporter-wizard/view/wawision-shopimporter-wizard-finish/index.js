import template from './wawision-shopimporter-wizard-finish.html.twig';
import './wawision-shopimporter-wizard-finish.scss';

const { Component } = Shopware;

Component.register('wawision-shopimporter-wizard-finish', {
    template,

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'back',
                    label: this.$tc('wawision-shopimporter.modal.buttonBack'),
                    position: 'left',
                    variant: null,
                    action: 'wawision.shopimporter.wizard.index.callback',
                    disabled: false
                },
                {
                    key: 'finish',
                    label: this.$tc('wawision-shopimporter.modal.buttonFinish'),
                    position: 'right',
                    variant: 'primary',
                    action: this.onFinish.bind(this),
                    disabled: false
                }
            ];

            this.$emit('buttons-update', buttonConfig);
        },

        onFinish() {
            this.$emit('xc-finish', true);
        }
    }

});
