'use strict';

const gulp = require('gulp');
const concat = require('gulp-concat');
const cleanCSS = require('gulp-clean-css');
const uglify = require('gulp-uglify');
const rename = require('gulp-rename');
const del = require('del');
const plumber = require('gulp-plumber');

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
        fonts: 'assets/dist/fonts'
    }
};

// Clean dist directory
gulp.task('clean', () => {
    return del(['assets/dist/**/*']);
});

// CSS processing task
gulp.task('css', () => {
    return gulp.src(paths.src.css)
        .pipe(plumber())
        .pipe(concat('proval-styles.css'))
        .pipe(gulp.dest(paths.dist.css))
        .pipe(cleanCSS({ level: 2 }))
        .pipe(rename({ suffix: '.min' }))
        .pipe(gulp.dest(paths.dist.css));
});

// Vendor JS processing task
gulp.task('js:vendor', () => {
    return gulp.src(paths.src.js.vendor)
        .pipe(plumber())
        .pipe(concat('proval-vendor.js'))
        .pipe(gulp.dest(paths.dist.js))
        .pipe(uglify({
            compress: { drop_console: true }
        }))
        .pipe(rename({ suffix: '.min' }))
        .pipe(gulp.dest(paths.dist.js));
});

// App JS processing task
gulp.task('js:app', () => {
    return gulp.src(paths.src.js.app)
        .pipe(plumber())
        .pipe(concat('proval-app.js'))
        .pipe(gulp.dest(paths.dist.js))
        .pipe(uglify({
            compress: { drop_console: false }
        }))
        .pipe(rename({ suffix: '.min' }))
        .pipe(gulp.dest(paths.dist.js));
});

// Images copying task
gulp.task('images', () => {
    return gulp.src(paths.src.images)
        .pipe(gulp.dest(paths.dist.images));
});

// Fonts copying task
gulp.task('fonts', () => {
    return gulp.src(paths.src.fonts)
        .pipe(gulp.dest(paths.dist.fonts));
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

<?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'prod'): ?>
<!-- Production: Minified and concatenated assets (2 HTTP requests) -->
<link rel="stylesheet" href="assets/dist/css/proval-styles.min.css">
<?php else: ?>
<!-- Development: Individual assets for debugging (7 HTTP requests) -->
<link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
<link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
<link rel="stylesheet" href="assets/vendors/css/dataTables.bootstrap4.css">
<link rel="stylesheet" href="assets/css/jquery-ui.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/responsive.css">
<link rel="stylesheet" href="assets/css/modern-manage-ui.css">
<?php endif; ?>

<link rel="shortcut icon" href="assets/images/favicon.ico" />

<?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'prod'): ?>
<!-- Production: Minified vendor JS (1 HTTP request) -->
<script src="assets/dist/js/proval-vendor.min.js" type="text/javascript"></script>
<?php else: ?>
<!-- Development: Individual vendor scripts (1 HTTP request for now) -->
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

<?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'prod'): ?>
<!-- Production: Single minified application bundle (1 HTTP request) -->
<script src="assets/dist/js/proval-app.min.js"></script>
<?php else: ?>
<!-- Development: Individual scripts for debugging (8 HTTP requests) -->
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

<!-- Performance Monitor -->
<?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'dev' && defined('CACHE_DEBUG_ENABLED') && CACHE_DEBUG_ENABLED): ?>
<script>
console.log('🚀 APCu Cache Status: <?= defined("CACHE_ENABLED") && CACHE_ENABLED ? "Enabled" : "Disabled" ?>');
console.log('📊 Environment: <?= defined("ENVIRONMENT") ? ENVIRONMENT : "undefined" ?>');
console.log('⚡ Assets: <?= (defined("ENVIRONMENT") && ENVIRONMENT === "prod") ? "Minified (2 requests)" : "Development (15+ requests)" ?>');
</script>
<?php endif; ?>
`;

    // Write optimized templates
    fs.writeFileSync('assets/inc/_header_optimized.php', headerContent);
    fs.writeFileSync('assets/inc/_footerjs_optimized.php', footerContent);

    console.log('✅ Created optimized template files');
    done();
});

// Asset analysis task
gulp.task('analyze', (done) => {
    const fs = require('fs');

    console.log('\n📊 ProVal HVAC Asset Analysis\n');

    // Count original files
    const originalFiles = [...paths.src.css, ...paths.src.js.vendor, ...paths.src.js.app];
    let totalOriginalSize = 0;

    originalFiles.forEach(file => {
        if (fs.existsSync(file)) {
            const stats = fs.statSync(file);
            totalOriginalSize += stats.size;
        }
    });

    console.log(`📦 Original Assets:`);
    console.log(`   Files: ${originalFiles.length}`);
    console.log(`   Total Size: ${(totalOriginalSize / 1024).toFixed(2)} KB`);
    console.log(`   HTTP Requests: ${originalFiles.length}`);

    // Check built assets
    const builtAssets = [
        'assets/dist/css/proval-styles.min.css',
        'assets/dist/js/proval-vendor.min.js',
        'assets/dist/js/proval-app.min.js'
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
        console.log(`\n🚀 Built Assets:`);
        console.log(`   Files: ${builtFiles}`);
        console.log(`   Total Size: ${(totalBuiltSize / 1024).toFixed(2)} KB`);
        console.log(`   HTTP Requests: ${builtFiles}`);
        console.log(`   Size Reduction: ${((totalOriginalSize - totalBuiltSize) / totalOriginalSize * 100).toFixed(1)}%`);
        console.log(`   Request Reduction: ${((originalFiles.length - builtFiles) / originalFiles.length * 100).toFixed(1)}%`);
    } else {
        console.log(`\n⚠️  Built assets not found. Run 'gulp build' first.`);
    }

    console.log(`\n🎯 Performance Impact:`);
    console.log(`   Target: 2 HTTP requests (1 CSS + 1 JS)`);
    console.log(`   Before: ${originalFiles.length} HTTP requests`);
    console.log(`   After: ${builtFiles || originalFiles.length} HTTP requests`);
    console.log(`   Goal Achieved: ${builtFiles <= 2 ? '✅ YES' : '❌ NO'}`);

    done();
});

// Build tasks
gulp.task('build:assets', gulp.parallel('css', 'js:vendor', 'js:app', 'images', 'fonts'));
gulp.task('build', gulp.series('clean', 'build:assets', 'create-optimized-templates'));

// Default task
gulp.task('default', gulp.series('build'));

// Export paths for external use
module.exports = { paths };