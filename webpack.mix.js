const mix = require('laravel-mix');

mix.sass('resources/styles/admin/config.scss', 'resources/build/admin.min.css').options({
    postCss: [
        require('autoprefixer')(),
    ]
});

mix.scripts([
    'node_modules/jqcron/src/jqCron.js',
    'node_modules/jqcron/src/jqCron.en.js',
], 'resources/build/vendor.min.js');

mix.babel([
    'resources/scripts/module.js',
], 'resources/build/module.min.js');

mix.babel([
    'resources/scripts/admin/config.js',
], 'resources/build/admin.min.js');