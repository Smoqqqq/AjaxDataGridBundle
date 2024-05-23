<?php

namespace Smoq\DataGridBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AjaxController extends AbstractController {
    #[Route('/_smoq_ajax_datagrid/_ajax/{datagridId}', name: 'smoq_ajax_datagrid_ajax')]
    public function ajax(string $datagridId): JsonResponse
    {
        $className = str_replace('_', '\\', $datagridId);

        $datagrid = new $className();

        return $this->json($datagrid->ajax());
    }
}