<?php

declare (strict_types=1);
$site = getenv('LUNIXI_TEST_SITE');
if (!is_string($site) || !is_file($site . '/config.php')) {
    throw new RuntimeException('Disposable installed fixture required.');
}
$zip = new ZipArchive();
if ($zip->open(dirname(__DIR__) . '/dist/lunixi-opencart3-1.0.0.ocmod.zip') !== true) {
    throw new RuntimeException('Build the merchant artifact first.');
}
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (!str_starts_with($name, 'upload/') || str_contains($name, '..') || str_ends_with($name, '/')) {
        continue;
    }
    $target = $site . '/' . substr($name, 7);
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0755, true);
    }
    file_put_contents($target, $zip->getFromIndex($i));
}
$zip->close();
echo "Merchant ZIP deployed into disposable official native fixture.\n";
