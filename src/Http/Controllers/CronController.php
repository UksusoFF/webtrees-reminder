<?php

namespace UksusoFF\WebtreesModules\Reminder\Http\Controllers;

use Exception;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Carbon;
use Fisharebest\Webtrees\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\NoReplyUser;
use Fisharebest\Webtrees\Services\CalendarService;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\SiteUser;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\User;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use UksusoFF\WebtreesModules\Reminder\Modules\ReminderModule;

class CronController implements RequestHandlerInterface
{
    public const ROUTE_PREFIX = 'reminder-cron';

    protected $module;

    /** @var \Fisharebest\Webtrees\Services\TreeService */
    protected $trees;

    /** @var \Fisharebest\Webtrees\Services\CalendarService */
    protected $events;

    /** @var \Fisharebest\Webtrees\Services\UserService */
    protected $users;

    /** @var \Fisharebest\Webtrees\Services\EmailService */
    protected $email;

    public function __construct(ReminderModule $module)
    {
        $this->module = $module;

        $this->trees = app(TreeService::class);
        $this->events = app(CalendarService::class);
        $this->users = app(UserService::class);
        $this->email = app(EmailService::class);
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

        $this->users->all()->each(function(User $user) {
            $reminders = [];

            if (filter_var($user->getPreference(ReminderModule::SETTING_EMAIL_NAME, 'false'), FILTER_VALIDATE_BOOLEAN)) {
                $reminders[] = 'email';
            }

            if (empty($reminders)) {
                return;
            }

            Auth::login($user);
            I18N::init($user->getPreference(User::PREF_LANGUAGE, 'en'));

            $this->trees->all()->each(function(Tree $tree) use ($user, $reminders) {
                /** @var \Illuminate\Support\Collection<Fact>|array $facts */
                $facts = $this->events->getEventsList(
                    Carbon::now()->julianDay(),
                    Carbon::now()->julianDay(),
                    implode(',', [
                        'BIRT',
                        'MARR',
                    ]),
                    true,
                    'alpha',
                    $tree
                );

                if ((is_array($facts) && !empty($facts)) || $facts->isNotEmpty()) {
                    $this->sendFacts($tree, $user, collect($facts), $reminders);
                }
            });
            Auth::logout();
        });

        return response([
            'success' => true,
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

        $this->email->send(
            new SiteUser(),
            $user,
            new NoReplyUser(),
            $subject,
            strip_tags($html),
            $html
        );
    }

    private function groupFacts(Collection $facts): Collection
    {
        return $facts
            ->sortBy(static function(Fact $fact) {
                $month = strip_tags($fact->date()->display(false, '%m'));
                $day = strip_tags($fact->date()->display(false, '%d'));
                $day = !empty($day) ? $day : '01';

                $year = Carbon::createFromFormat('m d', "{$month} {$day}")->isPast()
                    ? Carbon::now()->addYear()->format('Y')
                    : Carbon::now()->format('Y');

                return $year . $month . $day;
            })->groupBy(static function(Fact $fact) {
                return strip_tags($fact->date()->display(false, '%j %F'));
            });
    }
}
