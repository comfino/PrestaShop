<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Widget\WidgetTypeEnum;

class GetWidgetTypes extends Base
{
    /** @var WidgetTypeEnum[]
     * @readonly */
    public $widgetTypes;
    /** @var string[]
     * @readonly */
    public $widgetTypesWithNames;

    /**
     * @inheritDoc
     * @param mixed[]|string|bool|null $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        $this->widgetTypesWithNames = $deserializedResponseBody;
        $this->widgetTypes = array_map(
            static function (string $widgetType) : WidgetTypeEnum {
                return WidgetTypeEnum::from($widgetType, false);
            },
            array_keys($deserializedResponseBody)
        );
    }
}