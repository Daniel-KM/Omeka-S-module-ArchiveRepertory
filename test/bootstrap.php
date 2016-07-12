<?php
require __DIR__ . '/../vendor/autoload.php';
use OmekaTestHelper\Bootstrap;
Bootstrap::bootstrap(__DIR__);
include_once __DIR__ . '/../src/Media/Ingester/UploadAnywhere.php';
include_once __DIR__ . '/../src/File/OmekaRenameUpload.php';
include_once __DIR__ . '/../src/File/ArchiveManager.php';
include_once __DIR__ . '/../src/Service/FileArchiveManagerFactory.php';


Bootstrap::loginAsAdmin();
Bootstrap::enableModule('ArchiveRepertory');