<?php
Flight::route('GET /machines', function () {
    Flight::json(Flight::machineService()->getAll());
});

Flight::route('GET /machines/@id:[0-9]+', function ($id) {
    Flight::json(Flight::machineService()->getById($id));
});

Flight::route('POST /machines', function () {
    Flight::json(Flight::machineService()->add(Flight::request()->data->getData()), 201);
});

Flight::route('PUT /machines/@id:[0-9]+', function ($id) {
    Flight::json(Flight::machineService()->update(Flight::request()->data->getData(), $id));
});

Flight::route('DELETE /machines/@id:[0-9]+', function ($id) {
    Flight::machineService()->delete($id);
    Flight::json(['message' => 'Machine deleted.']);
});
