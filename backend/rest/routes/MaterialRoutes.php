<?php
Flight::route('GET /materials', function () {
    Flight::json(Flight::materialService()->getAll());
});

Flight::route('GET /materials/@id:[0-9]+', function ($id) {
    Flight::json(Flight::materialService()->getById($id));
});

Flight::route('POST /materials', function () {
    Flight::json(Flight::materialService()->add(Flight::request()->data->getData()), 201);
});

// Edits everything except the stock: that changes only through the delivery route below and through runs.
Flight::route('PUT /materials/@id:[0-9]+', function ($id) {
    Flight::json(Flight::materialService()->update(Flight::request()->data->getData(), $id));
});

// An action on the material, so it is a sub-path (course style). PUT, not POST: no new resource is created,
// the material's stock is changed.
Flight::route('PUT /materials/@id:[0-9]+/delivery', function ($id) {
    Flight::json(Flight::materialService()->addDelivery($id, Flight::request()->data->getData()));
});

Flight::route('DELETE /materials/@id:[0-9]+', function ($id) {
    Flight::materialService()->delete($id);
    Flight::json(['message' => 'Material deleted.']);
});
