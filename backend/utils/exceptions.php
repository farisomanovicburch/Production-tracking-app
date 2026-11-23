<?php
/*
 * Exceptions the services throw when a rule is broken. Each one carries the HTTP status code of the answer,
 * so a service never deals with HTTP: the error handler in index.php reads getStatus() and sends the JSON.
 */
class HttpException extends Exception
{
    protected $status = 500;

    public function getStatus()
    {
        return $this->status;
    }
}

/** 400: the input is invalid or a business rule is broken. */
class ValidationException extends HttpException { protected $status = 400; }

/** 401: the user is not logged in (missing, invalid or expired token). Thrown from Milestone 4. */
class UnauthorizedException extends HttpException { protected $status = 401; }

/** 403: the user is logged in but may not do this. Thrown from Milestone 4. */
class ForbiddenException extends HttpException { protected $status = 403; }

/** 404: the requested entity does not exist. */
class NotFoundException extends HttpException { protected $status = 404; }

/** 503: an external API (the weather service) is not available. */
class ExternalServiceException extends HttpException { protected $status = 503; }
