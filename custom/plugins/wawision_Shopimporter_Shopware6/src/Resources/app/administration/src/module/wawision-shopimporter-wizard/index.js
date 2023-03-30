import './page/index';
import './component/wawision-shopimporter-wizard-modal';
import './view/wawision-shopimporter-wizard-welcome';
import './view/wawision-shopimporter-wizard-settings';
import './view/wawision-shopimporter-wizard-callback';
import './view/wawision-shopimporter-wizard-finish';

const { Module } = Shopware;

Module.register('wawision-shopimporter-wizard', {
    type: 'plugin',
    name: 'wawision-shopimporter-wizard',
    title: 'wawision-shopimporter.wizard.mainMenuItemGeneral',
    description: 'wawision-shopimporter.wizard.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#42b8c5',

    routes: {
        index: {
            component: 'wawision-shopimporter-wizard',
            path: 'index/:what',
            redirect: {
                name: 'wawision.shopimporter.wizard.index.welcome'
            },
            children: {
                welcome: {
                    component: 'wawision-shopimporter-wizard-welcome',
                    path: ''
                },
                settings: {
                    component: 'wawision-shopimporter-wizard-settings',
                    path: 'settings'
                },
                callback: {
                    component: 'wawision-shopimporter-wizard-callback',
                    path: 'callback'
                },
                finish: {
                    component: 'wawision-shopimporter-wizard-finish',
                    path: 'finish'
                }
            },
            props: {
                default(route) {
                    return {
                        what: route.params.what
                    };
                }
            }
        },
    },
});
