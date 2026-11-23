<?php
Flight::route('GET /products', function () {
    Flight::json(Flight::productService()->getAll());
});

Flight::route('GET /products/@id:[0-9]+', function ($id) {
    Flight::json(Flight::productService()->getById($id));
});

Flight::route('POST /products', function () {
    Flight::json(Flight::productService()->add(Flight::request()->data->getData()), 201);
});

Flight::route('PUT /products/@id:[0-9]+', function ($id) {
    Flight::json(Flight::productService()->update(Flight::request()->data->getData(), $id));
});

Flight::route('DELETE /products/@id:[0-9]+', function ($id) {
    Flight::productService()->delete($id);
    Flight::json(['message' => 'Product deleted.']);
});
