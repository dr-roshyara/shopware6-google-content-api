<?php declare(strict_types=1);
/**
 * Shopware
 * Copyright © 2020
 *
 * @category   Shopware
 * @package    Shopimporter_Shopware6
 * @subpackage ShopimporterController.php
 *
 * @copyright  2020 Iguana-Labs GmbH
 * @author     Module Factory <info at module-factory.com>
 * @license    https://www.module-factory.com/eula
 */

namespace wawision\Shopimporter_Shopware6\Core\Framework\Wawision\Api;

use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class ShopimporterController extends AbstractController
{
    /** @var SystemConfigService */
    private $systemConfigService;

    /** @var EntityRepositoryInterface */
    private $userRepository;

    /** @var EntityRepositoryInterface */
    protected $loggerRepository;

    /** @var LoggerInterface */
    private $logger;

    /** @var Context */
    private $context;

    /**
     * Frontend constructor.
     * @param SystemConfigService $systemConfigService
     * @param EntityRepositoryInterface $userRepository
     * @param EntityRepositoryInterface $loggerRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $userRepository,
        EntityRepositoryInterface $loggerRepository,
        LoggerInterface $logger
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->userRepository = $userRepository;
        $this->loggerRepository = $loggerRepository;
        $this->logger = $logger;
        $this->context = Context::createDefaultContext();
    }

    /**
     * @Route("/api/_action/wawision/saveuser", name="api.action.saveuser", methods={"POST"})
     */
    public function saveUser(RequestDataBag $post): JsonResponse
    {
        $user = $post->get('user');
        $data = [
            'id' => $user->get('id'),
            'password' => $user->get('password')
        ];

        try {
            $this->userRepository->upsert([$data], $this->context);

            return new JsonResponse( ['valid' => true] );
        } catch (\Exception $exception) {
            return new JsonResponse([
                'valid' => false,
                'code' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @Route("/api/_action/wawision/buildurl", name="api.action.buildurl", methods={"POST"})
     */
    public function buildXentralURL(RequestDataBag $post): JsonResponse
    {
        $host = $this->getHost();

        if (!$host
            || !$post->has('module')
            || !$post->has('action')
            || !$post->has('id')
        ) {
            return new JsonResponse(['valid' => false]);
        }

        $id = $post->get('id');

        if ($post->get('action') !== 'edit') {
            $id = base64_encode($id);
        }

        $params = [
            'module' => $post->get('module'),
            'action' => $post->get('action'),
            'id' => $id
        ];

        $url = $host . '/index.php?' . http_build_query($params);

        return new JsonResponse([
            'valid' => true,
            'url' => $url
        ]);
    }

    /**
     * @Route("/api/_action/wawision/jumpwawision", name="api.action.jumpwawision", methods={"POST"})
     */
    public function jumpToXentral(Request $post): JsonResponse
    {
        $host = $this->getHost();

        if (!$host || !$post->get('shopdata')) {
            return new JsonResponse(['valid' => false]);
        }

        $shopdata = base64_encode($post->get('shopdata'));

        $params = [
            'module' => 'onlineshops',
            'action' => 'appnew',
            'shopdata' => $shopdata
        ];

        $url = $host . '/index.php?' . http_build_query($params);

        return new JsonResponse([
            'valid' => true,
            'url' => $url
        ]);
    }

    /**
     * @Route("/api/_action/wawision/getcredentials", name="api.action.getcredentials", methods={"POST"})
     */
    public function getXentralCredentials(Request $post): JsonResponse
    {
        $host = $this->getHost();

        if (!$host || !$post->get('token')) {
            return new JsonResponse(['valid' => false]);
        }

        $params = [
            'module' => 'onlineshops',
            'action' => 'getapi',
            'token' => $post->get('token'),
        ];

        $url = $host . '/index.php?' . http_build_query($params);

        $result = $this->_Call([
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => array('Accept: application/json'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $this->systemConfigService->set('WawisionShopimporter.config.apiname', $result['apiname']);
        $this->systemConfigService->set('WawisionShopimporter.config.apikey', $result['apikey']);
        $this->systemConfigService->set('WawisionShopimporter.config.shopid', $result['shopid']);

        return new JsonResponse(['valid' => true]);
    }

    /**
     * @Route("/api/_action/wawision/usecases", name="api.action.usecases", methods={"POST"})
     */
    public function getUseCases(): JsonResponse
    {
        $host = $this->getHost();

        if (!$host) {
            return new JsonResponse(['valid' => false]);
        }

        $params = [
            'module' => 'onlineshops',
            'action' => 'getapi',
            'cmd' => 'getusecases'
        ];

        $url = $host . '/index.php?' . http_build_query($params);

        $result = $this->_Call([
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => array('Accept: application/json'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        return new JsonResponse(['valid' => true, 'result' => $result]);
    }

    /**
     * @Route("/api/_action/wawision/connectapi", name="api.action.connectapi", methods={"POST"})
     */
    public function connectXentralApi(RequestDataBag $post): JsonResponse
    {
        if (!$post->has('resource')) {
            return new JsonResponse(['valid' => false]);
        }

        if ($this->getGetRequest($post->get('resource'))) {
            $options = $this->buildGetOptions($post->get('resource'), $post->get('id'));
        } else {
            $options = $this->buildPostOptions($post->get('resource'), $post->get('id'));
        }

        $result = $this->_Call($options);

        return new JsonResponse(['valid' => true, 'result' => $result]);
    }

    /**
     * baue die GET Api-Optionen für den Curl zusammen
     *
     * @param string $resource
     * @param string|null $id
     * @return array
     */
    private function buildGetOptions(string $resource, ?string $id = null): array
    {
        $credentials = $this->getConfig();

        $param = '';
        if (is_string($id)) {
            $param = '/' . base64_encode($id);
        }

        return [
            CURLOPT_URL => $credentials['host'] . '/api/shopimport/' . $resource . $param,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => [ 'Accept: application/json' ],

            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,

            CURLOPT_USERPWD => $credentials['apiname'] . ':' . $credentials['apikey'],
        ];
    }

    /**
     * baue die POST Api-Optionen für den Curl zusammen
     *
     * @param string $resource
     * @param string|null $id
     * @return array
     */
    private function buildPostOptions(string $resource, ?string $id = null): array
    {
        $credentials = $this->getConfig();

        $param = '';
        if (is_string($id)) {
            $param = '/' . base64_encode($id);
        }

        return [
            CURLOPT_URL => $credentials['host'] . '/api/shopimport/' . $resource . $param,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => [ 'Accept: application/json' ],

            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',

            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,

            CURLOPT_USERPWD => $credentials['apiname'] . ':' . $credentials['apikey'],
        ];
    }

    /**
     * Call Xentral
     *
     * @param array $options
     * @return mixed
     * @throws \Exception
     */
    private function _Call(array $options)
    {
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        $result = json_decode($response, true);

        if ($statusCode !== 200) {
            $this->writeLogs($result);
        }

        if ($ch != null) {
            curl_close($ch);
        }

        return $result;
    }

    /**
     * Schreibe Errorlogs
     *
     * @param $result
     */
    private function writeLogs($result): void
    {
        $this->loggerRepository->create(
            [
                [
                    'message' => $result['error']['http_code'] . ' ' . $result['error']['message'],
                    'level' => $result['error']['http_code'],
                    'channel' => 'xentral',
                    'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            ],
            $this->context
        );

        $this->logger->error($result['error']['http_code'] . ' ' . $result['error']['message'], $result['error']);
    }

    /**
     * @param string $resource
     * @return bool
     */
    private function getGetRequest(string $resource): bool
    {
        return $resource === 'status'
            || $resource === 'modulelinks'
            || $resource === 'statistics'
            || $resource === 'articlesyncstate';
    }

    /**
     * hole den Host aus der Konfiguration
     *
     * @return array|mixed|null
     */
    private function getHost()
    {
        $config = $this->getConfig();

        if ($config && array_key_exists('host', $config)) {
            $host = $config['host'];

            if (preg_match("/^(http[s]?\:\/\/)?((\w+)\.)?(([\w-]+)?)(\.[\w-]+){1,2}$/", $host)) {
                return $host;
            }
        }

        return false;
    }

    /**
     * hole die komplette Konfiguration
     *
     * @return array|mixed|null
     */
    private function getConfig()
    {
        return $this->systemConfigService->get('WawisionShopimporter.config');
    }
}
