<?php
declare(strict_types=1);
$root=dirname(__DIR__);$zip=new ZipArchive();if($zip->open($root.'/dist/lunixi-opencart3-0.1.3.ocmod.zip')!==true){throw new RuntimeException('Build ZIP first.');}
$required=['upload/system/library/lunixi3/sdk/Commerce/CheckoutService.php','upload/system/library/lunixi3/sdk/autoload.php','upload/system/library/lunixi3/autoload.php','upload/admin/controller/extension/payment/lunixi.php','upload/catalog/controller/extension/payment/lunixi.php','README.md','LICENSE','SECURITY.md','release.json'];
foreach($required as$file){if($zip->locateName($file)===false){throw new RuntimeException('Required merchant artifact file missing: '.$file);}}
for($i=0;$i<$zip->numFiles;$i++){$name=$zip->getNameIndex($i);$value=$zip->getFromIndex($i);if(str_contains($name,'..')||str_starts_with($name,'/')||preg_match('~(?:^|/)(?:tests|tools|vendor|[.]git)/~',$name)){throw new RuntimeException('Unsafe merchant artifact layout.');}if(preg_match('~/(?:Users|home)/|git@[A-Za-z0-9.-]+|-----BEGIN PRIVATE KEY-----|synthetic_webhook_secret_~',$value)){throw new RuntimeException('Nonpublic data in merchant artifact.');}}
$release=json_decode($zip->getFromName('release.json'),true,16,JSON_THROW_ON_ERROR);if(($release['sdk']??'')!=='0.29.6'||($release['merchant_composer_required']??true)!==false){throw new RuntimeException('Incorrect SDK distribution metadata.');}
$zip->close();echo "Standalone ZIP layout, scoped SDK, release metadata and secret/path checks passed.\n";
