$(document).ready(function() {
    var $page = $('#reminder-admin-config'),
        $table = $page.find('#reminder-admin-config-table');

    var path = window.WT_REMINDER.routes.cron;

    $page.find('[data-target="#reminder-admin-config-cron-content"]').on('click', function() {
        //TODO: Save state.
        var $this = $(this);

        if ($($this.data('target')).hasClass('show')) {
            $this.text('Show');
        } else {
            $this.text('Done');
        }
    });

    $('#reminder-admin-config-cron-text').jqCron({
        numeric_zero_pad: true,
        enabled_minute: false,
        enabled_hour: false,
        enabled_day: true,
        enabled_week: false,
        enabled_month: false,
        enabled_year: false,
        multiple_dom: true,
        multiple_month: true,
        multiple_mins: true,
        multiple_dow: true,
        multiple_time_hours: false,
        multiple_time_minutes: false,
        default_period: 'day',
        default_value: '0 9 * * *',
        bind_to: $('#reminder-admin-config-cron-input'),
        bind_method: {
            set: function($element, value) {
                $element.val(`${value} wget -O - -q "${path}"`);
            }
        },
        no_reset_button: false,
        lang: 'en'
    });

    $table.dataTable({
        processing: true,
        serverSide: true,
        ajax: $table.data('url'),
        autoWidth: false,
        filter: false,
        pageLength: 10,
        pagingType: 'full_numbers',
        stateSave: true,
        cookieDuration: 300,
        sort: false,
        columns: [
            {
                className: 'reminder-td-id'
            },
            {
                className: 'reminder-td-name'
            },
            {
                className: 'reminder-td-email'
            },
            {
                className: 'reminder-td-reminders text-right'
            }
        ],
        fnDrawCallback: function() {
            $table.find('[data-action="email"]').on('change', function() {
                var $this = $(this);

                $.ajax({
                    url: $this.data('url'),
                    data: {
                        'value': $this.is(':checked'),
                    }
                }).done(function(response) {
                    //$table.DataTable().ajax.reload();
                });
            });
        }
    });
});