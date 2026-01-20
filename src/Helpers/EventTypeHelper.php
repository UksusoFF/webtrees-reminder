<?php

declare(strict_types=1);

namespace UksusoFF\WebtreesModules\Reminder\Helpers;

use Fisharebest\Webtrees\Module\OnThisDayModule;
use Fisharebest\Webtrees\Registry;
use ReflectionClass;

class EventTypeHelper
{
    protected static function getAllEvents(): array
    {
        return (new ReflectionClass(OnThisDayModule::class))->getConstant('ALL_EVENTS');
    }

    public static function getDefaultEvents(): array
    {
        return [
            'BIRT',
            'MARR',
        ];
    }

    public static function getEventOptions(): array
    {
        $events = self::getAllEvents();

        foreach ($events as $event => $tag) {
            $events[$event] = Registry::elementFactory()->make($tag)->label();
        }

        return $events;
    }
}
