<?php
declare(strict_types=1);

/**
 * Staff (and admins) process category requests and support tickets, and can look up
 * customer account status. Staff never see balances or transactions.
 */
final class StaffController
{
    public static function overview(): array
    {
        require_role('staff', 'admin');
        $req = q_all('SELECT status, COUNT(*) AS n FROM category_requests GROUP BY status');
        $tix = q_all('SELECT status, COUNT(*) AS n FROM support_tickets GROUP BY status');
        $recent = q_all(
            '(SELECT \'request\' AS kind, r.id, r.requested_name AS title, r.status, r.created_at, u.email
              FROM category_requests r JOIN users u ON u.id = r.user_id ORDER BY r.created_at DESC LIMIT 5)
             UNION ALL
             (SELECT \'ticket\' AS kind, t.id, t.subject AS title, t.status, t.created_at, u.email
              FROM support_tickets t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC LIMIT 5)
             ORDER BY created_at DESC LIMIT 8'
        );
        return [
            'requests' => array_map('intval', array_column($req, 'n', 'status')) + ['pending' => 0, 'approved' => 0, 'rejected' => 0],
            'tickets' => array_map('intval', array_column($tix, 'n', 'status')) + ['pending' => 0, 'in_progress' => 0, 'resolved' => 0],
            'customers' => (int) q_val('SELECT COUNT(*) FROM users WHERE role = \'customer\''),
            'recent' => $recent,
        ];
    }

    public static function requests(): array
    {
        require_role('staff', 'admin');
        [$page, $per, $offset] = pagination(10);
        $where = ['1 = 1'];
        $params = [];
        if (in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true)) {
            $where[] = 'r.status = ?';
            $params[] = $_GET['status'];
        }
        $qStr = trim((string) ($_GET['q'] ?? ''));
        if ($qStr !== '') {
            $like = '%' . addcslashes($qStr, '%_\\') . '%';
            $where[] = '(r.requested_name LIKE ? OR u.email LIKE ? OR CONCAT(u.first_name, \' \', u.last_name) LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $total = (int) q_val("SELECT COUNT(*) FROM category_requests r JOIN users u ON u.id = r.user_id WHERE $w", $params);
        $rows = q_all(
            "SELECT r.id, r.requested_name, r.requested_type, r.reason, r.status, r.created_at, r.resolved_at,
                    u.email AS requester_email, CONCAT(u.first_name, ' ', u.last_name) AS requester_name,
                    CONCAT(h.first_name, ' ', h.last_name) AS handler_name
             FROM category_requests r
             JOIN users u ON u.id = r.user_id
             LEFT JOIN users h ON h.id = r.handled_by
             WHERE $w
             ORDER BY (r.status = 'pending') DESC, r.created_at ASC
             LIMIT $per OFFSET $offset",
            $params
        );
        return paged($rows, $total, $page, $per);
    }

    /** Approve (creates the category for that customer) or reject a pending request. */
    public static function resolveRequest(int $id): array
    {
        $staff = require_role('staff', 'admin');
        $v = new Validator(read_json());
        $status = $v->enum('status', 'Status', ['approved', 'rejected']);
        $v->done();

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $req = q_one('SELECT * FROM category_requests WHERE id = ? FOR UPDATE', [$id]);
            if (!$req) {
                fail(404, 'Request not found.');
            }
            if ($req['status'] !== 'pending') {
                fail(409, 'This request has already been ' . $req['status'] . '.');
            }
            q(
                'UPDATE category_requests SET status = ?, handled_by = ?, resolved_at = NOW() WHERE id = ?',
                [$status, $staff['id'], $id]
            );
            if ($status === 'approved') {
                q(
                    'INSERT IGNORE INTO categories (user_id, name, type, is_global) VALUES (?, ?, ?, 0)',
                    [$req['user_id'], $req['requested_name'], $req['requested_type']]
                );
                notify((int) $req['user_id'], "Your category request \"{$req['requested_name']}\" was approved. It's ready to use.");
            } else {
                notify((int) $req['user_id'], "Your category request \"{$req['requested_name']}\" was not approved.");
            }
            audit((int) $staff['id'], "category_request.$status", 'category_request', $id, $req['requested_name']);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['ok' => true, 'status' => $status];
    }

    public static function tickets(): array
    {
        require_role('staff', 'admin');
        [$page, $per, $offset] = pagination(10);
        $where = ['1 = 1'];
        $params = [];
        if (in_array($_GET['status'] ?? '', ['pending', 'in_progress', 'resolved'], true)) {
            $where[] = 't.status = ?';
            $params[] = $_GET['status'];
        } elseif (($_GET['status'] ?? '') === 'open') {
            $where[] = 't.status <> \'resolved\'';
        }
        $qStr = trim((string) ($_GET['q'] ?? ''));
        if ($qStr !== '') {
            $like = '%' . addcslashes($qStr, '%_\\') . '%';
            $where[] = '(t.subject LIKE ? OR t.message LIKE ? OR u.email LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $total = (int) q_val("SELECT COUNT(*) FROM support_tickets t JOIN users u ON u.id = t.user_id WHERE $w", $params);
        $rows = q_all(
            "SELECT t.id, t.subject, t.message, t.staff_reply, t.status, t.created_at, t.resolved_at,
                    u.email AS requester_email, CONCAT(u.first_name, ' ', u.last_name) AS requester_name,
                    CONCAT(h.first_name, ' ', h.last_name) AS handler_name
             FROM support_tickets t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN users h ON h.id = t.handled_by
             WHERE $w
             ORDER BY FIELD(t.status, 'pending', 'in_progress', 'resolved'), t.created_at ASC
             LIMIT $per OFFSET $offset",
            $params
        );
        return paged($rows, $total, $page, $per);
    }

    public static function updateTicket(int $id): array
    {
        $staff = require_role('staff', 'admin');
        $body = read_json();
        $v = new Validator($body);
        $status = $v->enum('status', 'Status', ['pending', 'in_progress', 'resolved']);
        $reply = $v->str('staff_reply', 'Reply', 2000, required: false);
        $v->done();

        $ticket = q_one('SELECT * FROM support_tickets WHERE id = ?', [$id]);
        if (!$ticket) {
            fail(404, 'Ticket not found.');
        }
        q(
            'UPDATE support_tickets SET status = ?, staff_reply = COALESCE(NULLIF(?, \'\'), staff_reply), handled_by = ?,
                    resolved_at = CASE WHEN ? = \'resolved\' THEN COALESCE(resolved_at, NOW()) ELSE NULL END
             WHERE id = ?',
            [$status, $reply, $staff['id'], $status, $id]
        );
        if ($status !== $ticket['status'] || ($reply !== '' && $reply !== $ticket['staff_reply'])) {
            $label = str_replace('_', ' ', $status);
            notify((int) $ticket['user_id'], "Your support ticket \"{$ticket['subject']}\" was updated ($label).");
        }
        audit((int) $staff['id'], 'ticket.update', 'support_ticket', $id, "status: $status");
        return ['ok' => true];
    }

    /** Customer lookup for support: account status only — no balances or transactions. */
    public static function users(): array
    {
        require_role('staff', 'admin');
        [$page, $per, $offset] = pagination(10);
        $where = ['role = \'customer\''];
        $params = [];
        $qStr = trim((string) ($_GET['q'] ?? ''));
        if ($qStr !== '') {
            $like = '%' . addcslashes($qStr, '%_\\') . '%';
            $where[] = '(email LIKE ? OR CONCAT(first_name, \' \', last_name) LIKE ?)';
            array_push($params, $like, $like);
        }
        if (in_array($_GET['status'] ?? '', ['active', 'suspended'], true)) {
            $where[] = 'status = ?';
            $params[] = $_GET['status'];
        }
        $w = implode(' AND ', $where);
        $total = (int) q_val("SELECT COUNT(*) FROM users WHERE $w", $params);
        $rows = q_all(
            "SELECT id, first_name, last_name, email, status, last_login_at, created_at,
                    (SELECT COUNT(*) FROM support_tickets st WHERE st.user_id = users.id AND st.status <> 'resolved') AS open_tickets
             FROM users WHERE $w ORDER BY created_at DESC LIMIT $per OFFSET $offset",
            $params
        );
        return paged($rows, $total, $page, $per);
    }
}
