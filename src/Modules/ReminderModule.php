<?php

namespace UksusoFF\WebtreesModules\Reminder\Modules;

use Aura\Router\RouterContainer;
use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\View;
use Illuminate\Support\Str;
use UksusoFF\WebtreesModules\Reminder\Helpers\DatabaseHelper;
use UksusoFF\WebtreesModules\Reminder\Http\Controllers\AdminController;
use UksusoFF\WebtreesModules\Reminder\Http\Controllers\CronController;

class ReminderModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;

    public const CUSTOM_VERSION = '2.0.4';

    public const CUSTOM_WEBSITE = 'https://github.com/UksusoFF/webtrees-reminder';

    public const SETTING_CRON_KEY_NAME = 'REMINDER_CRON_KEY';

    public const SETTING_EMAIL_NAME = 'REMINDER_EMAIL';

    public const SETTING_SLACK_NAME = 'REMINDER_SLACK';

    public $query;

    /** @var \Fisharebest\Webtrees\Services\UserService */
    public $users;

    public function __construct()
    {
        $this->query = new DatabaseHelper();
        $this->users = app(UserService::class);
    }

    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        $router = app(RouterContainer::class);
        assert($router instanceof RouterContainer);

        $map = $router->getMap();

        $map
            ->get(
                AdminController::ROUTE_PREFIX,
                '/admin/' . AdminController::ROUTE_PREFIX . '/{action}',
                new AdminController($this)
            )
            ->allows(RequestMethodInterface::METHOD_POST);

        $map
            ->get(
                CronController::ROUTE_PREFIX,
                '/' . CronController::ROUTE_PREFIX . '/{action}',
                new CronController($this)
            )
            ->allows(RequestMethodInterface::METHOD_POST);
    }

    public function title(): string
    {
        return 'Reminder';
    }

    public function description(): string
    {
        return 'Daily e-mail & Slack digest with list of the anniversaries.';
    }

    public function customModuleAuthorName(): string
    {
        return 'UksusoFF';
    }

    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_WEBSITE;
    }

    public function resourcesFolder(): string
    {
        return __DIR__ . '/../../resources/';
    }

    public function getSettingCronKey(): string
    {
        $key = $this->getPreference(self::SETTING_CRON_KEY_NAME);

        if (empty($key)) {
            $key = Str::random();
            $this->setPreference(self::SETTING_CRON_KEY_NAME, $key);
        }

        return $key;
    }

    public function getSettingUserReminder(int $id, string $type): string
    {
        $user = $this->users->find($id);

        if ($user === null) {
            throw new HttpNotFoundException();
        }

        return $user->getPreference($type);
    }

    public function setSettingUserReminder(int $id, string $type, string $value): string
    {
        $user = $this->users->find($id);

        if ($user === null) {
            throw new HttpNotFoundException();
        }

        $user->setPreference($type, $value);

        return $value;
    }

    public function getConfigLink(): string
    {
        return route(AdminController::ROUTE_PREFIX, [
            'action' => 'config',
        ]);
    }
}
