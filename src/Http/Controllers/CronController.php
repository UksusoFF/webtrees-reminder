<?php

namespace UksusoFF\WebtreesModules\Reminder\Http\Controllers;

use Exception;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\NoReplyUser;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\CalendarService;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\SiteUser;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use UksusoFF\WebtreesModules\Reminder\Helpers\AppHelper;
use UksusoFF\WebtreesModules\Reminder\Modules\ReminderModule;

class CronController implements RequestHandlerInterface
{
    public const ROUTE_PREFIX = 'reminder-cron';

    protected ReminderModule $module;

    protected TreeService $trees;

    protected CalendarService $events;

    protected UserService $users;

    protected EmailService $email;

    public function __construct(ReminderModule $module)
    {
        $this->module = $module;

        $this->trees = AppHelper::get(TreeService::class);
        $this->events = AppHelper::get(CalendarService::class);
        $this->users = AppHelper::get(UserService::class);
        $this->email = AppHelper::get(EmailService::class);
    }

    public function handle(Request $request): Response
    {
        try {
            switch ($request->getAttribute('action')) {
                case 'run':
                    return $this->run($request);
                default:
                    throw new HttpNotFoundException();
            }
        } catch (Exception $e) {
            Auth::logout();

            return response([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function run(Request $request): Response
    {
        if ($request->getQueryParams()['key'] !== $this->module->getSettingCronKey()) {
            throw new HttpAccessDeniedException();
        }

        $counter = 0;

        $this->users->all()->each(function(User $user) use (&$counter) {
            $reminders = [];

            if (filter_var($user->getPreference(ReminderModule::SETTING_EMAIL_NAME, 'false'), FILTER_VALIDATE_BOOLEAN)) {
                $reminders[] = 'email';
            }

            if (empty($reminders)) {
                return;
            }

            Auth::login($user);
            I18N::init($user->getPreference(User::PREF_LANGUAGE, 'en'));

            $startJd = Registry::timestampFactory()->now()->julianDay();
            $endJd = Registry::timestampFactory()->now()->julianDay();

            $this->trees->all()->each(function(Tree $tree) use ($user, $reminders, $startJd, $endJd, &$counter) {
                $facts = $this->events->getEventsList(
                    $startJd,
                    $endJd,
                    implode(',', [
                        'BIRT',
                        'MARR',
                    ]),
                    true,
                    'alpha',
                    $tree
                )->filter(static function(Fact $fact) {
                    $record = $fact->record();

                    if ($record instanceof Family) {
                        return $record->facts(Gedcom::DIVORCE_EVENTS)->isEmpty();
                    }

                    return true;
                });

                if ($facts->isNotEmpty()) {
                    $this->sendFacts($tree, $user, $facts, $reminders);
                    $counter++;
                }
            });
            Auth::logout();
        });

        return response([
            'success' => true,
            'count' => $counter,
        ]);
    }

    private function sendFacts(Tree $tree, User $user, Collection $facts, array $reminders): void
    {
        if (in_array('email', $reminders, true)) {
            $this->sendEmail($tree, $user, $facts);
        }
    }

    private function sendEmail(Tree $tree, User $user, Collection $facts): void
    {
        $subject = "{$tree->title()}: ". I18N::translate('Upcoming events');

        $html = view("{$this->module->name()}::email/facts", [
            'subject' => $subject,
            'items' => $this->groupFacts($facts),
        ]);

        $plain = str_replace(PHP_EOL, ' ', $html);
        $plain = preg_replace('/[[:blank:]]+/', ' ', $plain);
        $plain = str_replace(['<br/>', '<hr>'], [PHP_EOL, '----'], $plain);
        $plain = strip_tags($plain);

        $this->email->send(
            new SiteUser(),
            $user,
            new NoReplyUser(),
            $subject,
            $plain,
            $html
        );
    }

    private function groupFacts(Collection $facts): Collection
    {
        return $facts
            ->sortBy(static function(Fact $fact) {
                $month = strip_tags($fact->date()->display(null, '%m'));
                $day = strip_tags($fact->date()->display(null, '%d'));
                $day = !empty($day) ? $day : '01';

                $year = Carbon::createFromFormat('m d', "{$month} {$day}")->isPast()
                    ? Carbon::now()->addYear()->format('Y')
                    : Carbon::now()->format('Y');

                return $year . $month . $day;
            })
            ->groupBy(static function(Fact $fact) {
                return strip_tags($fact->date()->display(null, '%j %F'));
            });
    }
}
