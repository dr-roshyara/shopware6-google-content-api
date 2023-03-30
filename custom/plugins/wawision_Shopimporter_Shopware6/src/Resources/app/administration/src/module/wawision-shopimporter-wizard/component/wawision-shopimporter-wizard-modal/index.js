import template from './wawision-shopimporter-wizard-modal.html.twig';
import './wawision-shopimporter-wizard-modal.scss';

const { Component } = Shopware;

Component.register('wawision-shopimporter-wizard-modal', {
    template,

    data() {
        return {
            buttonConfig: [],
            stepVariant: 'info',
            currentStep: {
                name: '',
                variant: 'large',
                navigationIndex: 0
            },
            stepper: {
                welcome: {
                    name: 'wawision.shopimporter.wizard.index.welcome',
                    variant: 'large',
                    navigationIndex: 0
                },
                settings: {
                    name: 'wawision.shopimporter.wizard.index.settings',
                    variant: 'large',
                    navigationIndex: 1
                },
                callback: {
                    name: 'wawision.shopimporter.wizard.index.callback',
                    variant: 'large',
                    navigationIndex: 2
                },
                finish: {
                    name: 'wawision.shopimporter.wizard.index.finish',
                    variant: 'large',
                    navigationIndex: 3
                },
            },
        };
    },

    computed: {
        modalTitle() {
            if (this.$route.params.what == 'live') {
                return this.$tc('wawision-shopimporter.modal.liveTitle');
            }
            return this.$tc('wawision-shopimporter.modal.demoTitle');
        },


        columns() {
            const res = this.showSteps
                ? '1fr 4fr'
                : '1fr';

            return res;
        },

        variant() {
            const { variant } = this.currentStep;

            return variant;
        },

        showSteps() {
            const { navigationIndex } = this.currentStep;

            return navigationIndex !== 0;
        },

        buttons() {
            return {
                right: this.buttonConfig.filter((button) => button.position === 'right'),
                left: this.buttonConfig.filter((button) => button.position === 'left')
            };
        },

        stepIndex() {
            const { navigationIndex } = this.currentStep;

            if (navigationIndex < 1) {
                return 0;
            }

            return navigationIndex - 1;
        },

        stepInitialItemVariants() {
            const navigationSteps = [
                ['disabled', 'disabled', 'disabled'],
                ['info', 'disabled', 'disabled'],
                ['success', 'info', 'disabled'],
                ['success', 'success', 'info']
            ];
            const { navigationIndex } = this.currentStep;

            return navigationSteps[navigationIndex];
        },
    },

    watch: {
        '$route'(to) {
            const toName = to.name.replace('wawision.shopimporter.wizard.index.', '');

            this.currentStep = this.stepper[toName];
        }
    },

    mounted() {
        const step = this.$route.name.replace('wawision.shopimporter.wizard.index.', '');

        this.currentStep = this.stepper[step];
    },

    methods: {
        updateButtons(buttonConfig) {
            this.buttonConfig = buttonConfig;
        },

        onButtonClick(action) {
            if (typeof action === 'string') {
                this.redirect(action);
                return;
            }

            if (typeof action !== 'function') {
                return;
            }

            action.call();
        },

        redirect(routeName) {
            this.$router.push({ name: routeName });
        },

        onCloseModal() {
            this.$emit('modal-close');
            this.$nextTick(() => this.$router.push({ name: 'wawision.shopimporter.main' }));
        },

    }

});
