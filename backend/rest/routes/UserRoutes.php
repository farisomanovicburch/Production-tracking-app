<?php
/*
 * A route reads the request, calls one service method and answers with JSON. No rules and no try/catch here:
 * the service throws, and the error handler in index.php turns the exception into the answer.
 */
Flight::route('GET /users', function () {
    Flight::json(Flight::userService()->getAll());
});

Flight::route('GET /users/@id:[0-9]+', function ($id) {
    Flight::json(Flight::userService()->getById($id));
});

Flight::route('POST /users', function () {
    Flight::json(Flight::userService()->add(Flight::request()->data->getData()), 201);
});

Flight::route('PUT /users/@id:[0-9]+', function ($id) {
    Flight::json(Flight::userService()->update(Flight::request()->data->getData(), $id));
});

Flight::route('DELETE /users/@id:[0-9]+', function ($id) {
    Flight::userService()->delete($id);
    Flight::json(['message' => 'User deleted.']);
});
