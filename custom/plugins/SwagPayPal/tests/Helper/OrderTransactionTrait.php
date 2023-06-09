<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\PayPal\Test\Helper;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Exception\StateMachineStateNotFoundException;
use Swag\PayPal\SwagPayPal;
use Swag\PayPal\Test\Mock\PayPal\Client\_fixtures\V2\GetCapturedOrderCapture;
use Swag\PayPal\Test\Mock\Repositories\OrderTransactionRepoMock;
use Swag\PayPal\Util\PaymentMethodUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
trait OrderTransactionTrait
{
    /**
     * @throws StateMachineStateNotFoundException
     */
    public function getTransactionId(
        Context $context,
        ContainerInterface $container,
        string $transactionStateTechnicalName = OrderTransactionStates::STATE_OPEN
    ): string {
        $stateId = $this->getOrderTransactionStateIdByTechnicalName($transactionStateTechnicalName, $container, $context);
        if (!$stateId) {
            throw new StateMachineStateNotFoundException(OrderTransactionStates::STATE_MACHINE, $transactionStateTechnicalName);
        }

        $paymentMethodId = $container->get(PaymentMethodUtil::class)->getPayPalPaymentMethodId($context);
        static::assertNotNull($paymentMethodId);

        return $this->getValidTransactionId(
            $this->getOrderData(Uuid::randomHex(), $context),
            $paymentMethodId,
            $stateId,
            $container,
            $context,
            true
        );
    }

    public function getTransaction(
        string $transactionId,
        ContainerInterface $container,
        Context $context
    ): ?OrderTransactionEntity {
        /** @var EntityRepository $orderTransactionRepo */
        $orderTransactionRepo = $container->get(OrderTransactionDefinition::ENTITY_NAME . '.repository');

        /** @var OrderTransactionEntity|null $transaction */
        $transaction = $orderTransactionRepo->search(new Criteria([$transactionId]), $context)->get($transactionId);

        return $transaction;
    }

    protected function assertOrderTransactionState(string $state, string $transactionId, Context $context): void
    {
        $container = $this->getContainer();
        $expectedStateId = $this->getOrderTransactionStateIdByTechnicalName(
            $state,
            $container,
            $context
        );

        $transaction = $this->getTransaction($transactionId, $container, $context);
        static::assertNotNull($transaction);
        static::assertNotNull($expectedStateId);
        static::assertSame($expectedStateId, $transaction->getStateId());
    }

    private function getValidTransactionId(
        array $orderData,
        string $paymentMethodId,
        string $stateId,
        ContainerInterface $container,
        Context $context,
        bool $withCustomField = false
    ): string {
        /** @var EntityRepository $orderRepo */
        $orderRepo = $container->get('order.repository');
        /** @var EntityRepository $orderTransactionRepo */
        $orderTransactionRepo = $container->get('order_transaction.repository');

        $orderId = $orderData[0]['id'];
        $orderRepo->create($orderData, $context);

        $orderTransactionId = Uuid::randomHex();
        $orderTransactionData = [
            'id' => $orderTransactionId,
            'orderId' => $orderId,
            'paymentMethodId' => $paymentMethodId,
            'amount' => [
                'quantity' => 1,
                'taxRules' => [
                    0 => [
                        'taxRate' => 19.0,
                        'extensions' => [],
                        'percentage' => 100.0,
                    ],
                    1 => [
                        'taxRate' => 7.0,
                        'extensions' => [],
                        'percentage' => 100.0,
                    ],
                ],
                'unitPrice' => 20028.0,
                'totalPrice' => 20028.0,
                'referencePrice' => null,
                'calculatedTaxes' => [
                    0 => [
                        'tax' => 2137.57,
                        'price' => 11250.420168067229,
                        'taxRate' => 19.0,
                        'extensions' => [],
                    ],
                    1 => [
                        'tax' => 434.39,
                        'price' => 6205.607476635514,
                        'taxRate' => 7.0,
                        'extensions' => [],
                    ],
                ],
            ],
            'stateId' => $stateId,
        ];

        if ($withCustomField) {
            $orderTransactionData['customFields'] = [
                SwagPayPal::ORDER_TRANSACTION_CUSTOM_FIELDS_PAYPAL_TRANSACTION_ID => OrderTransactionRepoMock::WEBHOOK_PAYMENT_ID,
                SwagPayPal::ORDER_TRANSACTION_CUSTOM_FIELDS_PAYPAL_ORDER_ID => GetCapturedOrderCapture::ID,
            ];
        }

        $orderTransactionRepo->create([$orderTransactionData], $context);

        return $orderTransactionId;
    }
}
