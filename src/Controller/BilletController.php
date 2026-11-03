<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\ReservationRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class BilletController extends AbstractController
{
    #[Route(
        '/billet/{id}',
        name: 'app_billet',
        requirements: ['id' => '\d+'],
        methods: ['GET']
    )]
    #[IsGranted('ROLE_USER')]
    public function billet(
        int $id,
        ReservationRepository $reservationRepository
    ): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $reservation = $reservationRepository->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        // Sécurité : on ne peut télécharger que ses propres billets
        if ($reservation->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $seance = $reservation->getSeance();
        $places = $reservation->getReservationPlaces();

        // Contenu du QR code : de quoi identifier le billet à l'entrée
        $numerosPlaces = [];

        foreach ($places as $rp) {
            $numerosPlaces[] = $rp->getPlace()->getNumeroPlace();
        }

        $contenuQr = sprintf(
            'GRANDCINE|RES:%d|SEANCE:%d|PLACES:%s',
            $reservation->getIdReservation(),
            $seance->getIdSeance(),
            implode(',', $numerosPlaces)
        );

        $qrCode = new QrCode(data: $contenuQr, size: 180, margin: 5);
        $qrCodeDataUri = (new PngWriter())->write($qrCode)->getDataUri();

        $html = $this->renderView('billet/billet.html.twig', [
            'reservation' => $reservation,
            'seance' => $seance,
            'places' => $places,
            'utilisateur' => $user,
            'qrCode' => $qrCodeDataUri,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans'); // gère bien les accents et le €

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                // "inline" = ouvre dans le navigateur, "attachment" = télécharge
                'Content-Disposition' => 'inline; filename="billet-' . $id . '.pdf"',
            ]
        );
    }
}