import './page/wawision-shopimporter-main';
import './component/wawision-shopimporter-dashboard';
import deDE from '../../snippet/de-DE.json';
import enGB from '../../snippet/en-GB.json';

const { Module } = Shopware;

Module.register('wawision-shopimporter', {
    type: 'plugin',
    name: 'wawision-shopimporter',
    title: 'wawision-shopimporter.general.mainMenuItemGeneral',
    description: 'wawision-shopimporter.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#42b8c5',
    icon: 'default-basic-x-wide',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        main: {
            component: 'wawision-shopimporter-main',
            path: 'main',
            icon: 'default-action-settings',
        },
    },

    navigation: [{
        id: 'wawision-shopimporter',
        label: 'wawision-shopimporter.general.mainMenuItemGeneral',
        color: '#42b8c5',
        path: 'wawision.shopimporter.main',
        icon: 'default-basic-x-wide',
        parent: 'sw-settings',
        position: 999
    }]

});
