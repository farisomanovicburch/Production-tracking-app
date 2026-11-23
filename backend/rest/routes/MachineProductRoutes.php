<?php
// Same shape as the parameter routes. There is no PUT: a link is removed and added again instead.
Flight::route('GET /machines/@id:[0-9]+/products', function ($id) {
    Flight::json(Flight::machineProductService()->getByMachine($id));
});

Flight::route('POST /machines/@id:[0-9]+/products', function ($id) {
    Flight::json(Flight::machineProductService()->addToMachine($id, Flight::request()->data->getData()), 201);
});

Flight::route('DELETE /machine-products/@id:[0-9]+', function ($id) {
    Flight::machineProductService()->delete($id);
    Flight::json(['message' => 'Product link removed.']);
});
