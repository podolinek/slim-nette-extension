<?php

namespace BrandEmbassyTest\Slim\Dummy;

use BrandEmbassy\Slim\ErrorHandler;
use BrandEmbassy\Slim\Request\RequestInterface;
use BrandEmbassy\Slim\Response\ResponseInterface;
use Exception;

final class NotFoundHandler implements ErrorHandler
{

    /**
     * @inheritdoc
     */
    public function __invoke(RequestInterface $request, ResponseInterface $response, Exception $e = null)
    {
        return $response->withJson(['error' => 'Dummy NotFoundHandler here!'], 404);
    }

}
