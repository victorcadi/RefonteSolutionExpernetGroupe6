<?php

namespace App\Repository;

use App\Catalogue\CriteresRecherche;
use App\Entity\Formation;
use App\Enum\TypeFormation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    /**
     * Recherche paginée du catalogue public.
     *
     * @return Paginator<Formation>
     */
    public function rechercher(CriteresRecherche $criteres): Paginator
    {
        $qb = $this->requeteFiltree($criteres)
            ->addSelect('t', 'fi')
            ->orderBy('f.miseEnAvant', 'DESC')
            ->addOrderBy('f.intitule', 'ASC')
            ->setFirstResult(($criteres->page - 1) * CriteresRecherche::PAR_PAGE)
            ->setMaxResults(CriteresRecherche::PAR_PAGE);

        return new Paginator($qb->getQuery(), fetchJoinCollection: false);
    }

    /**
     * Nombre de résultats pour chaque valeur d'une facette, en tenant compte
     * des autres filtres actifs (la facette elle-même est ignorée).
     *
     * @param 'filiere'|'niveau'|'cpf'|'modalite'|'type' $facette
     *
     * @return array<string, int> valeur de la facette => nombre de formations
     */
    public function compterParFacette(CriteresRecherche $criteres, string $facette): array
    {
        $sansFacette = clone $criteres;
        match ($facette) {
            'filiere' => [$sansFacette->filiere, $sansFacette->thematique] = [null, null],
            'niveau' => $sansFacette->niveau = null,
            'cpf' => $sansFacette->cpf = false,
            'modalite' => $sansFacette->modalite = null,
            'type' => $sansFacette->type = null,
        };

        $expression = match ($facette) {
            'filiere' => 'fi.slug',
            'niveau' => 'f.niveauRncp',
            'cpf' => 'f.eligibleCpf',
            'modalite' => 'f.modalite',
            'type' => 'f.type',
        };

        $lignes = $this->requeteFiltree($sansFacette)
            ->select("$expression AS valeur, COUNT(f.id) AS total")
            ->groupBy('valeur')
            ->getQuery()
            ->getArrayResult();

        $comptes = [];
        foreach ($lignes as $ligne) {
            $valeur = $ligne['valeur'];
            if (null === $valeur) {
                continue;
            }
            $cle = match (true) {
                $valeur instanceof \BackedEnum => (string) $valeur->value,
                \is_bool($valeur) => $valeur ? '1' : '0',
                default => (string) $valeur,
            };
            $comptes[$cle] = (int) $ligne['total'];
        }

        return $comptes;
    }

    /**
     * Formations publiées mises en avant, avec leurs sessions (une seule requête).
     *
     * @return list<Formation>
     */
    public function findMisesEnAvant(TypeFormation $type, int $limite = 3): array
    {
        $ids = $this->createQueryBuilder('f')
            ->select('f.id')
            ->andWhere('f.publiee = true')
            ->andWhere('f.miseEnAvant = true')
            ->andWhere('f.type = :type')
            ->setParameter('type', $type)
            ->orderBy('f.updatedAt', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getSingleColumnResult();

        return $this->chargerAvecSessions($ids);
    }

    /**
     * Formations publiées d'un univers regroupées par filière (accueil).
     *
     * @return array<string, list<Formation>> nom de la filière => formations
     */
    public function findParFiliere(TypeFormation $type): array
    {
        $formations = $this->createQueryBuilder('f')
            ->addSelect('t', 'fi', 's')
            ->join('f.thematique', 't')
            ->join('t.filiere', 'fi')
            ->leftJoin('f.sessions', 's')
            ->andWhere('f.publiee = true')
            ->andWhere('f.type = :type')
            ->setParameter('type', $type)
            ->orderBy('fi.position', 'ASC')
            ->addOrderBy('f.niveauRncp', 'DESC')
            ->addOrderBy('f.intitule', 'ASC')
            ->getQuery()
            ->getResult();

        $groupes = [];
        foreach ($formations as $formation) {
            $groupes[(string) $formation->getFiliere()?->getNom()][] = $formation;
        }

        return $groupes;
    }

    public function findPublieeParSlug(string $slug): ?Formation
    {
        return $this->createQueryBuilder('f')
            ->addSelect('t', 'fi')
            ->join('f.thematique', 't')
            ->join('t.filiere', 'fi')
            ->andWhere('f.slug = :slug')
            ->andWhere('f.publiee = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Formations publiées proches (même thématique, sinon même filière).
     *
     * @return list<Formation>
     */
    public function findSimilaires(Formation $formation, int $limite = 3): array
    {
        return $this->createQueryBuilder('f')
            ->addSelect('t')
            ->join('f.thematique', 't')
            ->andWhere('f.publiee = true')
            ->andWhere('f.id != :id')
            ->andWhere('t.filiere = :filiere')
            ->andWhere('f.type = :type')
            ->setParameter('id', $formation->getId())
            ->setParameter('filiere', $formation->getFiliere())
            ->setParameter('type', $formation->getType())
            ->addSelect('CASE WHEN f.thematique = :thematique THEN 0 ELSE 1 END AS HIDDEN proximite')
            ->orderBy('proximite', 'ASC')
            ->addOrderBy('f.intitule', 'ASC')
            ->setParameter('thematique', $formation->getThematique())
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste des formations publiées pour les listes déroulantes des formulaires.
     */
    public function requetePubliees(): QueryBuilder
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.publiee = true')
            ->orderBy('f.type', 'ASC')
            ->addOrderBy('f.intitule', 'ASC');
    }

    private function requeteFiltree(CriteresRecherche $criteres): QueryBuilder
    {
        $qb = $this->createQueryBuilder('f')
            ->join('f.thematique', 't')
            ->join('t.filiere', 'fi')
            ->andWhere('f.publiee = true');

        if (null !== $criteres->type) {
            $qb->andWhere('f.type = :type')->setParameter('type', $criteres->type);
        }

        if (null !== $terme = $criteres->termeRecherche()) {
            // Chaque mot doit apparaître dans l'intitulé, le résumé ou la thématique.
            foreach (array_slice(preg_split('/\s+/u', $terme) ?: [], 0, 6) as $i => $mot) {
                $qb->andWhere($qb->expr()->orX(
                    "f.intitule LIKE :mot$i",
                    "f.resume LIKE :mot$i",
                    "t.nom LIKE :mot$i",
                    "f.codeRncp LIKE :mot$i",
                ))->setParameter("mot$i", '%'.addcslashes($mot, '%_\\').'%');
            }
        }

        if (null !== $criteres->filiere) {
            $qb->andWhere('fi.slug = :filiere')->setParameter('filiere', $criteres->filiere);
        }

        if (null !== $criteres->thematique) {
            $qb->andWhere('t.slug = :thematique')->setParameter('thematique', $criteres->thematique);
        }

        if (null !== $criteres->niveau) {
            $qb->andWhere('f.niveauRncp = :niveau')->setParameter('niveau', $criteres->niveau);
        }

        if ($criteres->cpf) {
            $qb->andWhere('f.eligibleCpf = true');
        }

        if (null !== $criteres->modalite) {
            $qb->andWhere('f.modalite = :modalite')->setParameter('modalite', $criteres->modalite);
        }

        return $qb;
    }

    /**
     * @param list<int|string> $ids
     *
     * @return list<Formation>
     */
    private function chargerAvecSessions(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $formations = $this->createQueryBuilder('f')
            ->addSelect('t', 's')
            ->join('f.thematique', 't')
            ->leftJoin('f.sessions', 's')
            ->andWhere('f.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        // Respecte l'ordre de la première requête.
        $rang = array_flip(array_map('intval', $ids));
        usort($formations, static fn (Formation $a, Formation $b): int => $rang[$a->getId()] <=> $rang[$b->getId()]);

        return $formations;
    }
}
