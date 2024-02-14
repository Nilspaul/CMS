<?php

namespace App\Controller;

use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use \Pimcore\Model\DataObject;
use App\Document\Areabrick\TimelineBrick;
use Pimcore\Model\DataObject\Timeline;

class MediaproductionController extends FrontendController
{
    /**
     * @param Request $request
     * @return Response
     */
    public function defaultAction(Request $request): Response
    {
        $timeline = new DataObject\Timeline\Listing();
        $timelineOptions = $timeline->getClass()->getFieldDefinition("major")->getOptions();
        $specializations = new DataObject\User\Listing();
        $specializationsOptions = $specializations->getClass()->getFieldDefinition("specialization")->getOptions();

        // SplineViewer-Objekte abrufen
        return $this->render('webAndMobile/webAndMobile.html.twig', [
            'timelineOptions' => $timelineOptions,
            'specializationsOptions' => $specializationsOptions,
        ]);
    }
}
