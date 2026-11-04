<?php

namespace App\Repository;

use App\Entity\Score;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * The rankings: fastest first, and between equal times, the one that arrived first.
 *
 * @extends ServiceEntityRepository<Score>
 */
final class ScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Score::class);
    }

    /** @return list<Score> The ten best, with their players. */
    public function ranking(string $game, string $variant): array
    {
        return $this->ordered($game, $variant)
            ->addSelect('p')
            ->join('s.player', 'p')
            ->setMaxResults(Score::RANKING_SIZE)
            ->getQuery()
            ->getResult();
    }

    /** Times as good as [$seconds] or better: a new one with that time ranks after all of them. */
    public function countAtOrBelow(string $game, string $variant, int $seconds): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.game = :game AND s.variant = :variant AND s.seconds <= :seconds')
            ->setParameter('game', $game)
            ->setParameter('variant', $variant)
            ->setParameter('seconds', $seconds)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** 1-based, or null if it has been pushed out of the ten best. */
    public function rankOf(Score $score): ?int
    {
        $ahead = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.game = :game AND s.variant = :variant')
            ->andWhere('s.seconds < :seconds OR (s.seconds = :seconds AND s.sequence < :sequence)')
            ->setParameter('game', $score->getGame())
            ->setParameter('variant', $score->getVariant())
            ->setParameter('seconds', $score->getSeconds())
            ->setParameter('sequence', $score->getSequence())
            ->getQuery()
            ->getSingleScalarResult();

        return $ahead < Score::RANKING_SIZE ? $ahead + 1 : null;
    }

    /** Deletes whatever has been pushed out of the ten best. */
    public function trim(string $game, string $variant): void
    {
        $outside = $this->ordered($game, $variant)
            ->setFirstResult(Score::RANKING_SIZE)
            ->setMaxResults(1000)
            ->getQuery()
            ->getResult();

        if ([] === $outside) {
            return;
        }

        $manager = $this->getEntityManager();
        foreach ($outside as $score) {
            $manager->remove($score);
        }
        $manager->flush();
    }

    private function ordered(string $game, string $variant): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->where('s.game = :game AND s.variant = :variant')
            ->setParameter('game', $game)
            ->setParameter('variant', $variant)
            ->orderBy('s.seconds', 'ASC')
            ->addOrderBy('s.sequence', 'ASC');
    }
}
