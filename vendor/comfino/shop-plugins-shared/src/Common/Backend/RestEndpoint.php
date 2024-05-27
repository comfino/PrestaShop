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

namespace Comfino\Common\Backend;

use Psr\Http\Message\ServerRequestInterface;

abstract class RestEndpoint implements RestEndpointInterface
{
    /**
     * @readonly
     * @var string
     */
    private $name;
    /**
     * @readonly
     * @var string
     */
    private $endpointUrl;
    /**
     * @var mixed[]
     */
    protected $methods;

    public function __construct(string $name, string $endpointUrl)
    {
        $this->name = $name;
        $this->endpointUrl = $endpointUrl;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getEndpointUrl(): string
    {
        return $this->endpointUrl;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $serverRequest
     */
    protected function endpointPathMatch($serverRequest): bool
    {
        return $serverRequest->getUri()->getPath() === parse_url($this->endpointUrl, PHP_URL_PATH) &&
            in_array($serverRequest->getMethod(), $this->methods, true);
    }
}
