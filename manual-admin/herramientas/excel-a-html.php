<?php

/**
 * Convierte el .xlsx descargado en una página HTML con el mismo contenido y
 * formato (colores, anchos, filtros, logo), para fotografiarla.
 *
 *   php excel-a-html.php entrada.xlsx salida.html
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html;

require __DIR__.'/../../vendor/autoload.php';

[$script, $entrada, $salida] = $argv + [null, null, null];
if (! $entrada || ! $salida) {
    fwrite(STDERR, "Uso: php excel-a-html.php entrada.xlsx salida.html\n");
    exit(1);
}

$libro = IOFactory::load($entrada);
$hoja = $libro->getActiveSheet();
$hoja->setShowGridlines(true);

$escritor = new Html($libro);
$escritor->setSheetIndex(0);
$escritor->setEmbedImages(true);
$escritor->save($salida);

echo 'Hoja "'.$hoja->getTitle().'": '.$hoja->getHighestRow().' filas, '.$hoja->getHighestColumn()." columnas\n";
