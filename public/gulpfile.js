'use strict';

const gulp = require('gulp');
const concat = require('gulp-concat');
const cleanCSS = require('gulp-clean-css');
const uglify = require('gulp-uglify');
const sourcemaps = require('gulp-sourcemaps');
const rename = require('gulp-rename');
const rev = require('gulp-rev');
const revReplace = require('gulp-rev-replace');
const del = require('del');
const plumber = require('gulp-plumber');
const notify = require('gulp-notify');
const gulpif = require('gulp-if');
const browserSync = require('browser-sync').create();
const autoprefixer = require('gulp-autoprefixer');
const imagemin = require('gulp-imagemin');

// Environment detection
const isProduction = process.argv.indexOf('--production') !== -1;

// Path configuration
const paths = {
    src: {
        css: [
            'assets/vendors/mdi/css/materialdesignicons.min.css',
            'assets/vendors/css/vendor.bundle.base.css',
            'assets/vendors/css/dataTables.bootstrap4.css',
            'assets/css/jquery-ui.min.css',
            'assets/css/style.css',
            'assets/css/responsive.css',
            'assets/css/modern-manage-ui.css'
        ],
        js: {
            vendor: [
                'assets/js/jquery.min.js',
                'assets/vendors/js/vendor.bundle.base.js',
                'assets/vendors/js/jquery.dataTables.js',
                'assets/vendors/js/dataTables.bootstrap4.js',
                'assets/js/jquery-ui.min.js'
            ],
            app: [
                'assets/js/off-canvas.js',
                'assets/js/hoverable-collapse.js',
                'assets/js/misc.js',
                'assets/js/sweetalert2.all.min.js',
                'assets/js/form-validation.js',
                'assets/js/responsive-tables.js',
                'assets/js/custom.js',
                'assets/js/read-http-get.js'
            ]
        },
        images: 'assets/images/**/*',
        fonts: 'assets/vendors/mdi/fonts/**/*'
    },
    dist: {
        css: 'assets/dist/css',
        js: 'assets/dist/js',
        images: 'assets/dist/images',
        fonts: 'assets/dist/fonts',
        rev: 'assets/dist/rev'
    },
    templates: [
        'assets/inc/_header.php',
        'assets/inc/_footerjs.php'
    ]
};

// Error handling
const handleError = (err) => {
    notify.onError({
        title: 'Gulp Error',
        message: '<%= error.message %>'
    })(err);
    console.log(err.toString());
};

// Clean dist directory
gulp.task('clean', () => {
    return del([
        'assets/dist/**/*',
        '!assets/dist',
        '!assets/dist/.gitkeep'
    ]);
});

// CSS processing task
gulp.task('css', () => {
    return gulp.src(paths.src.css)
        .pipe(plumber({ errorHandler: handleError }))
        .pipe(gulpif(!isProduction, sourcemaps.init()))
        .pipe(concat('proval-styles.css'))
        .pipe(autoprefixer({
            overrideBrowserslist: ['last 2 versions'],
            cascade: false
        }))
        .pipe(gulpif(isProduction, cleanCSS({
            level: 2,
            compatibility: 'ie9'
        })))
        .pipe(gulpif(isProduction, rename({ suffix: '.min' })))
        .pipe(gulpif(!isProduction, sourcemaps.write('.')))
        .pipe(gulpif(isProduction, rev()))
        .pipe(gulp.dest(paths.dist.css))
        .pipe(gulpif(isProduction, rev.manifest('css-manifest.json')))
        .pipe(gulpif(isProduction, gulp.dest(paths.dist.rev)))
        .pipe(browserSync.stream());
});

// Vendor JS processing task
gulp.task('js:vendor', () => {
    return gulp.src(paths.src.js.vendor)
        .pipe(plumber({ errorHandler: handleError }))
        .pipe(gulpif(!isProduction, sourcemaps.init()))
        .pipe(concat('proval-vendor.js'))
        .pipe(gulpif(isProduction, uglify({
            compress: {
                drop_console: true,
                drop_debugger: true
            },
            mangle: true
        })))
        .pipe(gulpif(isProduction, rename({ suffix: '.min' })))
        .pipe(gulpif(!isProduction, sourcemaps.write('.')))
        .pipe(gulpif(isProduction, rev()))
        .pipe(gulp.dest(paths.dist.js))
        .pipe(gulpif(isProduction, rev.manifest('vendor-js-manifest.json')))
        .pipe(gulpif(isProduction, gulp.dest(paths.dist.rev)));
});

// App JS processing task
gulp.task('js:app', () => {
    return gulp.src(paths.src.js.app)
        .pipe(plumber({ errorHandler: handleError }))
        .pipe(gulpif(!isProduction, sourcemaps.init()))
        .pipe(concat('proval-app.js'))
        .pipe(gulpif(isProduction, uglify({
            compress: {
                drop_console: false, // Keep console.log for app debugging
                drop_debugger: true
            },
            mangle: true
        })))
        .pipe(gulpif(isProduction, rename({ suffix: '.min' })))
        .pipe(gulpif(!isProduction, sourcemaps.write('.')))
        .pipe(gulpif(isProduction, rev()))
        .pipe(gulp.dest(paths.dist.js))
        .pipe(gulpif(isProduction, rev.manifest('app-js-manifest.json')))
        .pipe(gulpif(isProduction, gulp.dest(paths.dist.rev)));
});

// Images optimization task
gulp.task('images', () => {
    return gulp.src(paths.src.images)
        .pipe(gulpif(isProduction, imagemin([
            imagemin.gifsicle({ interlaced: true }),
            imagemin.mozjpeg({ progressive: true }),
            imagemin.optipng({ optimizationLevel: 5 }),
            imagemin.svgo({
                plugins: [
                    { removeViewBox: false },
                    { cleanupIDs: false }
                ]
            })
        ])))
        .pipe(gulp.dest(paths.dist.images));
});

// Fonts copying task
gulp.task('fonts', () => {
    return gulp.src(paths.src.fonts)
        .pipe(gulp.dest(paths.dist.fonts));
});

// Template update task for production
gulp.task('templates', (done) => {
    if (!isProduction) {
        return done();
    }

    // Read manifest files
    const cssManifest = require('./assets/dist/rev/css-manifest.json');
    const vendorJsManifest = require('./assets/dist/rev/vendor-js-manifest.json');
    const appJsManifest = require('./assets/dist/rev/app-js-manifest.json');

    // Update header template
    return gulp.src('assets/inc/_header.php')
        .pipe(plumber({ errorHandler: handleError }))
        .pipe(revReplace({
            manifest: gulp.src([
                'assets/dist/rev/css-manifest.json'
            ]),
            replaceInExtensions: ['.php']
        }))
        .pipe(gulp.dest('assets/inc/'));
});

// Create optimized template files
gulp.task('create-optimized-templates', (done) => {
    const fs = require('fs');

    // Create optimized header template
    const headerContent = `<?php
/**
 * ProVal HVAC - Optimized Header Assets
 * Reduces HTTP requests from 8-10 to 2
 */
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>ProVal HVAC</title>

<?php if (ENVIRONMENT === 'prod'): ?>
<!-- Production: Minified and concatenated assets -->
<link rel="stylesheet" href="assets/dist/css/proval-styles.min.css">
<?php else: ?>
<!-- Development: Individual assets for debugging -->
<link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
<link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
<link rel="stylesheet" href="assets/vendors/css/dataTables.bootstrap4.css">
<link rel="stylesheet" href="assets/css/jquery-ui.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/responsive.css">
<link rel="stylesheet" href="assets/css/modern-manage-ui.css">
<?php endif; ?>

<link rel="shortcut icon" href="assets/images/favicon.ico" />

<?php if (ENVIRONMENT === 'prod'): ?>
<!-- Production: Minified vendor JS -->
<script src="assets/dist/js/proval-vendor.min.js" type="text/javascript"></script>
<?php else: ?>
<!-- Development: Individual vendor scripts -->
<script src="assets/js/jquery.min.js" type="text/javascript"></script>
<?php endif; ?>
`;

    // Create optimized footer template
    const footerContent = `<?php
/**
 * ProVal HVAC - Optimized Footer Assets
 * Loads application JavaScript based on environment
 */
?>

<?php if (ENVIRONMENT === 'prod'): ?>
<!-- Production: Single minified application bundle -->
<script src="assets/dist/js/proval-app.min.js"></script>
<?php else: ?>
<!-- Development: Individual scripts for debugging -->
<script src="assets/vendors/js/vendor.bundle.base.js"></script>
<script src="assets/vendors/js/jquery.dataTables.js"></script>
<script src="assets/vendors/js/dataTables.bootstrap4.js"></script>
<script src="assets/js/off-canvas.js"></script>
<script src="assets/js/hoverable-collapse.js"></script>
<script src="assets/js/misc.js"></script>
<script src="assets/js/sweetalert2.all.min.js"></script>
<script src="assets/js/form-validation.js"></script>
<script src="assets/js/jquery-ui.min.js"></script>
<script src="assets/js/responsive-tables.js"></script>
<script src="assets/js/custom.js"></script>
<script src="assets/js/read-http-get.js"></script>
<?php endif; ?>

<!-- APCu Cache Performance Monitor (Development Only) -->
<?php if (ENVIRONMENT === 'dev' && CACHE_DEBUG_ENABLED): ?>
<script>
console.log('🚀 APCu Cache Status: <?= CACHE_ENABLED ? "Enabled" : "Disabled" ?>');
console.log('📊 Environment: <?= ENVIRONMENT ?>');
console.log('⚡ Assets: <?= ENVIRONMENT === "prod" ? "Minified" : "Development" ?>');
</script>
<?php endif; ?>
`;

    // Write optimized templates
    fs.writeFileSync('assets/inc/_header_optimized.php', headerContent);
    fs.writeFileSync('assets/inc/_footerjs_optimized.php', footerContent);

    console.log('✅ Created optimized template files');
    done();
});

// Watch task
gulp.task('watch', () => {
    gulp.watch(paths.src.css, gulp.series('css'));
    gulp.watch(paths.src.js.vendor, gulp.series('js:vendor'));
    gulp.watch(paths.src.js.app, gulp.series('js:app'));
    gulp.watch(paths.src.images, gulp.series('images'));
});

// BrowserSync task
gulp.task('serve', gulp.series('build:dev', () => {
    browserSync.init({
        proxy: 'localhost:8000', // Adjust to your local PHP server
        port: 3000,
        open: false,
        notify: false
    });

    gulp.watch(paths.src.css, gulp.series('css'));
    gulp.watch(paths.src.js.vendor, gulp.series('js:vendor', (done) => {
        browserSync.reload();
        done();
    }));
    gulp.watch(paths.src.js.app, gulp.series('js:app', (done) => {
        browserSync.reload();
        done();
    }));
    gulp.watch('*.php').on('change', browserSync.reload);
}));

// Build tasks
gulp.task('build:assets', gulp.parallel('css', 'js:vendor', 'js:app', 'images', 'fonts'));

gulp.task('build:dev', gulp.series('clean', 'build:assets', 'create-optimized-templates'));

gulp.task('build:prod', gulp.series('clean', 'build:assets', 'create-optimized-templates'));

gulp.task('build', (done) => {
    if (isProduction) {
        return gulp.series('build:prod')(done);
    } else {
        return gulp.series('build:dev')(done);
    }
});

// Asset analysis task
gulp.task('analyze', (done) => {
    const fs = require('fs');
    const path = require('path');

    console.log('\n📊 ProVal HVAC Asset Analysis\n');

    // Analyze original assets
    let totalOriginalSize = 0;
    let originalFiles = 0;

    [...paths.src.css, ...paths.src.js.vendor, ...paths.src.js.app].forEach(file => {
        if (fs.existsSync(file)) {
            const stats = fs.statSync(file);
            totalOriginalSize += stats.size;
            originalFiles++;
        }
    });

    console.log(`Original Assets:`);
    console.log(`  Files: ${originalFiles}`);
    console.log(`  Total Size: ${(totalOriginalSize / 1024).toFixed(2)} KB`);
    console.log(`  HTTP Requests: ${originalFiles}`);

    // Analyze built assets (if they exist)
    const builtAssets = [
        'assets/dist/css/proval-styles.css',
        'assets/dist/js/proval-vendor.js',
        'assets/dist/js/proval-app.js'
    ];

    let totalBuiltSize = 0;
    let builtFiles = 0;

    builtAssets.forEach(file => {
        if (fs.existsSync(file)) {
            const stats = fs.statSync(file);
            totalBuiltSize += stats.size;
            builtFiles++;
        }
    });

    if (builtFiles > 0) {
        console.log(`\\nBuilt Assets:`);
        console.log(`  Files: ${builtFiles}`);
        console.log(`  Total Size: ${(totalBuiltSize / 1024).toFixed(2)} KB`);
        console.log(`  HTTP Requests: ${builtFiles}`);
        console.log(`  Size Reduction: ${((totalOriginalSize - totalBuiltSize) / totalOriginalSize * 100).toFixed(1)}%`);
        console.log(`  Request Reduction: ${((originalFiles - builtFiles) / originalFiles * 100).toFixed(1)}%`);
    } else {
        console.log(`\\n⚠️  Built assets not found. Run 'npm run build' first.`);
    }

    console.log(`\\n🎯 Performance Goals:`);
    console.log(`  Target HTTP Requests: 2 (CSS + JS)`);
    console.log(`  Current Requests: ${builtFiles || originalFiles}`);
    console.log(`  Goal Achieved: ${builtFiles <= 2 ? '✅' : '❌'}`);

    done();
});

// Default task
gulp.task('default', gulp.series('serve'));

// Export paths for external use
module.exports = { paths };