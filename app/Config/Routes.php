<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'PriceSync::index');
$routes->get('price-sync', 'PriceSync::index');
$routes->get('references', 'PriceSync::references');
$routes->post('references/upload', 'PriceSync::saveReferences', ['filter' => 'csrf']);
$routes->post('sync', 'PriceSync::process', ['filter' => 'csrf']);
