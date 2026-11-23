<?php
/*
 * A parameter is listed and added through its machine (the machine id is in the URL), and read, edited and deleted
 * through its own id, which already says which machine it belongs to.
 */
Flight::route('GET /machines/@id:[0-9]+/parameters', function ($id) {
    Flight::json(Flight::machineParameterService()->getByMachine($id));
});

Flight::route('POST /machines/@id:[0-9]+/parameters', function ($id) {
    Flight::json(Flight::machineParameterService()->addToMachine($id, Flight::request()->data->getData()), 201);
});

Flight::route('GET /machine-parameters/@id:[0-9]+', function ($id) {
    Flight::json(Flight::machineParameterService()->getById($id));
});

Flight::route('PUT /machine-parameters/@id:[0-9]+', function ($id) {
    Flight::json(Flight::machineParameterService()->update(Flight::request()->data->getData(), $id));
});

Flight::route('DELETE /machine-parameters/@id:[0-9]+', function ($id) {
    Flight::machineParameterService()->delete($id);
    Flight::json(['message' => 'Machine parameter deleted.']);
});
