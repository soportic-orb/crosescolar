<?php
declare(strict_types=1);

namespace Cros\Controllers;

use Cros\Core\Controller;
use Cros\Models\Content;
use Cros\Models\TicketType;

/** Portada del web. */
class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('public/home', [
            'title' => site_name(),
            'description' => setting('meta_description', ''),
            'courses' => Content::courses(),
            'categories' => Content::categories(),
            'schedule' => Content::schedule(),
            'infoBlocks' => Content::infoBlocks(),
            'faqs' => Content::faqs(),
            'gallery' => Content::gallery(12),
            'documents' => Content::documents(),
            'sponsors' => Content::sponsorsByTier(),
            'ticketTypes' => TicketType::all(),
            'isHome' => true,
        ]);
    }
}
