<?php

namespace Comfino\Form\Admin\Sell\Order;

use PrestaShop\PrestaShop\Core\Form\ConfigurableFormChoiceProviderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class UpdateOrderStatusType extends AbstractType
{
    /**
     * @var ConfigurableFormChoiceProviderInterface
     */
    private $statusChoiceProvider;

    /**
     * @var array
     */
    private $statusChoiceAttributes;

    /**
     * @param ConfigurableFormChoiceProviderInterface $statusChoiceProvider
     * @param array $statusChoiceAttributes
     */
    public function __construct(
        ConfigurableFormChoiceProviderInterface $statusChoiceProvider,
        array $statusChoiceAttributes
    ) {
        $this->statusChoiceProvider = $statusChoiceProvider;
        $this->statusChoiceAttributes = $statusChoiceAttributes;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choiceProviderParams = [];

        if (!empty($options['data']['new_order_status_id'])) {
            $choiceProviderParams['current_state'] = $options['data']['new_order_status_id'];
        }
        if (!empty($options['data']['payment_method'])) {
            $choiceProviderParams['payment_method'] = $options['data']['payment_method'];
        }

        $builder
            ->add('new_order_status_id', ChoiceType::class, [
                'required' => false,
                'placeholder' => false,
                'choices' => $this->statusChoiceProvider->getChoices($choiceProviderParams),
                'choice_attr' => $this->statusChoiceAttributes,
                'translation_domain' => false,
            ])
        ;
    }
}
