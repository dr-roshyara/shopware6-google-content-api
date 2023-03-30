import template from './sw-dashboard-index.html.twig';
import './sw-dashboard-index.scss';

const { Component } = Shopware;

Component.override('sw-dashboard-index', {
    template,

    computed: {
        series() {
            return [
                {
                    name: 'Saleschannel A',
                    data: [
                        {x: 1559426400000, y: 7},
                        {x: 1559512800000, y: 6},
                        {x: 1559772000000, y: 9},
                        {x: 1559599200000, y: 0},
                        {x: 1559685600000, y: 2}
                    ]
                }, {
                    name: 'Saleschannel B',
                    data: [
                        {x: 1559426400000, y: 4},
                        {x: 1559512800000, y: 2},
                        {x: 1559599200000, y: 3},
                        {x: 1559685600000, y: 0},
                        {x: 1559772000000, y: 1}
                    ]
                }
            ];
        },

        options() {
            return {
                title: {
                    text: 'Number of orders'
                },
                xaxis: {
                    type: 'datetime',
                        min: 1559260800000,
                        max: 1559952000000
                },
                yaxis: {
                    min:0,
                        tickAmount:3,
                        labels:{
                        formatter: (value) => { return parseInt(value, 10);}
                    }
                }
            };
        }
    }

});
