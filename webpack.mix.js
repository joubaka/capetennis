const { EnvironmentPlugin } = require('webpack');
const mix = require('laravel-mix');
const glob = require('glob');
const path = require('path');

/*
 |--------------------------------------------------------------------------
 | Global Mix config (CRITICAL)
 |--------------------------------------------------------------------------
 */

mix
  .setPublicPath('public')
  .setResourceRoot('/ct/public/');

/*
 |--------------------------------------------------------------------------
 | Mix options
 |--------------------------------------------------------------------------
 */

mix.options({
  processCssUrls: false,
  postCss: [require('autoprefixer')]
});

/*
 |--------------------------------------------------------------------------
 | Webpack configuration
 |--------------------------------------------------------------------------
 */

mix.webpackConfig({
  output: {
    libraryTarget: 'umd'
  },
  plugins: [
    new EnvironmentPlugin({
      BASE_URL: '/ct/public/'
    })
  ],
  module: {
    rules: [
      {
        test: /\.es6$|\.js$/,
        include: [
          path.join(__dirname, 'node_modules/bootstrap/'),
          path.join(__dirname, 'node_modules/popper.js/'),
          path.join(__dirname, 'node_modules/shepherd.js/')
        ],
        loader: 'babel-loader',
        options: {
          presets: [
            ['@babel/preset-env', { targets: 'last 2 versions, ie >= 10' }]
          ],
          plugins: [
            '@babel/plugin-transform-destructuring',
            '@babel/plugin-proposal-object-rest-spread',
            '@babel/plugin-transform-template-literals'
          ],
          babelrc: false
        }
      }
    ]
  },
  externals: {
    jquery: 'jQuery',
    moment: 'moment',
    jsdom: 'jsdom',
    velocity: 'Velocity',
    hammer: 'Hammer',
    pace: '"pace-progress"',
    chartist: 'Chartist',
    'popper.js': 'Popper',

    './blueimp-helper': 'jQuery',
    './blueimp-gallery': 'blueimpGallery',
    './blueimp-gallery-video': 'blueimpGallery'
  },
  stats: {
    children: true
  }
});

/*
 |--------------------------------------------------------------------------
 | Helper for vendor assets
 |--------------------------------------------------------------------------
 */

function mixAssetsDir(query, cb) {
  (glob.sync('resources/assets/' + query) || []).forEach(f => {
    f = f.replace(/[\\\/]+/g, '/');
    cb(f, f.replace('resources/assets/', 'public/assets/'));
  });
}

/*
 |--------------------------------------------------------------------------
 | SASS / CSS
 |--------------------------------------------------------------------------
 */

const sassOptions = { precision: 5 };

// Core styles
mixAssetsDir('vendor/scss/**/!(_)*.scss', (src, dest) =>
  mix.sass(
    src,
    dest
      .replace(/(\\|\/)scss(\\|\/)/, '$1css$2')
      .replace(/\.scss$/, '.css'),
    { sassOptions }
  )
);

// Vendor CSS
mixAssetsDir('vendor/libs/**/!(_)*.scss', (src, dest) =>
  mix.sass(src, dest.replace(/\.scss$/, '.css'), { sassOptions })
);

// App CSS
mixAssetsDir('css/**/*.css', (src, dest) => mix.copy(src, dest));

/*
 |--------------------------------------------------------------------------
 | JavaScript
 |--------------------------------------------------------------------------
 */

// Vendor JS
mixAssetsDir('vendor/js/**/*.js', (src, dest) => mix.js(src, dest));
mixAssetsDir('vendor/libs/**/*.js', (src, dest) => mix.js(src, dest));

// Application JS
mix.js('resources/js/laravel-user-management.js', 'public/js');
mix.js('resources/js/app.js', 'public/js');
mix.js('resources/js/pages/event-show.js', 'public/js');
mix.js('resources/js/pages/home.js', 'public/js');
mix.js('resources/js/pages/adminShow.js', 'public/js');
mix.js('resources/js/pages/regions.js', 'public/js');
mix.js('resources/js/pages/players.js', 'public/js');
mix.js('resources/js/pages/playerOrder.js', 'public/js');
mix.js('resources/js/pages/eventCategories.js', 'public/js');
mix.js('resources/js/pages/seriesEvents.js', 'public/js');
mix.js('resources/js/pages/eventEdit.js', 'public/js');
mix.js('resources/js/pages/categories.js', 'public/js');
mix.js('resources/js/pages/headOffice.js', 'public/js');
mix.js('resources/js/pages/draw-fixtures-show.js', 'public/js');
mix.js('resources/js/pages/team-schedule.js', 'public/js');
mix.js('resources/js/pages/frontend/insert-score.js', 'public/js');

// Admin modular frontend — core utilities
mix.copy('resources/js/admin/core/api.js',     'public/assets/js/admin/core/api.js');
mix.copy('resources/js/admin/core/toast.js',   'public/assets/js/admin/core/toast.js');
mix.copy('resources/js/admin/core/modal.js',   'public/assets/js/admin/core/modal.js');
mix.copy('resources/js/admin/core/loading.js', 'public/assets/js/admin/core/loading.js');
mix.copy('resources/js/admin/core/confirm.js', 'public/assets/js/admin/core/confirm.js');
mix.copy('resources/js/admin/core/routes.js',  'public/assets/js/admin/core/routes.js');
mix.copy('resources/js/admin/core/state.js',   'public/assets/js/admin/core/state.js');

// Admin modular frontend — round-robin page modules
mix.copy('resources/js/admin/roundrobin/matrix.js',       'public/assets/js/admin/roundrobin/matrix.js');
mix.copy('resources/js/admin/roundrobin/scores.js',        'public/assets/js/admin/roundrobin/scores.js');
mix.copy('resources/js/admin/roundrobin/standings.js',     'public/assets/js/admin/roundrobin/standings.js');
mix.copy('resources/js/admin/roundrobin/oop.js',           'public/assets/js/admin/roundrobin/oop.js');
mix.copy('resources/js/admin/roundrobin/groups.js',        'public/assets/js/admin/roundrobin/groups.js');
mix.copy('resources/js/admin/roundrobin/schedule.js',      'public/assets/js/admin/roundrobin/schedule.js');
mix.copy('resources/js/admin/roundrobin/brackets.js',      'public/assets/js/admin/roundrobin/brackets.js');
mix.copy('resources/js/admin/roundrobin/state-badges.js',  'public/assets/js/admin/roundrobin/state-badges.js');
mix.copy('resources/js/admin/roundrobin/init.js',          'public/assets/js/admin/roundrobin/init.js');

/*
 |--------------------------------------------------------------------------
 | Assets / Fonts / Images
 |--------------------------------------------------------------------------
 */

mixAssetsDir('vendor/libs/**/*.{png,jpg,jpeg,gif}', (src, dest) =>
  mix.copy(src, dest)
);

mixAssetsDir('vendor/libs/formvalidation/dist', (src, dest) =>
  mix.copyDirectory(src, dest)
);

mixAssetsDir('vendor/fonts/*/*', (src, dest) => mix.copy(src, dest));
mixAssetsDir('vendor/fonts/!(_)*.scss', (src, dest) =>
  mix.sass(
    src,
    dest
      .replace(/(\\|\/)scss(\\|\/)/, '$1css$2')
      .replace(/\.scss$/, '.css'),
    { sassOptions }
  )
);

mix.copy(
  'node_modules/@fortawesome/fontawesome-free/webfonts/*',
  'public/assets/vendor/fonts/fontawesome'
);

mix.copy(
  'node_modules/katex/dist/fonts/*',
  'public/assets/vendor/libs/quill/fonts'
);

/*
 |--------------------------------------------------------------------------
 | Versioning
 |--------------------------------------------------------------------------
 */

mix.version();

/*
 |--------------------------------------------------------------------------
 | BrowserSync (optional)
 |--------------------------------------------------------------------------
 */

if (!mix.inProduction()) {
  mix.browserSync({
    proxy: 'http://localhost/ct/public',
    files: [
      'app/**/*.php',
      'resources/views/**/*.blade.php',
      'public/js/**/*.js',
      'public/css/**/*.css'
    ]
  });
}
