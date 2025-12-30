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

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoUpdateDismissModuleFrontController extends ModuleFrontController
{
    public function postProcess(): void
    {
        parent::postProcess();

        // Only allow POST requests.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);

            exit(json_encode(['success' => false, 'message' => 'Method not allowed.']));
        }

        // Get the version to dismiss from POST data.
        $requestData = json_decode(file_get_contents('php://input'), true);

        if (!isset($requestData['version']) || !preg_match('/^\d+\.\d+\.\d+$/', $requestData['version'])) {
            http_response_code(400);

            exit(json_encode(['success' => false, 'message' => 'Missing or invalid version parameter.']));
        }

        // Store the dismissed version.
        Configuration::updateValue('COMFINO_UPDATE_NOTICE_DISMISSED', $requestData['version']);

        header('Content-Type: application/json');

        exit(json_encode(['success' => true, 'message' => 'Notice dismissed']));
    }
}
