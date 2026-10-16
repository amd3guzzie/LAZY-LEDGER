<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/visitors.php';

final class VisitorController
{
    /** POST /track - the browser reports a click (see assets/js/tracker.js). Works for guests too. */
    public static function track(): array
    {
        $v = new Validator(read_json());
        $action = $v->str('action', 'Action', 120);
        $page = $v->str('page', 'Page', 190, false);
        $v->done();
        log_visit($action, $page ?? '/');
        return ['ok' => true];
    }
}
