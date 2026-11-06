<?php

namespace App\Controller;

use App\Entity\Film;
use App\Entity\Reservation;
use App\Entity\Seance;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/gestion')]
#[IsGranted('ROLE_ADMIN')]
class GestionCinemaController extends AbstractController
{
    private const PAR_PAGE = 6;

    #[Route('/seances', name: 'gestion_seances', methods: ['GET', 'POST'])]
    public function seances(
        Request $request,
        EntityManagerInterface $em
    ): Response {

        // AJOUTER OU MODIFIER UNE SÉANCE
        if ($request->isMethod('POST')) {
            $idSeance = $request->request->getInt('idSeance', 0);

            // Déterminer si on ajoute ou modifie une séance
            $seance = null;

            if ($idSeance > 0) {
                $seance = $em->getRepository(Seance::class)->find($idSeance);

                if (!$seance) {
                    $this->addFlash('erreur', 'La séance demandée est introuvable.');

                    return $this->redirectToRoute('gestion_seances');
                }

                $identifiantToken = 'modifier' . $idSeance;
            } else {
                $identifiantToken = 'ajouter_seance';
            }

            // Vérification CSRF
            $token = (string) $request->request->get('_token', '');

            if (!$this->isCsrfTokenValid($identifiantToken, $token)) {
                $this->addFlash(
                    'erreur',
                    'Le formulaire a expiré ou le jeton est invalide. Rechargez la page puis réessayez.'
                );

                if ($idSeance > 0) {
                    return $this->redirectToRoute('gestion_seances', [
                        'edit' => $idSeance,
                        '_fragment' => 'formulaire-seance',
                    ]);
                }

                return $this->redirectToRoute('gestion_seances', [
                    '_fragment' => 'formulaire-seance',
                ]);
            }

            // Récupération des données
            $filmId = $request->request->getInt('film');
            $salleTexte = trim((string) $request->request->get('salle', ''));
            $dateTexte = trim((string) $request->request->get('dateSeance', ''));
            $horaireTexte = trim((string) $request->request->get('horaireSeance', ''));

            $film = $em->getRepository(Film::class)->find($filmId);

            $date = \DateTime::createFromFormat('!Y-m-d', $dateTexte);
            $horaire = \DateTime::createFromFormat('!H:i', $horaireTexte);

            $dateValide = $date !== false
                && $date->format('Y-m-d') === $dateTexte;

            $horaireValide = $horaire !== false
                && $horaire->format('H:i') === $horaireTexte;

            // Vérification des données
            if (
                !$film
                || !ctype_digit($salleTexte)
                || (int) $salleTexte < 1
                || !$dateValide
                || !$horaireValide
            ) {
                $this->addFlash(
                    'erreur',
                    'Veuillez sélectionner un film, une salle, une date et un horaire valides.'
                );

                if ($idSeance > 0) {
                    return $this->redirectToRoute('gestion_seances', [
                        'edit' => $idSeance,
                        '_fragment' => 'formulaire-seance',
                    ]);
                }

                return $this->redirectToRoute('gestion_seances', [
                    '_fragment' => 'formulaire-seance',
                ]);
            }

            // Vérifier que la salle existe
            $salleExiste = (int) $em->getConnection()->fetchOne(
                'SELECT COUNT(*) FROM Places WHERE salle = ?',
                [(int) $salleTexte]
            ) > 0;

            if (!$salleExiste) {
                $this->addFlash(
                    'erreur',
                    'Cette salle ne possède aucune place configurée.'
                );

                if ($idSeance > 0) {
                    return $this->redirectToRoute('gestion_seances', [
                        'edit' => $idSeance,
                        '_fragment' => 'formulaire-seance',
                    ]);
                }

                return $this->redirectToRoute('gestion_seances', [
                    '_fragment' => 'formulaire-seance',
                ]);
            }

            // MODIFICATION
            if ($seance) {

                // Empêcher de changer le film ou la salle
                // si la séance possède déjà des réservations.
                if (
                    $seance->getReservations()->count() > 0
                    && (
                        $seance->getFilm()->getIdFilm() !== $film->getIdFilm()
                        || $seance->getSalle() !== (int) $salleTexte
                    )
                ) {
                    $this->addFlash(
                        'erreur',
                        'Impossible de changer le film ou la salle : cette séance possède déjà des réservations.'
                    );

                    return $this->redirectToRoute('gestion_seances', [
                        'edit' => $idSeance,
                        '_fragment' => 'formulaire-seance',
                    ]);
                }

                $seance->setFilm($film);
                $seance->setSalle((int) $salleTexte);
                $seance->setDateSeance($date);
                $seance->setHoraireSeance($horaire);

                $em->flush();

                $this->addFlash(
                    'succes',
                    'La séance a été modifiée avec succès.'
                );

            } else {

                // AJOUT
                $seance = new Seance();
                $seance->setFilm($film);
                $seance->setSalle((int) $salleTexte);
                $seance->setDateSeance($date);
                $seance->setHoraireSeance($horaire);

                $em->persist($seance);
                $em->flush();

                $this->addFlash(
                    'succes',
                    'La séance a été ajoutée avec succès.'
                );
            }

            return $this->redirectToRoute('gestion_seances');
        }

        // FILTRES
        $q = trim((string) $request->query->get('q', ''));
        $salle = trim((string) $request->query->get('salle', ''));
        $dateTexte = trim((string) $request->query->get('date', ''));
        $page = max(1, $request->query->getInt('page', 1));

        // CHARGER LA SÉANCE À MODIFIER
        $seanceEdit = null;
        $idEdit = $request->query->getInt('edit', 0);

        if ($idEdit > 0) {
            $seanceEdit = $em->getRepository(Seance::class)->find($idEdit);

            if (!$seanceEdit) {
                $this->addFlash('erreur', 'La séance demandée est introuvable.');

                return $this->redirectToRoute('gestion_seances');
            }
        }

        // REQUÊTE DES SÉANCES
        $qb = $em->createQueryBuilder()
            ->select('s', 'f')
            ->from(Seance::class, 's')
            ->join('s.film', 'f')
            ->orderBy('s.dateSeance', 'ASC')
            ->addOrderBy('s.horaireSeance', 'ASC');

        if ($q !== '') {
            $qb->andWhere('f.nomFilm LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($salle !== '' && ctype_digit($salle)) {
            $qb->andWhere('s.salle = :salle')
                ->setParameter('salle', (int) $salle);
        }

        if ($dateObjet = $this->lireDate($dateTexte)) {
            $qb->andWhere('s.dateSeance = :date')
                ->setParameter('date', $dateObjet, Types::DATE_MUTABLE);
        }

        // PAGINATION
        $qb->setFirstResult(($page - 1) * self::PAR_PAGE)
            ->setMaxResults(self::PAR_PAGE);

        $paginator = new Paginator($qb);
        $total = count($paginator);
        $pages = max(1, (int) ceil($total / self::PAR_PAGE));

        if ($page > $pages) {
            return $this->redirectToRoute('gestion_seances', [
                'page' => $pages,
                'q' => $q,
                'salle' => $salle,
                'date' => $dateTexte,
            ]);
        }

        $seances = iterator_to_array($paginator, false);

        // CAPACITÉ ET PLACES RÉSERVÉES
        $connexion = $em->getConnection();

        $capacites = $connexion->fetchAllKeyValue(
            'SELECT salle, COUNT(*) FROM Places GROUP BY salle'
        );

        $reservees = $connexion->fetchAllKeyValue(
            'SELECT idSeance, COUNT(*) FROM ReservationPlaces GROUP BY idSeance'
        );

        $places = [];

        foreach ($seances as $seance) {
            $capacite = (int) ($capacites[$seance->getSalle()] ?? 0);
            $prises = (int) ($reservees[$seance->getIdSeance()] ?? 0);

            $places[$seance->getIdSeance()] = [
                'libres' => max(0, $capacite - $prises),
                'total' => $capacite,
            ];
        }

        // SALLES ET FILMS DU FORMULAIRE
        $salles = array_map('intval', array_keys($capacites));
        sort($salles);

        $films = $em->getRepository(Film::class)->findBy(
            [],
            ['nomFilm' => 'ASC']
        );

        return $this->render('admin/seancesAdmin.html.twig', [
            'seances' => $seances,
            'places' => $places,
            'salles' => $salles,
            'films' => $films,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'filtre_q' => $q,
            'filtre_salle' => $salle,
            'filtre_date' => $dateTexte,
            'seanceEdit' => $seanceEdit,
            'isEdit' => $seanceEdit !== null,
        ]);
    }

    // SUPPRIMER UNE SÉANCE
    #[Route(
        '/seances/{id}/supprimer',
        name: 'gestion_seance_delete',
        methods: ['POST']
    )]
    public function supprimerSeance(
        Seance $seance,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid(
            'supprimer' . $seance->getIdSeance(),
            $token
        )) {
            $this->addFlash('erreur', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('gestion_seances');
        }

        if ($seance->getReservations()->count() > 0) {
            $this->addFlash(
                'erreur',
                'Impossible de supprimer cette séance : elle possède déjà des réservations.'
            );
        } else {
            $em->remove($seance);
            $em->flush();

            $this->addFlash('succes', 'Séance supprimée avec succès.');
        }

        return $this->redirectToRoute('gestion_seances');
    }

    // GESTION DES RÉSERVATIONS
    #[Route('/reservations', name: 'gestion_reservations', methods: ['GET'])]
    public function reservations(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $q = trim((string) $request->query->get('q', ''));
        $statut = (string) $request->query->get('statut', '');
        $dateTexte = trim((string) $request->query->get('date', ''));
        $page = max(1, $request->query->getInt('page', 1));

        $qb = $em->createQueryBuilder()
            ->select('r', 'u', 's', 'f')
            ->from(Reservation::class, 'r')
            ->join('r.utilisateur', 'u')
            ->join('r.seance', 's')
            ->join('s.film', 'f')
            ->orderBy('r.idReservation', 'DESC');

        if ($q !== '') {
            $qb->andWhere('(u.email LIKE :q OR f.nomFilm LIKE :q)')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($statut !== '') {
            $qb->andWhere('r.statut = :statut')
                ->setParameter('statut', $statut);
        }

        if ($dateObjet = $this->lireDate($dateTexte)) {
            $qb->andWhere('s.dateSeance = :date')
                ->setParameter('date', $dateObjet, Types::DATE_MUTABLE);
        }

        $qb->setFirstResult(($page - 1) * self::PAR_PAGE)
            ->setMaxResults(self::PAR_PAGE);

        $paginator = new Paginator($qb);
        $totalFiltre = count($paginator);
        $pages = max(1, (int) ceil($totalFiltre / self::PAR_PAGE));

        if ($page > $pages) {
            return $this->redirectToRoute('gestion_reservations', [
                'page' => $pages,
                'q' => $q,
                'statut' => $statut,
                'date' => $dateTexte,
            ]);
        }

        $reservations = iterator_to_array($paginator, false);
        $repo = $em->getRepository(Reservation::class);

        return $this->render('admin/reservationsAdmin.html.twig', [
            'reservations' => $reservations,
            'page' => $page,
            'pages' => $pages,
            'total_filtre' => $totalFiltre,
            'total' => $repo->count([]),
            'confirmees' => $repo->count([
                'statut' => Reservation::STATUT_PAYEE,
            ]),
            'en_attente' => $repo->count([
                'statut' => Reservation::STATUT_EN_ATTENTE,
            ]),
            'annulees' => $repo->count([
                'statut' => 'annulee',
            ]),
            'filtre_q' => $q,
            'filtre_statut' => $statut,
            'filtre_date' => $dateTexte,
        ]);
    }

    // LIRE UNE DATE
    private function lireDate(string $texte): ?\DateTime
    {
        if ($texte === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('!Y-m-d', $texte);

        if (!$date || $date->format('Y-m-d') !== $texte) {
            return null;
        }

        return $date;
    }
}
