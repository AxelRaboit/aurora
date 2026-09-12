<?php

declare(strict_types=1);

namespace Aurora\Module\General\Trash\Controller\Backend;

use Aurora\Module\General\Trash\View\TrashViewBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Read-only on purpose: this screen says where deleted things are, and each
 * module's own screen is where they come back or end. One button that restores
 * anything from anywhere would need every module's rules restated here.
 */
#[Route('/backend/trash', name: 'backend_general_trash')]
#[IsGranted('general.trash.view')]
class TrashController extends AbstractController
{
    public function __construct(private readonly TrashViewBuilder $viewBuilder) {}

    #[Route('', name: '')]
    public function index(): Response
    {
        return $this->render('@General/backend/trash/index.html.twig', $this->viewBuilder->indexView());
    }
}
