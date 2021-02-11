<?php

namespace UksusoFF\WebtreesModules\Reminder\Http\Controllers;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\Controllers\Admin\AbstractAdminController;
use Fisharebest\Webtrees\Http\RequestHandlers\ControlPanel;
use Fisharebest\Webtrees\I18N;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use UksusoFF\WebtreesModules\Reminder\Modules\ReminderModule;

class AdminController extends AbstractAdminController implements RequestHandlerInterface
{
    public const ROUTE_PREFIX = 'reminder-admin';

    protected $module;

    public function __construct(ReminderModule $module)
    {
        $this->module = $module;
    }

    public function handle(ServerRequestInterface $request): Response
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }

        switch ($request->getAttribute('action')) {
            case 'config':
                return $this->config();
            case 'data':
                return $this->data($request);
            case 'update':
                return $this->update($request);
            default:
                throw new HttpNotFoundException();
        }
    }

    private function config(): Response
    {
        return $this->viewResponse($this->module->name() . '::admin/config', [
            'title' => $this->module->title(),
            'tree' => null,
            'breadcrumbs' => [
                route(ControlPanel::class) => I18N::translate('Control panel'),
                $this->module->getConfigLink() => $this->module->title(),
            ],
            'routes' => [
                // Crontab can't work with percents. So we replace this.
                'cron' => str_replace('%2F', '/', route(CronController::ROUTE_PREFIX, [
                    'action' => 'run',
                    'key' => $this->module->getSettingCronKey(),
                ])),
                'data' => route(self::ROUTE_PREFIX, [
                    'action' => 'data',
                ]),
            ],
            'styles' => [
                $this->module->assetUrl('build/admin.min.css'),
            ],
            'scripts' => [
                $this->module->assetUrl('build/vendor.min.js'),
                $this->module->assetUrl('build/admin.min.js'),
            ],
        ]);
    }

    private function data(Request $request): Response
    {
        [$rows, $total] = $this->module->query->getUserList(
            $request->getQueryParams()['start'] ?? 0,
            $request->getQueryParams()['length'] ?? 10
        );

        return response([
            'draw' => $request->getQueryParams()['draw'] ?? '1',
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $rows->map(function($row) {
                return [
                    $row->user_id,
                    $row->real_name,
                    $row->email,
                    view($this->module->name() . '::admin/parts/reminder_email', [
                        'id' => $row->user_id,
                        'url' => route(AdminController::ROUTE_PREFIX, [
                            'action' => 'update',
                            'type' => 'email',
                            'id' => $row->user_id,
                        ]),
                        'checked' => $this->module->getSettingUserReminder($row->user_id, ReminderModule::SETTING_EMAIL_NAME),
                    ]) /* TODO: Not implemented . view($this->module->name() . '::admin/parts/reminder_slack', [
                        'id' => $row->user_id,
                        'url' => '#',
                        'checked' => true,
                    ])*/,
                ];
            }),
        ]);
    }

    private function update(Request $request): Response
    {
        switch ($request->getQueryParams()['type']) {
            case 'email':
                $this->module->setSettingUserReminder(
                    (int)$request->getQueryParams()['id'],
                    ReminderModule::SETTING_EMAIL_NAME,
                    (string)$request->getQueryParams()['value']
                );

                return response([
                    'success' => true,
                ]);
            default:
                throw new HttpNotFoundException();
        }
    }
}
