<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Comfino;

use Comfino\Api\Client;
use Comfino\Extended\Api\Dto\Plugin\ShopPluginError;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Http\Message\RequestMatcher\RequestMatcher;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sunrise\Http\Factory\RequestFactory;
use Sunrise\Http\Factory\ResponseFactory;
use Sunrise\Http\Factory\StreamFactory;

trait ExtendedClientTestTrait
{
    use ReflectionTrait;

    /**
     * @var string
     */
    protected $productionApiHost;

    public function testSendLoggedError(): void
    {
        $shopPluginError = new ShopPluginError(
            'host',
            'platform',
            ['attrib1' => 'value1', 'attrib2' => 'value2'],
            '123EF',
            'Error message.'
        );
        $hashKey = 'Comfino API client 1.0';
        $errorDetails = gzcompress('{"host":"host","platform":"platform","environment":{"attrib1":"value1","attrib2":"value2"},"error_code":"123EF","error_message":"Error message.","api_request_url":null,"api_request":null,"api_response":null,"stack_trace":null}', 9);

        $request = [
            'error_details' => base64_encode($errorDetails),
            'hash' => hash_hmac('sha256', $errorDetails, $hashKey),
        ];

        $apiClient = $this->initApiClient('/v1/log-plugin-error', 'POST', null, (new JsonSerializer())->serialize($request), null, 'API-KEY');
        $status = $apiClient->sendLoggedError($shopPluginError);

        $this->assertTrue($status);
    }

    protected function setUp(): void
    {
        $this->productionApiHost = parse_url($this->getConstantFromClass(Client::class, 'PRODUCTION_HOST'), PHP_URL_HOST);
    }

    private function initApiClient(string $endpointPath, string $method, ?array $queryParameters = null, ?string $requestBody = null, $responseData = null, ?string $apiKey = null, bool $isPublicEndpoint = false, int $responseStatus = 200): Extended\Api\Client
    {
        $client = new \Http\Mock\Client();
        $client->on(
            new RequestMatcher($endpointPath, $this->productionApiHost, $method, 'https'),
            function (RequestInterface $request) use ($queryParameters, $requestBody, $responseData, $apiKey, $isPublicEndpoint, $responseStatus) {
                return $this->processRequest($request, $queryParameters, $requestBody, $responseData, $apiKey, $isPublicEndpoint, $responseStatus);
            }
        );

        return new Extended\Api\Client(new RequestFactory(), new StreamFactory(), $client, $apiKey);
    }

    private function processRequest(RequestInterface $request, ?array $queryParameters = null, ?string $requestBody = null, $responseData = null, ?string $apiKey = null, bool $isPublicEndpoint = false, int $responseStatus = 200): ResponseInterface
    {
        if (!$isPublicEndpoint && (!$request->hasHeader('Api-Key') || $request->getHeaderLine('Api-Key') !== $apiKey)) {
            return (new ResponseFactory())->createJsonResponse(401, ['message' => 'Invalid credentials.']);
        }

        if ($requestBody !== null) {
            $this->assertEquals($requestBody, $request->getBody()->getContents(), 'Request body is invalid.');
        } else {
            $this->assertEquals('', $request->getBody()->getContents(), 'Request body is invalid.');
        }

        if (is_array($queryParameters) && count($queryParameters)) {
            $this->assertEquals(http_build_query($queryParameters), $request->getUri()->getQuery(), 'Request URL query string is invalid.');
        } else {
            $this->assertEquals('', $request->getUri()->getQuery(), 'Request URL query string is invalid.');
        }

        return (new ResponseFactory())->createJsonResponse($responseStatus, $responseData);
    }
}
