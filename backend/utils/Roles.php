<?php
/*
 * User roles. Must match the ENUM of users.role in database/production_tracker.sql.
 */
class Roles
{
    const ADMIN = 'admin';
    const OPERATOR = 'operator';

    public static function all()
    {
        return [self::ADMIN, self::OPERATOR];
    }
}
