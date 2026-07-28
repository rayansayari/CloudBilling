<?php

namespace App\Controller;

use App\Entity\FinancialOffer;
use App\Entity\Project;
use App\Form\FinancialOfferUploadType;
use App\Service\AuditService;
use App\Service\FinancialOfferParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/project/{project_id}/offer')]
class FinancialOfferController extends AbstractController
{
    #[Route('/new', name: 'app_financial_offer_new', methods: ['GET', 'POST'])]
    public function new(
        int $project_id,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        FinancialOfferParser $parser,
        AuditService $audit
    ): Response {
        $project = $em->getRepository(Project::class)->find($project_id);
        if (!$project) {
            throw $this->createNotFoundException('Projet non trouvé');
        }

        $form = $this->createForm(FinancialOfferUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            $effectiveDate = $form->get('effectiveDate')->getData();

            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/offers';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                try {
                    $file->move($uploadDir, $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload du fichier.');
                    return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
                }

                // Parse the file to get lines
                $filePath = $uploadDir . '/' . $newFilename;
                
                $offer = new FinancialOffer();
                $offer->setFileName($file->getClientOriginalName());
                $offer->setFilePath($newFilename);
                $offer->setEffectiveDate($effectiveDate);
                $offer->setProject($project);
                
                // Determine new version
                $activeOffer = $project->getActiveOffer();
                $newVersion = $activeOffer ? $activeOffer->getVersion() + 1 : 1;
                $offer->setVersion($newVersion);

                try {
                    $lines = $parser->parse($filePath, $offer);
                    if (empty($lines)) {
                        $this->addFlash('warning', 'Le fichier a été analysé mais aucune ligne exploitable n\'a été trouvée.');
                    } else {
                        foreach ($lines as $line) {
                            $offer->addOfferLine($line);
                        }
                    }

                    // Save offer in session for preview
                    $request->getSession()->set('pending_offer', clone $offer);
                    $request->getSession()->set('pending_offer_lines', $lines);
                    
                    return $this->redirectToRoute('app_financial_offer_preview', ['project_id' => $project->getId()]);

                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'analyse du fichier : ' . $e->getMessage());
                }
            }
        }

        return $this->render('financial_offer/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/preview', name: 'app_financial_offer_preview', methods: ['GET', 'POST'])]
    public function preview(int $project_id, Request $request, EntityManagerInterface $em, AuditService $audit): Response
    {
        $project = $em->getRepository(Project::class)->find($project_id);
        
        /** @var FinancialOffer $offer */
        $offer = $request->getSession()->get('pending_offer');
        $lines = $request->getSession()->get('pending_offer_lines');

        if (!$offer || !$lines) {
            $this->addFlash('warning', 'Session expirée ou aucune offre en attente de prévisualisation.');
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        if ($request->isMethod('POST')) {
            // Re-bind lines to offer (since session serialization might lose the link)
            $offerToSave = new FinancialOffer();
            $offerToSave->setFileName($offer->getFileName());
            $offerToSave->setFilePath($offer->getFilePath());
            $offerToSave->setEffectiveDate($offer->getEffectiveDate());
            $offerToSave->setVersion($offer->getVersion());
            $offerToSave->setIsActive(true);
            $offerToSave->setProject($project);

            foreach ($lines as $lineData) {
                $line = new \App\Entity\OfferLine();
                $line->setResourceName($lineData->getResourceName());
                $line->setServiceType($lineData->getServiceType());
                $line->setSkuId($lineData->getSkuId());
                $line->setTerm($lineData->getTerm());
                $line->setUnit($lineData->getUnit());
                $line->setUnitPrice($lineData->getUnitPrice());
                $line->setQuantity($lineData->getQuantity());
                $line->setDiscount($lineData->getDiscount());
                $line->setDiscountType($lineData->getDiscountType());
                $line->setDescription($lineData->getDescription());
                $offerToSave->addOfferLine($line);
            }

            // Deactivate older offers
            foreach ($project->getFinancialOffers() as $oldOffer) {
                $oldOffer->setIsActive(false);
            }

            $em->persist($offerToSave);
            $em->flush();

            $audit->log('IMPORT_OFFER', 'FinancialOffer', $offerToSave->getId(), 
                "Nouvelle offre v{$offerToSave->getVersion()} importée pour le SO {$project->getSoNumber()}");

            $request->getSession()->remove('pending_offer');
            $request->getSession()->remove('pending_offer_lines');

            $this->addFlash('success', 'La nouvelle offre financière a été validée et activée.');
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('financial_offer/preview.html.twig', [
            'project' => $project,
            'offer' => $offer,
            'lines' => $lines,
        ]);
    }
}
