<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    'orderNumber' => '10012',
    'currencyId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
    'currencyFactor' => 1,
    'salesChannelId' => '98432def39fc4624b33213a56b8c944d',
    'billingAddressId' => 'f29e62f4b0a94e1cb279a2b9a1c0ab5e',
    'orderDateTime' => '2020-10-14T15:07:42.651+00:00',
    'price' => [
        'netPrice' => 4133.3,
        'totalPrice' => 4376.45,
        'calculatedTaxes' => [
            [
                'tax' => 243.14999999999998,
                'taxRate' => 7,
                'price' => 3473.6728971962616,
            ],
            [
                'tax' => 0,
                'taxRate' => 0,
                'price' => 659.62,
            ],
        ],
        'taxRules' => [
            [
                'taxRate' => 7,
                'percentage' => 100,
            ],
            [
                'taxRate' => 0,
                'percentage' => 100,
            ],
        ],
        'positionPrice' => 4133.3,
        'taxStatus' => 'net',
    ],
    'shippingCosts' => [
        'unitPrice' => 0,
        'quantity' => 1,
        'totalPrice' => 0,
        'calculatedTaxes' => [
            [
                'tax' => 0,
                'taxRate' => 7,
                'price' => 0,
            ],
        ],
        'taxRules' => [
            [
                'taxRate' => 7,
                'percentage' => 84.0411510704827,
            ],
            [
                'taxRate' => 0,
                'percentage' => 15.958677086105533,
            ],
        ],
    ],
    'orderCustomer' => [
        'email' => 'b018844eae3d4d7d97901a8d3955516etpouros@example.net',
        'orderId' => 'efaf7e8752b242baa67ed795d18f785d',
        'salutationId' => '7c2800508bce4ee0a1f6aee7c9698831',
        'firstName' => 'Hoyt',
        'lastName' => 'Murphy',
        'title' => 'Prof. Dr.',
        'customerNumber' => '10005',
        'customerId' => 'b018844eae3d4d7d97901a8d3955516e',
        'id' => '73c9c6d9fd5d4f0b8b7a5d124a7c1264',
    ],
    'languageId' => '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
    'addresses' => [
        [
            'countryId' => '793d37407690486fa3dc0f72cde3e5fc',
            'salutationId' => '7c2800508bce4ee0a1f6aee7c9698831',
            'firstName' => 'Hoyt',
            'lastName' => 'Murphy',
            'street' => 'Eve Extension',
            'zipcode' => '18586-5037',
            'city' => 'Hegmannfurt',
            'title' => 'Prof. Dr.',
            'orderId' => 'efaf7e8752b242baa67ed795d18f785d',
            'id' => 'f29e62f4b0a94e1cb279a2b9a1c0ab5e',
        ],
        [
            'countryId' => 'b5900f53fdf44cc49e668222f435b77b',
            'salutationId' => '7c2800508bce4ee0a1f6aee7c9698831',
            'firstName' => 'Hoyt',
            'lastName' => 'Murphy',
            'street' => 'Williamson Square',
            'zipcode' => '99658',
            'city' => 'Kihnborough',
            'title' => 'Prof. Dr.',
            'orderId' => 'efaf7e8752b242baa67ed795d18f785d',
            'id' => 'f96e79ec949d4b90931a89216887b438',
        ],
    ],
    'deliveries' => [
        [
            'orderId' => 'efaf7e8752b242baa67ed795d18f785d',
            'shippingOrderAddressId' => 'f96e79ec949d4b90931a89216887b438',
            'shippingMethodId' => '256e38edb1274bc3a5e3a9c65371c550',
            'shippingDateEarliest' => '2020-11-16T00:00:00.000+00:00',
            'shippingDateLatest' => '2020-11-18T00:00:00.000+00:00',
            'shippingCosts' => [
                'unitPrice' => 0,
                'quantity' => 1,
                'totalPrice' => 0,
                'calculatedTaxes' => [
                    [
                        'tax' => 0,
                        'taxRate' => 7,
                        'price' => 0,
                    ],
                ],
                'taxRules' => [
                    [
                        'taxRate' => 7,
                        'percentage' => 84.0411510704827,
                    ],
                    [
                        'taxRate' => 0,
                        'percentage' => 15.958677086105533,
                    ],
                ],
            ],
            'shippingOrderAddress' => [
                'countryId' => 'b5900f53fdf44cc49e668222f435b77b',
                'salutationId' => '7c2800508bce4ee0a1f6aee7c9698831',
                'firstName' => 'Hoyt',
                'lastName' => 'Murphy',
                'street' => 'Williamson Square',
                'zipcode' => '99658',
                'city' => 'Kihnborough',
                'title' => 'Prof. Dr.',
                'orderId' => 'efaf7e8752b242baa67ed795d18f785d',
                'id' => 'f96e79ec949d4b90931a89216887b438',
            ],
            'stateId' => '5195a80dc2f0447187cd6cb9a3e77079',
            'stateMachineState' => [
                'name' => 'Open',
                'technicalName' => 'open',
                'stateMachineId' => '33e6da5ed70345feaeb3a65c5dfc8a0e',
                'stateMachine' => [
                    'technicalName' => 'order_delivery.state',
                    'name' => 'Order state',
                    'initialStateId' => '5195a80dc2f0447187cd6cb9a3e77079',
                    'id' => '33e6da5ed70345feaeb3a65c5dfc8a0e',
                ],
                'id' => '5195a80dc2f0447187cd6cb9a3e77079',
            ],
            'id' => '8c6259d6e4ef44ed93ed244b349305a2',
        ],
    ],
    'lineItems' => [
        [
            'orderId' => 'efaf7e8752b242baa67ed795d18f785d',
            'identifier' => '59708e360b82402bbf3ceb2b7bff4ebb',
            'referencedId' => '59708e360b82402bbf3ceb2b7bff4ebb',
            'productId' => '59708e360b82402bbf3ceb2b7bff4ebb',
            'quantity' => 2,
            'unitPrice' => 329.81,
            'totalPrice' => 659.62,
            'label' => 'Heavy Duty Granite Dandy Brand',
            'good' => true,
            'removable' => true,
            'coverId' => 'fb1edaf2e5464a869d8aab3d5a20fdc9',
            'stackable' => true,
            'position' => 3,
            'price' => [
                'unitPrice' => 329.81,
                'quantity' => 2,
                'totalPrice' => 659.62,
                'calculatedTaxes' => [
                    [
                        'tax' => 0,
                        'taxRate' => 0,
                        'price' => 659.62,
                    ],
                ],
                'taxRules' => [
                    [
                        'taxRate' => 0,
                        'percentage' => 100,
                    ],
                ],
            ],
            'priceDefinition' => [
                'price' => 329.81,
                'taxRules' => [
                    [
                        'taxRate' => 0,
                        'percentage' => 100,
                    ],
                ],
                'quantity' => 2,
                'isCalculated' => true,
                'precision' => 2,
                'listPrice' => 0,
                'type' => 'quantity',
            ],
            'payload' => [
                'isCloseout' => false,
                'isNew' => false,
                'purchasePrice' => 90.85,
                'purchasePrices' => '{"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","net":90.85,"gross":90.85,"linked":true,"listPrice":null,"extensions":[]}',
                'productNumber' => '63c0720dd1d748efa94220256e2bb5c7',
                'manufacturerId' => '3634c8f559524c85b59acadfd419a93a',
                'taxId' => 'ddeda88505ca4ce39799b51ccfab5cac',
                'categoryIds' => [
                    '6ef1902522cc4adda31b8eaec53c870d',
                ],
                'propertyIds' => [
                    '1b5528beede34979aa7833b162a8c2f6',
                    '1fa100fbfb4a40569e7dd789da5d400b',
                    '219b93998d7d430f970fbdc109dba10e',
                    '6421ab21d3cf431db8118f04d2fad161',
                    '9204e42cea97415592ae632ceb1a3885',
                    '98638385e36444729e83055116fc7c3a',
                    '9a1752a9d6c741069a0edbc9b47e12df',
                    'a066b075b4b747ae88b14ea45edd5e74',
                    'aebc48fb76a94c848419530c990dc739',
                    'b32a5d9d7f1541e89ad8554cbfe3e12c',
                    'bdee6d99422a4b41953de116ddac520f',
                    'd0fa6f4a2c084209b48812056627f215',
                    'ec056bfebdb941dbb6f4f7796c11df98',
                    'ed686195690d4791b27f66a00615c0be',
                    'f268376e71564abfa1dc857ea8e08508',
                    'f8eb11e818a24356a817549f615d5ec7',
                    'fc4dc89dc001407ab5dc7ffb2c72e7c7',
                    'fd957ea8663048ae8af092bd404151fa',
                ],
            ],
            'type' => 'product',
            'id' => '1e71d98f547e47739ed6db604e270e2f',
        ],
        [
            'orderId' => 'efaf7e8752b242baa67ed795d18f785d',
            'identifier' => '553274a0ccb641488c5ba5801414d2bc',
            'referencedId' => '553274a0ccb641488c5ba5801414d2bc',
            'productId' => '553274a0ccb641488c5ba5801414d2bc',
            'quantity' => 1,
            'unitPrice' => 590.4953271028038,
            'totalPrice' => 590.5,
            'label' => 'Mediocre Steel Quick Licks',
            'good' => true,
            'removable' => true,
            'coverId' => '5469f7b0dfb64621b6cd2926b4721612',
            'stackable' => true,
            'position' => 1,
            'price' => [
                'unitPrice' => 590.4953271028038,
                'quantity' => 1,
                'totalPrice' => 590.5,
                'calculatedTaxes' => [
                    [
                        'tax' => 41.33,
                        'taxRate' => 7,
                        'price' => 590.4953271028038,
                    ],
                ],
                'taxRules' => [
                    [
                        'taxRate' => 7,
                        'percentage' => 100,
                    ],
                ],
            ],
            'priceDefinition' => [
                'price' => 590.4953271028038,
                'taxRules' => [
                    [
                        'taxRate' => 7,
                        'percentage' => 100,
                    ],
                ],
                'quantity' => 1,
                'isCalculated' => true,
                'precision' => 2,
                'listPrice' => 0,
                'type' => 'quantity',
            ],
            'payload' => [
                'isCloseout' => false,
                'isNew' => false,
                'purchasePrice' => 76.94,
                'purchasePrices' => '{"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","net":71.90654205607476,"gross":76.94,"linked":true,"listPrice":null,"extensions":[]}',
                'productNumber' => 'f5cfda12b99e401aaa6de8ec4f78d9b7',
                'manufacturerId' => 'f5ef1ab8d7ef42d8aa349fb09d1949be',
                'taxId' => 'dfdd3af489d14e3f9fe45966c43c5182',
                'categoryIds' => [
                    '5487b40cb041415e8098b2f8ea62319f',
                    '15fcfa722b454f65a8fe4e58c0041391',
                ],
                'propertyIds' => [
                    '06571c5b760842e2a3b347b4aba724aa',
                    '1b31a24c257c43a98d552ad9341a6a57',
                    '219b93998d7d430f970fbdc109dba10e',
                    '29fe930068214bdeaea1b3a70eec5894',
                    '328f9b7b70c8437a908d751d606afbb2',
                    '3e0ba09e7ae449d983e170ba122e6317',
                    '591e9562d2cc4a30a53fec23b5bcf7e1',
                    '6421ab21d3cf431db8118f04d2fad161',
                    '66f0ad0abc6a4b02b3f1d2ca5e5d4721',
                    '8765ac67373d439f82439654a88598e9',
                    '9204e42cea97415592ae632ceb1a3885',
                    'abcddad818214153a93697d891630509',
                    'b32a5d9d7f1541e89ad8554cbfe3e12c',
                    'b8168455cc194d288da89d1d77f5460e',
                    'c80449c94bba4ac8b7dc9e5284a50c81',
                    'e9fb04cc14b542439808b9dad59fc861',
                    'f0c446af250d4a5b9e6dd5ff251e739a',
                    'f76efab75d2d424a80b769aa53038835',
                ],
            ],
            'type' => 'product',
            'id' => '7213d230c0834de7961371ce160637ce',
        ],
        [
            'orderId' => 'efaf7e8752b242baa67ed795d18f785d',
            'identifier' => '575eb6fe95364285b26a5eb57d5ab24e',
            'referencedId' => '575eb6fe95364285b26a5eb57d5ab24e',
            'productId' => '575eb6fe95364285b26a5eb57d5ab24e',
            'quantity' => 5,
            'unitPrice' => 576.6355140186915,
            'totalPrice' => 2883.18,
            'label' => 'Durable Linen Prawn Ton Soup',
            'good' => true,
            'removable' => true,
            'coverId' => 'c09e7037583c4ecbadb4936d4ac02913',
            'stackable' => true,
            'position' => 2,
            'price' => [
                'unitPrice' => 576.6355140186915,
                'quantity' => 5,
                'totalPrice' => 2883.18,
                'calculatedTaxes' => [
                    [
                        'tax' => 201.82,
                        'taxRate' => 7,
                        'price' => 2883.1775700934577,
                    ],
                ],
                'taxRules' => [
                    [
                        'taxRate' => 7,
                        'percentage' => 100,
                    ],
                ],
            ],
            'priceDefinition' => [
                'price' => 576.6355140186915,
                'taxRules' => [
                    [
                        'taxRate' => 7,
                        'percentage' => 100,
                    ],
                ],
                'quantity' => 5,
                'isCalculated' => true,
                'precision' => 2,
                'type' => 'quantity',
            ],
            'payload' => [
                'isCloseout' => false,
                'isNew' => false,
                'purchasePrice' => 88.41,
                'purchasePrices' => '{"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","net":82.62616822429906,"gross":88.41,"linked":true,"listPrice":null,"extensions":[]}',
                'productNumber' => 'e4a50fc7df7e44b191daa3258712e415',
                'manufacturerId' => '3634c8f559524c85b59acadfd419a93a',
                'taxId' => 'dfdd3af489d14e3f9fe45966c43c5182',
                'categoryIds' => [
                    '03a16f032437412898c420cbc0e9c46c',
                ],
                'propertyIds' => [
                    '06d4c2777d704043aebb2305205665af',
                    '2fd0628fafdc41f390607ba807281a58',
                    '519a4e5e3aaa4de3926ad42d20fac8d8',
                    '7077be1cad214b3dbbf44413313209c8',
                    '73e20dac4478458b9448cb0994ff8803',
                    '7d3dab90cf854164bc27191fd64f43b4',
                    '912028b6b97742158512a5199969311c',
                    '99ac9e2003ca46599d3574f35f54303b',
                    'aebc48fb76a94c848419530c990dc739',
                    'b32a5d9d7f1541e89ad8554cbfe3e12c',
                    'c49a012159e349ecb74218e0590b7ded',
                    'c80449c94bba4ac8b7dc9e5284a50c81',
                    'ccd62a7c9d754633b4b13c8a429eae74',
                    'd01873317bce4f838eda107f9efbfcaa',
                    'eaf8c0a32320419586210f8e4374f446',
                    'ec056bfebdb941dbb6f4f7796c11df98',
                    'f8133e24e41b41a8919fcfffdf132cd0',
                    'f8eb11e818a24356a817549f615d5ec7',
                ],
            ],
            'type' => 'product',
            'id' => '78b191d01e904d2c89efc5838729688e',
        ],
    ],
    'transactions' => [
        [
            'orderId' => 'efaf7e8752b242baa67ed795d18f785d',
            'paymentMethodId' => '92fa111b3a81433fb22fd17d3d6804fc',
            'amount' => [
                'unitPrice' => 4376.45,
                'quantity' => 1,
                'totalPrice' => 4376.45,
                'calculatedTaxes' => [
                    [
                        'tax' => 243.14999999999998,
                        'taxRate' => 7,
                        'price' => 3473.6728971962616,
                    ],
                    [
                        'tax' => 0,
                        'taxRate' => 0,
                        'price' => 659.62,
                    ],
                ],
                'taxRules' => [
                    [
                        'taxRate' => 7,
                        'percentage' => 100,
                    ],
                    [
                        'taxRate' => 0,
                        'percentage' => 100,
                    ],
                ],
            ],
            'stateMachineState' => [
                'name' => 'Open',
                'technicalName' => 'open',
                'stateMachineId' => '67a4cd56205346909a145bebf41c31fe',
                'stateMachine' => [
                    'technicalName' => 'order_transaction.state',
                    'name' => 'Payment state',
                    'initialStateId' => '3b55f624e3b040f98b3420e79db05729',
                    'id' => '67a4cd56205346909a145bebf41c31fe',
                ],
                'id' => '3b55f624e3b040f98b3420e79db05729',
            ],
            'stateId' => '3b55f624e3b040f98b3420e79db05729',
            'id' => 'e6e10333864f49248ecab59e481fe32e',
        ],
    ],
    'deepLinkCode' => 'iBUe5S7w3a8sYxAKYboK1zk9oGnc9_bL',
    'stateMachineState' => [
        'name' => 'Open',
        'technicalName' => 'open',
        'stateMachineId' => '44c7774d8a4b4bb985a86b8162a2b4b8',
        'stateMachine' => [
            'technicalName' => 'order.state',
            'name' => 'Order state',
            'initialStateId' => '225a07ac700649848be1da88eb5cfbb2',
            'id' => '44c7774d8a4b4bb985a86b8162a2b4b8',
        ],
        'id' => '225a07ac700649848be1da88eb5cfbb2',
    ],
    'stateId' => '225a07ac700649848be1da88eb5cfbb2',
    'ruleIds' => [
        'a6cc47655efc4fa893e91d1cdea88f8b',
        'addbf17d94374bfaa2f2d2b57baa5161',
        'e3563b3f63c14b52a9b1dedfbc12c2fa',
    ],
    'id' => 'efaf7e8752b242baa67ed795d18f785d',
];
