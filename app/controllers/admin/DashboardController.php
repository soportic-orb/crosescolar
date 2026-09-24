<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Core\Readiness;
use Cros\Core\Stripe;
use Cros\Models\Order;
use Cros\Models\TicketType;

/** Tauler inicial del panell. */
class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        $eventDate = (string) setting('event_date', '');
        $daysLeft = $eventDate !== '' ? (int) floor((strtotime($eventDate) - strtotime('today')) / 86400) : null;

        $this->adminView('dashboard', [
            'title' => 'Tauler',
            'stats' => Order::stats(),
            'ticketTypes' => TicketType::all(false),
            'recentOrders' => Db::all('SELECT * FROM orders ORDER BY created_at DESC LIMIT 8'),
            'recentRegistrations' => Db::all(
                'SELECT r.*, c.name AS category_name FROM registrations r
                 LEFT JOIN categories c ON c.id = r.category_id
                 ORDER BY r.created_at DESC LIMIT 8'
            ),
            'daysLeft' => $daysLeft,
            'readiness' => Readiness::due() ? Readiness::pending() : [],
            'readinessTotal' => count(Readiness::checks()),
            'stripeMode' => Stripe::mode(),
            'stripeReady' => Stripe::configured(),
            'salesOpen' => \Cros\Controllers\TicketsController::salesOpen(),
            'counts' => [
                'sponsors' => (int) Db::val('SELECT COUNT(*) FROM sponsors', [], 0),
                'courses' => (int) Db::val('SELECT COUNT(*) FROM courses', [], 0),
                'categories' => (int) Db::val('SELECT COUNT(*) FROM categories', [], 0),
            ],
        ]);
    }
}
