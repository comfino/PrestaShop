<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Widget\WidgetTypeEnum;

class GetWidgetTypes extends Base
{
    /** @var WidgetTypeEnum[] */
    public readonly array $widgetTypes;
    /** @var string[] */
    public readonly array $widgetTypesWithNames;

    /**
     * @inheritDoc
     */
    protected function processResponseBody(array|string|bool|null $deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        $this->widgetTypesWithNames = $deserializedResponseBody;
        $this->widgetTypes = array_map(
            static fn (string $widgetType): WidgetTypeEnum => WidgetTypeEnum::from($widgetType, false),
            array_keys($deserializedResponseBody)
        );
    }
}
