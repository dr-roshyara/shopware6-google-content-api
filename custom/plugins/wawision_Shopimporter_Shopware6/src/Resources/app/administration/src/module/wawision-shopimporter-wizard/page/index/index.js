import template from './wawision-shopimporter-wizard.html.twig';

const { Component } = Shopware;

Component.register('wawision-shopimporter-wizard', {
    template,

    data() {
        return {
            showModal: true,
        }
    }

});
