<?php
// src/Document/Areabrick/Iframe.php

namespace App\Document\Areabrick;
use Pimcore\Model\Document\Editable\Area\Info;
use \Pimcore\Model\DataObject;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Pimcore\Extension\Document\Areabrick\AbstractTemplateAreabrick;

class PortfolioListBrick extends AbstractTemplateAreabrick
{
    public function getName(): string
    {
        return 'PortfolioListBrick';
    }

    public function getDescription(): string
    {
        return 'Embed contents from other URL (websites) via iframe';
    }

    public function getTemplateLocation(): string
    {
        return static::TEMPLATE_LOCATION_GLOBAL;
    }
    
    public function needsReload(): bool
    {
        // optional
        // here you can decide whether adding this bricks should trigger a reload
        // in the editing interface, this could be necessary in some cases. default=false
        return false;
    }
    public function action(Info $info): ?RedirectResponse
    {
        // Initialize a listing of Portfolio data objects
        $portfolio = new DataObject\Portfolio\Listing();
        
        // Set the 'portfolios' parameter in the Info object for later use in the view
        $info->setParam('portfolios', $portfolio);
    
        return null;
    }

}