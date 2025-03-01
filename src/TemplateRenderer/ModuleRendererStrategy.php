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
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Api\HttpErrorExceptionInterface;
use Comfino\Common\Frontend\PaywallRenderer;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\View\FrontendManager;
use Comfino\View\TemplateManager;
use ComfinoExternal\Psr\Http\Client\NetworkExceptionInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ModuleRendererStrategy implements RendererStrategyInterface
{
    /** @var \PaymentModule */
    private $module;
    /** @var bool */
    private $fullDocumentStructure;

    public function __construct(\PaymentModule $module, bool $fullDocumentStructure = false)
    {
        $this->module = $module;
        $this->fullDocumentStructure = $fullDocumentStructure;
    }

    /**
     * @param \Throwable $exception
     * @param \Comfino\Common\Frontend\FrontendRenderer $frontendRenderer
     */
    public function renderErrorTemplate($exception, $frontendRenderer): string
    {
        $showLoader = false;
        $showMessage = false;
        $userErrorMessage = $this->module->l(
            'There was a technical problem. Please try again in a moment and it should work!'
        );

        DebugLogger::logEvent(
            '[API_ERROR]',
            'renderErrorTemplate',
            [
                'exception' => get_class($exception),
                'error_message' => $exception->getMessage(),
                'error_code' => $exception->getCode(),
                'error_file' => $exception->getFile(),
                'error_line' => $exception->getLine(),
                'error_trace' => $exception->getTraceAsString(),
                '$fullDocumentStructure' => $this->fullDocumentStructure,
            ]
        );

        if ($exception instanceof HttpErrorExceptionInterface) {
            $url = $exception->getUrl();
            $requestBody = $exception->getRequestBody();

            if ($exception instanceof ResponseValidationError || $exception instanceof ServiceUnavailable) {
                $responseBody = $exception->getResponseBody();
            } else {
                if ($exception instanceof AccessDenied && $exception->getCode() === 404) {
                    $showMessage = true;
                    $userErrorMessage = $exception->getMessage();
                }

                $responseBody = '';
            }

            $templateName = 'api-error';
        } elseif ($exception instanceof NetworkExceptionInterface) {
            $exception->getRequest()->getBody()->rewind();

            /*if ($exception->getCode() === CURLE_OPERATION_TIMEDOUT) {
                $cookie = \Context::getContext()->cookie;

                if (isset($cookie->comfino_conn_attempt_idx)) {
                    $connectAttemptIdx = $cookie->comfino_conn_attempt_idx++;
                } else {
                    $connectAttemptIdx = 1;
                }

                if ($connectAttemptIdx < FrontendManager::getConnectMaxNumAttempts()) {
                    $showLoader = true;
                } elseif ($connectAttemptIdx === FrontendManager::getConnectMaxNumAttempts()) {
                    $showMessage = true;
                }

                DebugLogger::logEvent(
                    '[API_TIMEOUT]',
                    'renderErrorTemplate',
                    [
                        '$cookie->comfino_conn_attempt_idx' => $cookie->comfino_conn_attempt_idx,
                        '$connectAttemptIdx' => $connectAttemptIdx,
                        '$showLoader' => $showLoader,
                    ]
                );

                if ($cookie->comfino_conn_attempt_idx >= FrontendManager::getConnectMaxNumAttempts()) {
                    $cookie->comfino_conn_attempt_idx = 1;
                }

                $cookie->write();
            }*/

            $url = $exception->getRequest()->getRequestTarget();
            $requestBody = $exception->getRequest()->getBody()->getContents();
            $responseBody = '';
            $templateName = 'api-error';
        } else {
            $url = '';
            $requestBody = '';
            $responseBody = '';
            $templateName = 'error';
        }

        $paywallStyles = [];

        if ($this->fullDocumentStructure && $frontendRenderer instanceof PaywallRenderer) {
            $paywallStyles = FrontendManager::registerExternalStyles($frontendRenderer->getStyles());
        }

        return TemplateManager::renderModuleView(
            $this->module,
            $templateName,
            'front',
            [
                'exception_class' => get_class($exception),
                'error_message' => $exception->getMessage(),
                'error_code' => $exception->getCode(),
                'error_file' => $exception->getFile(),
                'error_line' => $exception->getLine(),
                'error_trace' => $exception->getTraceAsString(),
                'url' => $url,
                'request_body' => $requestBody,
                'response_body' => $responseBody,
                'paywall_styles' => $paywallStyles,
                'full_document_structure' => $this->fullDocumentStructure,
                'show_loader' => $showLoader,
                'show_message' => $showMessage,
                'user_error_message' => $userErrorMessage,
                'is_debug_mode' => ConfigManager::isDevEnv(),
            ]
        );
    }
}
