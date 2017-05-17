# Today Events Message for webtrees

[![Latest Release](https://img.shields.io/github/release/UksusoFF/webtrees-todays_events_message.svg)](https://github.com/UksusoFF/webtrees-todays_events_message/releases/latest)

This module send message with list of the anniversaries that occur today.

Tested with 1.7.9 version.

## Installation
1. Download the [latest release](https://github.com/UksusoFF/webtrees-todays_events_message/releases/latest).
2. Upload the downloaded file to your webserver.
3. Unzip the package into your `webtrees/modules_v3` directory.
4. Rename the folder to `todays_events_message`.
5. Go to the control panel (admin section) => Module administration => Enable the `Today Events Message` module and save your settings.
6. [Config cron task](https://www.google.ru/search?ie=UTF-8&hl=ru&q=how%20to%20config%20cron%20task&gws_rd=ssl) for execute module action url: http://YOUR_WEBTREES_URL/module.php?mod=todays_events_message for eg:
```
0 0 * * *       wget -O - -q http://YOUR_WEBTREES_URL/module.php?mod=todays_events_message
```

## Todo
* Admin interface with settings
