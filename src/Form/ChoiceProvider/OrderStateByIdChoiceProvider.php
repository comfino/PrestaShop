<?php

namespace Comfino\Form\ChoiceProvider;

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
        $orderStates = $this->orderStateDataProvider->getOrderStates($this->languageId);
        $choices = [];
        $paymentMethod = $options['payment_method'] ?? '';
        $orderStatesMap = array_combine(array_map(function ($itemValue) { return $itemValue['id_order_state']; }, $orderStates), $orderStates);

        foreach ($orderStates as $orderState) {
            if ($paymentMethod === 'Comfino payments' && $orderState['name'] === 'Canceled' && !empty($options['current_state']) && $orderStatesMap[$options['current_state']]['paid'] == 1) {
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
