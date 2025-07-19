<?php

declare(strict_types=1);

namespace Libok\Framework\Controllers;

use Doctrine\ORM\EntityManager;

abstract class BaseController
{
    /**
     * The BaseController constructor.
     * All controllers that extend this class will have access to the EntityManager.
     *
     * @param \Doctrine\ORM\EntityManager $entityManager
     */
    public function __construct(protected readonly EntityManager $entityManager)
    {
    }

    /**
     * Renders a view with the shared header and footer layout.
     *
     * @param string $view The path to the view file (e.g., 'auth/login').
     * @param array $data Data to be extracted and made available to the view.
     * @return void
     */
    protected function render(string $view, array $data = []): void
    {
        // Makes variables from the $data array available to the view
        // e.g., $data['error'] becomes $error
        extract($data);

        // Include the view within the standard layout
        require_once __DIR__ . "/../Views/layout/header.php";
        require_once __DIR__ . "/../Views/{$view}.php";
        require_once __DIR__ . "/../Views/layout/footer.php";
    }
}