<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (! function_exists('user_error_message')) {
    /**
     * What a user may see for a caught exception (BL-006). Business-rule errors (plain SPL
     * exceptions and the application's own exceptions) show their message. Anything else
     * (database, framework, PHP engine errors) is logged in full and the user sees a generic
     * message with the request reference.
     */
    function user_error_message(Throwable $e): string
    {
        $shown = [
            RuntimeException::class, InvalidArgumentException::class, DomainException::class,
            LengthException::class, OutOfRangeException::class, UnexpectedValueException::class,
            LogicException::class,
        ];
        $class = get_class($e);
        if (in_array($class, $shown, true) || str_starts_with($class, 'App\\')) {
            return $e->getMessage();
        }

        $reference = \App\Libraries\RequestContext::id();
        log_message('error', '[{ref}] {class}: {message} at {file}:{line}', [
            'ref' => $reference, 'class' => $class, 'message' => $e->getMessage(),
            'file' => $e->getFile(), 'line' => $e->getLine(),
        ]);

        return 'Something went wrong. Please try again; if it keeps happening, quote reference ' . $reference . '.';
    }
}
