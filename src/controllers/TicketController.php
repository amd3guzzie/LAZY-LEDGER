<?php
declare(strict_types=1);

/** Customer side of support tickets (staff side lives in StaffController). */
final class TicketController
{
    public static function index(): array
    {
        $user = require_role('customer');
        $rows = q_all(
            'SELECT id, subject, message, staff_reply, status, created_at, resolved_at
             FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC, id DESC',
            [$user['id']]
        );
        return ['data' => array_map(fn($r) => ['id' => (int) $r['id']] + $r, $rows)];
    }

    public static function store(): array
    {
        $user = require_role('customer');
        $v = new Validator(read_json());
        $subject = $v->str('subject', 'Subject', 120, min: 3);
        $message = $v->str('message', 'Message', 2000, min: 10);
        $v->done();

        q('INSERT INTO support_tickets (user_id, subject, message) VALUES (?, ?, ?)', [$user['id'], $subject, $message]);
        $id = (int) db()->lastInsertId();
        audit((int) $user['id'], 'ticket.create', 'support_ticket', $id, $subject);
        return ['id' => $id];
    }
}
