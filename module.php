<?php

namespace UksusoFF\WebtreesModules\TodayEventsMessage;

use Composer\Autoload\ClassLoader;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Functions\FunctionsDb;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Mail;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Theme;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\User;

class TodayEventsMessage extends AbstractModule implements ModuleConfigInterface
{
    const CUSTOM_VERSION = '1.0';
    const CUSTOM_WEBSITE = 'https://github.com/UksusoFF/webtrees-todays_events_message';

    var $directory;

    public function __construct()
    {
        parent::__construct('todays_events_message');

        $this->directory = WT_MODULES_DIR . $this->getName();

        // register the namespaces
        $loader = new ClassLoader();
        $loader->addPsr4('UksusoFF\\WebtreesModules\\TodayEventsMessage\\', $this->directory);
        $loader->register();
    }

    /* ****************************
     * Module configuration
     * ****************************/

    /** {@inheritdoc} */
    public function getName()
    {
        // warning: Must match (case-sensitive!) the directory name!
        return 'todays_events_message';
    }

    /** {@inheritdoc} */
    public function getTitle()
    {
        return 'Today Events Message';
    }

    /** {@inheritdoc} */
    public function getDescription()
    {
        return 'This module send message with list of the anniversaries that occur today.';
    }

    /** {@inheritdoc} */
    public function modAction($modAction)
    {
        if (WT_TIMESTAMP - $this->getSetting('TEM_LAST_EMAIL') > (60 * 60 * 24)) {
            $this->setSetting('TEM_LAST_EMAIL', WT_TIMESTAMP);

            foreach (User::all() as $user) {
                Auth::login($user);
                foreach (Tree::getAll() as $tree) {
                    if (!Auth::isMember($tree, $user)) {
                        continue;
                    }
                    $events = [];
                    foreach (FunctionsDb::getAnniversaryEvents(WT_CLIENT_JD, 'BIRT MARR', $tree) as $fact) {
                        $record = $fact->getParent();
                        if ($record instanceof Individual && $record->isDead()) {
                            continue;
                        }
                        if ($record instanceof Family) {
                            $husb = $record->getHusband();
                            if (is_null($husb) || $husb->isDead()) {
                                continue;
                            }
                            $wife = $record->getWife();
                            if (is_null($wife) || $wife->isDead()) {
                                continue;
                            }
                        }
                        $events[] = $fact;
                    }

                    if (!empty($events)) {
                        $html = '';
                        $html .= '<table>';
                        $html .= '<thead><tr>';
                        $html .= '<th>' . I18N::translate('Record') . '</th>';
                        $html .= '<th>' . GedcomTag::getLabel('DATE') . '</th>';
                        $html .= '<th>' . I18N::translate('Anniversary') . '</th>';
                        $html .= '<th>' . GedcomTag::getLabel('EVEN') . '</th>';
                        $html .= '</tr></thead><tbody>';

                        foreach ($events as $n => $fact) {
                            $record = $fact->getParent();
                            $html .= '<tr>';
                            $html .= '<td><a href="' . $record->getAbsoluteLinkUrl() . '">' . $record->getFullName() . '</a></td>';
                            $html .= '<td>' . $fact->getDate()->display() . '</td>';
                            $html .= '<td>';
                            $html .= ($fact->anniv ? I18N::number($fact->anniv) : '');
                            $html .= '</td>';
                            $html .= '<td>' . $fact->getLabel() . '</td>';
                            $html .= '</tr>';
                        }

                        $html .= '</tbody></table>';

                        if ($user->getPreference('contactmethod') !== 'none') {
                            I18N::init($user->getPreference('language'));
                            Mail::systemMessage($tree, $user, I18N::translate('On this day'), $html);
                            I18N::init(WT_LOCALE);
                        }
                    }
                }
                Auth::logout();
            }
        }
    }

    /** {@inheritdoc} */
    public function getConfigLink()
    {
        return '#';
    }
}

return new TodayEventsMessage();