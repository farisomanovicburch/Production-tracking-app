<?php
// Register and login are public: a user who is not logged in has no token yet. Only /auth/me needs one.
Flight::route('POST /auth/register', function () {
    Flight::json(Flight::authService()->register(Flight::request()->data->getData()), 201);
});

Flight::route('POST /auth/login', function () {
    Flight::json(Flight::authService()->login(Flight::request()->data->getData()));
});

// AuthMiddleware has already checked the token and stored the user, so the id here can be trusted.
Flight::route('GET /auth/me', function () {
    Flight::json(Flight::userService()->getById(Flight::get('user')['id']));
})->addMiddleware(new AuthMiddleware());
