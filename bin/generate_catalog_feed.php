<?php
declare(strict_types=1);
$root=dirname(__DIR__);['pdo'=>$pdo]=require $root.'/bootstrap/app.php';
$generated=(new App\Services\CatalogFeedService($pdo,$root))->generate(in_array('--force',$argv,true));fwrite(STDOUT,$generated?"Catalog feed generated\n":"Catalog feed is current\n");
