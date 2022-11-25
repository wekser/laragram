<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class BotException
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected static $dontReport = [
        NotFoundRouteException::class,
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @param Exception $exception
     * @return null
     */
    public static function handle(Exception $exception)
    {
        if (self::shouldntReport($exception)) {
            self::report($exception);
        }

        return self::render();
    }

    /**
     * Determine if the exception is in the "do not report" list.
     *
     * @param \Exception $e
     * @return bool
     */
    protected static function shouldntReport(Exception $exception)
    {
        $dontReport = self::$dontReport;

        return is_null(collect($dontReport)->first(function ($value, $key) use ($exception) {
            return $exception instanceof $value;
        }));
    }

    /**
     * Report or log an exception.
     *
     * @param \Exception $exception
     * @return void
     */
    protected static function report(Exception $exception)
    {
        app('log')->error($exception, ['exception' => $exception]);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @return null
     */
    protected static function render()
    {
        return response('');
    }
}