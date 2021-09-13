<?php

namespace Comfino\Form\ChoiceProvider;

use OrdersList;
use PrestaShop\PrestaShop\Core\Form\ConfigurableFormChoiceProviderInterface;
use PrestaShop\PrestaShop\Core\Form\FormChoiceAttributeProviderInterface;
use PrestaShop\PrestaShop\Core\Form\FormChoiceProviderInterface;
use PrestaShop\PrestaShop\Core\Order\OrderStateDataProviderInterface;
use PrestaShop\PrestaShop\Core\Util\ColorBrightnessCalculator;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class OrderStateByIdChoiceProvider provides order state choices with ID values.
 */
final class OrderStateByIdChoiceProvider implements FormChoiceProviderInterface, FormChoiceAttributeProviderInterface, ConfigurableFormChoiceProviderInterface
{
    /**
     * @var int language ID
     */
    private $languageId;

    /**
     * @var OrderStateDataProviderInterface
     */
    private $orderStateDataProvider;

    /**
     * @var ColorBrightnessCalculator
     */
    private $colorBrightnessCalculator;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @param int $languageId language ID
     * @param OrderStateDataProviderInterface $orderStateDataProvider
     * @param ColorBrightnessCalculator $colorBrightnessCalculator
     * @param TranslatorInterface $translator
     */
    public function __construct(
        $languageId,
        OrderStateDataProviderInterface $orderStateDataProvider,
        ColorBrightnessCalculator $colorBrightnessCalculator,
        TranslatorInterface $translator
    ) {
        $this->languageId = $languageId;
        $this->orderStateDataProvider = $orderStateDataProvider;
        $this->colorBrightnessCalculator = $colorBrightnessCalculator;
        $this->translator = $translator;
    }

    /**
     * Get order state choices.
     *
     * @param array $options
     *
     * @return array
     */
    public function getChoices(array $options = [])
    {
        require_once __DIR__.'/../../../models/OrdersList.php';

        $orderStates = $this->orderStateDataProvider->getOrderStates($this->languageId);
        $choices = [];
        $paymentMethod = $options['payment_method'] ?? '';
        $orderStatesMap = array_combine(array_map(function ($itemValue) { return $itemValue['id_order_state']; }, $orderStates), $orderStates);
        $comfinoConfirmStates = [
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_WAITING_FOR_PAYMENT],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_ACCEPTED],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_PAID]
        ];
        $comfinoStates = [
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_CREATED],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_WAITING_FOR_FILLING],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_WAITING_FOR_CONFIRMATION],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_WAITING_FOR_PAYMENT],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_ACCEPTED],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_PAID],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_REJECTED],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_CANCELLED_BY_SHOP],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_CANCELLED]
        ];

        foreach ($orderStates as $orderState) {
            if (in_array($orderState['name'], $comfinoStates, true)) {
                continue;
            }
            if ($paymentMethod === 'Comfino payments' && $orderState['name'] === 'Canceled' && !empty($options['current_state']) &&
                ($orderStatesMap[$options['current_state']]['paid'] == 1 || in_array($orderState['name'], $comfinoConfirmStates, true))
            ) {
                continue;
            }
            if ($orderState['deleted'] == 1 && (empty($options['current_state']) || $options['current_state'] != $orderState['id_order_state'])) {
                continue;
            }

            $orderState['name'] .= $orderState['deleted'] == 1 ? ' ' . $this->translator->trans('(deleted)', [], 'Admin.Global') : '';
            $choices[$orderState['name']] = $orderState['id_order_state'];
        }

        return $choices;
    }

    /**
     * Get order state choices attributes.
     *
     * @return array
     */
    public function getChoicesAttributes()
    {
        $orderStates = $this->orderStateDataProvider->getOrderStates($this->languageId);
        $attrs = [];

        foreach ($orderStates as $orderState) {
            $orderState['name'] .= $orderState['deleted'] == 1 ? ' ' . $this->translator->trans('(deleted)', [], 'Admin.Global') : '';
            $attrs[$orderState['name']]['data-background-color'] = $orderState['color'];
            $attrs[$orderState['name']]['data-is-bright'] = $this->colorBrightnessCalculator->isBright($orderState['color']);
        }

        return $attrs;
    }
}
