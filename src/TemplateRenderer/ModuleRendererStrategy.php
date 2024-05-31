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

namespace Comfino\TemplateRenderer;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\TemplateManager;
use Psr\Http\Client\NetworkExceptionInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ModuleRendererStrategy implements RendererStrategyInterface
{
    /** @var \PaymentModule */
    private $module;

    public function __construct(\PaymentModule $module)
    {
        $this->module = $module;
    }

    public function renderPaywallTemplate($paywallContents): string
    {
        return $paywallContents;
    }

    public function renderErrorTemplate($exception): string
    {
        if ($exception instanceof RequestValidationError || $exception instanceof ResponseValidationError
            || $exception instanceof AuthorizationError || $exception instanceof AccessDenied
            || $exception instanceof ServiceUnavailable
        ) {
            $url = $exception->getUrl();
            $request_body = $exception->getRequestBody();

            if ($exception instanceof ResponseValidationError || $exception instanceof ServiceUnavailable) {
                $response_body = $exception->getResponseBody();
            } else {
                $response_body = '';
            }

            $template_name = 'api_error';
        } elseif ($exception instanceof NetworkExceptionInterface) {
            $exception->getRequest()->getBody()->rewind();

            $url = $exception->getRequest()->getRequestTarget();
            $request_body = $exception->getRequest()->getBody()->getContents();
            $response_body = '';
            $template_name = 'api_error';
        } else {
            $url = '';
            $request_body = '';
            $response_body = '';
            $template_name = 'error';
        }

        return TemplateManager::renderModuleView(
            $this->module,
            $template_name,
            'front',
            [
                'exception_class' => get_class($exception),
                'error_message' => $exception->getMessage(),
                'error_code' => $exception->getCode(),
                'error_file' => $exception->getFile(),
                'error_line' => $exception->getLine(),
                'error_trace' => $exception->getTraceAsString(),
                'url' => $url,
                'request_body' => $request_body,
                'response_body' => $response_body,
            ]
        );
    }
}
