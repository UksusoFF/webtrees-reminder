<?php

namespace UksusoFF\WebtreesModules\Reminder\Modules;

use Aura\Router\Route;
use Aura\Router\RouterContainer;
use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\AccountUpdate;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\User;
use Fisharebest\Webtrees\View;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UksusoFF\WebtreesModules\Reminder\Helpers\DatabaseHelper;
use UksusoFF\WebtreesModules\Reminder\Http\Controllers\AdminController;
use UksusoFF\WebtreesModules\Reminder\Http\Controllers\CronController;

class ReminderModule extends AbstractModule implements ModuleCustomInterface, ModuleGlobalInterface, ModuleConfigInterface, MiddlewareInterface
{
    use ModuleCustomTrait;
    use ModuleGlobalTrait;
    use ModuleConfigTrait;

    public const CUSTOM_VERSION = '2.0.5';

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

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute('route');

        if (!empty($route) && $route instanceof Route && $route->name === AccountUpdate::class) {
            $user = $request->getAttribute('user');
            assert($user instanceof User);

            $this->setSettingUserReminder(
                $user->id(),
                self::SETTING_EMAIL_NAME,
                (string)(($request->getParsedBody()['reminder-email'] ?? '0') === '1')
            );
        }

        return $handler->handle($request);
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

    public function customTranslations(string $language): array
    {
        $file = $this->resourcesFolder() . "langs/{$language}.php";

        return file_exists($file)
            ? require $file
            : require $this->resourcesFolder() . 'langs/en.php';
    }

    public function resourcesFolder(): string
    {
        return __DIR__ . '/../../resources/';
    }

    public function bodyContent(): string
    {
        /** @var \Psr\Http\Message\ServerRequestInterface $request */
        $request = app(ServerRequestInterface::class);

        $tree = $request->getAttribute('tree');
        $user = $request->getAttribute('user');

        return $tree instanceof Tree
            ? view("{$this->name()}::script", [
                'module' => $this->name(),
                'settings' => [
                    'email' => $user instanceof User
                        ? $this->getSettingUserReminder($user->id(), self::SETTING_EMAIL_NAME)
                        : false,
                ],
                'scripts' => [
                    $this->assetUrl('build/module.min.js'),
                ],
            ])
            : '';
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

    public function getSettingUserReminder(int $id, string $type): bool
    {
        $user = $this->users->find($id);

        if ($user === null) {
            throw new HttpNotFoundException();
        }

        return filter_var($user->getPreference($type), FILTER_VALIDATE_BOOLEAN);
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
