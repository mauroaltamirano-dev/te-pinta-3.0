<?php

declare(strict_types=1);

const PRODUCT_RESOURCE = '/var/www/html/app/Modules/Products/Http/Resources/PublicProductResource.php';
const FEATURED_PRODUCT_VIEW = '/var/www/html/app/Modules/Marketing/Queries/BuildFeaturedProductPublicView.php';
const HELPER_DESTINATION = '/var/www/html/app/Modules/Products/Support/PublicProductImageUrl.php';
const CLASSMAP = '/var/www/html/vendor/composer/autoload_classmap.php';
const STATIC_CLASSMAP = '/var/www/html/vendor/composer/autoload_static.php';

/** @param array<int, string> $arguments */
function argument(array $arguments, int $index, string $name): string
{
    $value = $arguments[$index] ?? null;

    if (! is_string($value) || preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) {
        fail("The {$name} checksum must be a lowercase SHA-256 value.");
    }

    return $value;
}

function fail(string $message): never
{
    fwrite(STDERR, $message."\n");
    exit(1);
}

function sha256(string $path): string
{
    $checksum = hash_file('sha256', $path);

    if ($checksum === false) {
        fail("Could not checksum {$path}.");
    }

    return $checksum;
}

function assertChecksum(string $path, string $expected): void
{
    if (! is_file($path)) {
        fail("Required overlay file is missing: {$path}.");
    }

    if (sha256($path) !== $expected) {
        fail("Unexpected baked source checksum: {$path}.");
    }
}

function replaceExactlyOnce(string $path, string $from, string $to): void
{
    $contents = file_get_contents($path);

    if ($contents === false || substr_count($contents, $from) !== 1) {
        fail("Expected one known source fragment in {$path}.");
    }

    if (file_put_contents($path, str_replace($from, $to, $contents)) === false) {
        fail("Could not write {$path}.");
    }
}

function addClassmapEntry(string $path, string $anchor, string $entry): void
{
    $contents = file_get_contents($path);

    if ($contents === false || substr_count($contents, $anchor) !== 1 || str_contains($contents, $entry)) {
        fail("Unexpected Composer classmap state: {$path}.");
    }

    if (file_put_contents($path, str_replace($anchor, $anchor.$entry, $contents)) === false) {
        fail("Could not write {$path}.");
    }
}

$productChecksum = argument($argv, 1, 'product resource');
$featuredChecksum = argument($argv, 2, 'featured product view');
$helperChecksum = argument($argv, 3, 'helper');
$helperSource = $argv[4] ?? null;

if (! is_string($helperSource) || $helperSource === '') {
    fail('The frozen helper source path is required.');
}

assertChecksum(PRODUCT_RESOURCE, $productChecksum);
assertChecksum(FEATURED_PRODUCT_VIEW, $featuredChecksum);
assertChecksum($helperSource, $helperChecksum);

if (! is_dir(dirname(HELPER_DESTINATION)) && ! mkdir(dirname(HELPER_DESTINATION), 0755, true) && ! is_dir(dirname(HELPER_DESTINATION))) {
    fail('Could not create the Products support directory.');
}

if (! copy($helperSource, HELPER_DESTINATION)) {
    fail('Could not copy the frozen public image helper.');
}

replaceExactlyOnce(
    PRODUCT_RESOURCE,
    "use App\\Modules\\Products\\Models\\Product;\nuse Illuminate\\Http\\Request;",
    "use App\\Modules\\Products\\Models\\Product;\nuse App\\Modules\\Products\\Support\\PublicProductImageUrl;\nuse Illuminate\\Http\\Request;",
);
replaceExactlyOnce(PRODUCT_RESOURCE, "use Illuminate\\Support\\Facades\\Storage;\n", '');
replaceExactlyOnce(
    PRODUCT_RESOURCE,
    "'image_url' => \$this->image_path ? Storage::disk('public')->url(\$this->image_path) : null,",
    "'image_url' => PublicProductImageUrl::fromPath(\$this->image_path),",
);

replaceExactlyOnce(
    FEATURED_PRODUCT_VIEW,
    "use App\\Modules\\Products\\Models\\Product;\nuse Illuminate\\Support\\Facades\\Storage;",
    "use App\\Modules\\Products\\Models\\Product;\nuse App\\Modules\\Products\\Support\\PublicProductImageUrl;",
);
replaceExactlyOnce(
    FEATURED_PRODUCT_VIEW,
    "'image_url' => \$product->image_path === null\n                ? null\n                : Storage::disk('public')->url(\$product->image_path),",
    "'image_url' => PublicProductImageUrl::fromPath(\$product->image_path),",
);

$resourceAnchor = "    'App\\\\Modules\\\\Products\\\\Http\\\\Resources\\\\PublicProductResource' => \$baseDir . '/app/Modules/Products/Http/Resources/PublicProductResource.php',\n";
$resourceEntry = "    'App\\\\Modules\\\\Products\\\\Support\\\\PublicProductImageUrl' => \$baseDir . '/app/Modules/Products/Support/PublicProductImageUrl.php',\n";
addClassmapEntry(CLASSMAP, $resourceAnchor, $resourceEntry);

$staticResourceAnchor = "        'App\\\\Modules\\\\Products\\\\Http\\\\Resources\\\\PublicProductResource' => __DIR__ . '/../..' . '/app/Modules/Products/Http/Resources/PublicProductResource.php',\n";
$staticResourceEntry = "        'App\\\\Modules\\\\Products\\\\Support\\\\PublicProductImageUrl' => __DIR__ . '/../..' . '/app/Modules/Products/Support/PublicProductImageUrl.php',\n";
addClassmapEntry(STATIC_CLASSMAP, $staticResourceAnchor, $staticResourceEntry);

foreach ([PRODUCT_RESOURCE, FEATURED_PRODUCT_VIEW, HELPER_DESTINATION, CLASSMAP, STATIC_CLASSMAP] as $path) {
    if (! is_file($path)) {
        fail("Overlay output is missing: {$path}.");
    }
}
